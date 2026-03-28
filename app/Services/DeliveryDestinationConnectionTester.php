<?php

namespace App\Services;

use App\Models\DeliveryDestination;
use phpseclib3\Net\SFTP as PhpseclibSftp;

class DeliveryDestinationConnectionTester
{
    public function test(DeliveryDestination $destination): array
    {
        $type = $destination->type ?? '';
        if ($type === 'email') {
            return ['success' => true, 'message' => 'E-Mail-Destination: Kein Verbindungstest nötig.'];
        }
        if ($type === 'ftp') {
            return $this->testFtp($destination, false);
        }
        if ($type === 'ftps') {
            return $this->testFtps($destination);
        }
        if ($type === 'sftp') {
            return $this->testSftp($destination);
        }

        return ['success' => false, 'message' => 'Unbekannter Typ: '.$type];
    }

    private function testFtp(DeliveryDestination $destination, bool $useSsl): array
    {
        $host = $destination->getHostOrConfig();
        $port = $destination->getPortOrConfig();
        $username = $destination->getUsernameOrConfig();
        $password = $destination->getDecryptedPassword();
        $passive = $destination->getPassiveOrConfig();
        $timeout = $destination->getTimeoutOrConfig();
        $remotePath = $destination->getRemotePathOrConfig();

        if (empty($host) || empty($username)) {
            return ['success' => false, 'message' => 'Host und Benutzername sind Pflicht.'];
        }

        $conn = $useSsl && function_exists('ftp_ssl_connect')
            ? @ftp_ssl_connect($host, $port, $timeout)
            : @ftp_connect($host, $port, $timeout);

        if ($conn === false) {
            return ['success' => false, 'message' => 'Verbindung zum Server fehlgeschlagen.'];
        }

        if (@ftp_login($conn, $username, $password ?? '') === false) {
            ftp_close($conn);

            return ['success' => false, 'message' => 'Login fehlgeschlagen (Benutzername/Passwort).'];
        }

        @ftp_pasv($conn, $passive);

        if ($remotePath !== '' && $remotePath !== '/') {
            if (@ftp_chdir($conn, $remotePath) === false) {
                ftp_close($conn);

                return ['success' => false, 'message' => 'Zielverzeichnis nicht erreichbar: '.$remotePath];
            }
        }

        @ftp_nlist($conn, '.');
        ftp_close($conn);

        return ['success' => true, 'message' => 'Verbindung erfolgreich.'];
    }

    private function testFtps(DeliveryDestination $destination): array
    {
        if (! function_exists('ftp_ssl_connect')) {
            return ['success' => false, 'message' => 'FTPS wird von dieser PHP-Installation nicht unterstützt (ftp_ssl_connect fehlt). Bitte FTP oder SFTP verwenden oder PHP mit OpenSSL-FTP-Support kompilieren.'];
        }

        return $this->testFtp($destination, true);
    }

    private function testSftp(DeliveryDestination $destination): array
    {
        $host = $destination->getHostOrConfig();
        $port = $destination->getPortOrConfig();
        $username = $destination->getUsernameOrConfig();
        $remotePath = $destination->getRemotePathOrConfig();
        $timeout = $destination->getTimeoutOrConfig();

        if (empty($host) || empty($username)) {
            return ['success' => false, 'message' => 'Host und Benutzername sind Pflicht.'];
        }

        // Zuerst phpseclib versuchen (funktioniert auch ohne ext-ssh2, gleicher Weg wie manueller Test)
        $result = $this->testSftpViaPhpseclib($destination, $host, $port, $username, $remotePath, $timeout);
        if ($result['success']) {
            return $result;
        }
        // Fallback auf ext-ssh2, falls phpseclib fehlschlägt (z. B. anderer Fehler)
        if (function_exists('ssh2_connect')) {
            return $this->testSftpViaSsh2($destination, $host, $port, $username, $remotePath, $timeout);
        }

        return $result;
    }

    private function testSftpViaSsh2(DeliveryDestination $destination, string $host, int $port, string $username, string $remotePath, int $timeout): array
    {
        $conn = @ssh2_connect($host, $port, ['hostkey' => 'ssh-rsa,ssh-dss,ecdsa-sha2-nistp256,ecdsa-sha2-nistp384,ecdsa-sha2-nistp521,ssh-ed25519']);
        if ($conn === false) {
            return ['success' => false, 'message' => 'SSH2-Verbindung zum Server fehlgeschlagen.'];
        }

        $key = $destination->getDecryptedPrivateKey();
        $passphrase = $destination->getDecryptedPrivateKeyPassphrase();
        $password = $destination->getDecryptedPassword();

        if ($key !== null && $key !== '') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ssh2key_');
            try {
                file_put_contents($tmpFile, $key);
                if (@ssh2_auth_pubkey_file($conn, $username, $tmpFile, $tmpFile, $passphrase ?? '') === false) {
                    @unlink($tmpFile);

                    return ['success' => false, 'message' => 'SSH2-Login mit Private Key fehlgeschlagen.'];
                }
            } finally {
                if (@file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        } else {
            if (@ssh2_auth_password($conn, $username, $password ?? '') === false) {
                return ['success' => false, 'message' => 'SSH2-Login fehlgeschlagen (Benutzername/Passwort).'];
            }
        }

        $sftp = @ssh2_sftp($conn);
        if ($sftp === false) {
            return ['success' => false, 'message' => 'SFTP-Subsystem konnte nicht initialisiert werden.'];
        }

        $path = $remotePath === '' || $remotePath === '/' ? '/' : $remotePath;
        $realPath = 'ssh2.sftp://'.(int) $sftp.$path;
        if (! @is_dir($realPath)) {
            return ['success' => false, 'message' => 'Zielverzeichnis nicht erreichbar: '.$path];
        }

        return ['success' => true, 'message' => 'SFTP-Verbindung erfolgreich.'];
    }

    private function testSftpViaPhpseclib(DeliveryDestination $destination, string $host, int $port, string $username, string $remotePath, int $timeout): array
    {
        $password = $destination->getDecryptedPassword();
        $privateKey = $destination->getDecryptedPrivateKey();
        $passphrase = $destination->getDecryptedPrivateKeyPassphrase();

        try {
            $sftp = new PhpseclibSftp($host, $port, $timeout);

            if ($privateKey !== null && $privateKey !== '') {
                $key = \phpseclib3\Crypt\PublicKeyLoader::load($privateKey, $passphrase ?? false);
                if (! $sftp->login($username, $key)) {
                    return ['success' => false, 'message' => 'SFTP-Login mit Private Key fehlgeschlagen.'];
                }
            } else {
                if (! $sftp->login($username, $password ?? '')) {
                    $lastError = method_exists($sftp, 'getLastError') ? ($sftp->getLastError() ?: '') : '';
                    $msg = 'SFTP-Login fehlgeschlagen (Benutzername/Passwort).';
                    if ($lastError !== '') {
                        $msg .= ' '.$lastError;
                    }

                    return ['success' => false, 'message' => $msg];
                }
            }

            $path = $remotePath === '' || $remotePath === '/' ? '.' : $remotePath;
            $list = @$sftp->nlist($path);
            if ($list === false) {
                return ['success' => false, 'message' => 'Zielverzeichnis nicht erreichbar: '.$path];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SFTP: '.$e->getMessage()];
        }

        return ['success' => true, 'message' => 'SFTP-Verbindung erfolgreich (phpseclib).'];
    }
}

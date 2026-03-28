<?php

namespace App\Jobs;

use App\Mail\DeliveryRunFinishedMail;
use App\Models\DeliveryDestination;
use App\Models\DeliveryRun;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use phpseclib3\Net\SFTP as PhpseclibSftp;

class UploadMediaToDestinationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $deliveryDestinationId,
        public array $mediaIds,
        public int $runId = 0
    ) {}

    public function handle(): void
    {
        $destination = DeliveryDestination::find($this->deliveryDestinationId);
        if (! $destination) {
            return;
        }

        $run = $this->runId > 0
            ? DeliveryRun::find($this->runId)
            : DeliveryRun::create([
                'delivery_destination_id' => $destination->id,
                'status' => 'running',
                'started_at' => now(),
            ]);

        if (! $run) {
            return;
        }

        $run->update(['status' => 'running', 'started_at' => $run->started_at ?? now()]);

        $type = $destination->type ?? '';
        if ($type === 'ftp' || $type === 'ftps') {
            $this->uploadViaFtp($destination, $run);
        } elseif ($type === 'sftp') {
            $this->uploadViaSftp($destination, $run);
        } else {
            $this->finishRun($run, $destination, 'failed', 'Typ nicht unterstützt: '.$type);
        }
    }

    /**
     * Bei WDR-Format: nach Meldung gruppieren, Unterordner = MoID oder Jahr_Monat_Tag_Ort_Titel.
     *
     * @return array<int, array{subfolder: string, media_ids: int[]}>
     */
    private function groupMediaByNewsItem(DeliveryDestination $destination): array
    {
        // Für WDR-Ziele immer pro Meldung einen Unterordner (MoID oder Jahr_Monat_Tag_Ort_Titel)
        $useWdr = $this->isWdrDestination($destination)
            || ! empty($destination->config_json['wdr_subfolder_per_item'] ?? false);
        if (! $useWdr) {
            return [0 => ['subfolder' => '', 'media_ids' => $this->mediaIds]];
        }
        $byItem = [];
        foreach ($this->mediaIds as $mediaId) {
            $media = NewsItemMedia::find($mediaId);
            $nid = $media ? $media->news_item_id : 0;
            if (! isset($byItem[$nid])) {
                $byItem[$nid] = ['subfolder' => '', 'media_ids' => []];
            }
            $byItem[$nid]['media_ids'][] = $mediaId;
        }
        foreach ($byItem as $nid => &$g) {
            if ($nid && ($newsItem = NewsItem::find($nid))) {
                $g['subfolder'] = $newsItem->wdr_subfolder_name;
            } else {
                $g['subfolder'] = 'Meldung_'.$nid;
            }
        }

        return $byItem;
    }

    private function ftpDirExists($conn, string $dir): bool
    {
        $cur = @ftp_pwd($conn);
        if ($cur === false) {
            return false;
        }
        $ok = @ftp_chdir($conn, $dir);
        if ($ok && $cur !== false) {
            @ftp_chdir($conn, $cur);
        }

        return $ok;
    }

    /** WDR: Es dürfen nur Videos hochgeladen werden, keine Bilder. */
    private function shouldUploadMediaForDestination(DeliveryDestination $destination, NewsItemMedia $media): bool
    {
        // Für WDR-Ziele grundsätzlich nur Videos (keine Bilder) hochladen
        if ($this->isWdrDestination($destination)) {
            return $media->type === 'video';
        }

        // Sonst nur bei aktivierter WDR-Unterordner-Option einschränken
        if (empty($destination->config_json['wdr_subfolder_per_item'] ?? false)) {
            return true;
        }

        return $media->type === 'video';
    }

    /**
     * Ermittelt, ob es sich um ein WDR-Versandziel handelt (Organisationname enthält "WDR" etc.).
     */
    private function isWdrDestination(DeliveryDestination $destination): bool
    {
        $org = $destination->relationLoaded('organization')
            ? $destination->organization
            : $destination->organization;

        if (! $org || ! isset($org->name)) {
            return false;
        }

        $names = config('newsdesk.wdr_organization_names', ['WDR', 'Westdeutscher Rundfunk']);
        foreach ($names as $name) {
            if ($name !== '' && stripos($org->name, $name) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Auslieferungs-Dateiname: optional mit externem Suffix (vendor_code/author_id/supplier_id). */
    private function getDeliveredFilename(DeliveryDestination $destination, string $localBasename): string
    {
        $suffix = $destination->getFilenameSuffix();
        if ($suffix === null || $suffix === '') {
            return $localBasename;
        }
        $pathInfo = pathinfo($localBasename);
        $base = $pathInfo['filename'] ?? $localBasename;
        $ext = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';

        return $base.'_'.$suffix.$ext;
    }

    /**
     * Sidecar-JSON bauen und als Datei-Inhalt zurückgeben (für Upload).
     *
     * @param  array<int, array{original_name: string, delivered_name: string, size_bytes: int, sha256: string}>  $files
     */
    private function buildSidecarJson(DeliveryDestination $destination, ?NewsItem $newsItem, array $files): string
    {
        $external = $destination->getExternalIdsResolved();
        if ($external['label'] === 'Externe Kennung' && $destination->label) {
            $external['label'] = $destination->label;
        }
        $payload = [
            'news_id' => $newsItem?->id,
            'title' => $newsItem?->title,
            'published_at' => $newsItem?->published_at?->toIso8601String(),
            'destination' => $destination->label,
            'external' => [
                'label' => $external['label'],
                'author_id' => $external['author_id'] ?: null,
                'supplier_id' => $external['supplier_id'] ?: null,
                'vendor_code' => $external['vendor_code'] ?: null,
            ],
            'files' => $files,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function uploadViaFtp(DeliveryDestination $destination, DeliveryRun $run): void
    {
        $host = $destination->getHostOrConfig();
        $port = $destination->getPortOrConfig();
        $username = $destination->getUsernameOrConfig();
        $password = $destination->getDecryptedPassword();
        $remotePath = $destination->getRemotePathOrConfig();
        $passive = $destination->getPassiveOrConfig();
        $timeout = $destination->getTimeoutOrConfig();
        $useSsl = ($destination->type ?? '') === 'ftps';

        if (empty($host) || empty($username)) {
            $this->finishRun($run, $destination, 'failed', 'Host/Benutzername fehlt');

            return;
        }

        $conn = $useSsl && function_exists('ftp_ssl_connect')
            ? @ftp_ssl_connect($host, $port, $timeout)
            : @ftp_connect($host, $port, $timeout);

        if ($conn === false) {
            $this->finishRun($run, $destination, 'failed', 'FTP-Verbindung fehlgeschlagen');

            return;
        }

        if (@ftp_login($conn, $username, $password ?? '') === false) {
            ftp_close($conn);
            $this->finishRun($run, $destination, 'failed', 'FTP-Login fehlgeschlagen');

            return;
        }

        @ftp_pasv($conn, $passive);
        if ($remotePath !== '' && $remotePath !== '/') {
            if (@ftp_chdir($conn, $remotePath) === false) {
                ftp_close($conn);
                $this->finishRun($run, $destination, 'failed', 'Zielverzeichnis nicht erreichbar');

                return;
            }
        }

        $failed = 0;
        $groups = $this->groupMediaByNewsItem($destination);
        foreach ($groups as $group) {
            $subfolder = $group['subfolder'];
            if ($subfolder !== '' && @ftp_mkdir($conn, $subfolder) === false && ! $this->ftpDirExists($conn, $subfolder)) {
                $this->finishRun($run, $destination, 'failed', 'Unterordner anlegen fehlgeschlagen: '.$subfolder);
                ftp_close($conn);

                return;
            }
            $sidecarFiles = [];
            $newsItem = null;
            foreach ($group['media_ids'] as $mediaId) {
                $item = $run->items()->where('news_item_media_id', $mediaId)->first();
                if (! $item) {
                    $item = $run->items()->create(['news_item_media_id' => $mediaId, 'filename' => 'unknown', 'status' => 'pending']);
                }
                $media = NewsItemMedia::find($mediaId);
                if (! $media) {
                    $item->update(['status' => 'failed', 'message' => 'Medium nicht gefunden']);
                    $failed++;

                    continue;
                }
                if ($newsItem === null) {
                    $newsItem = $media->newsItem;
                }
                if (! $this->shouldUploadMediaForDestination($destination, $media)) {
                    // Für nicht passende Medien (z. B. Bilder bei WDR) nur "skipped" markieren,
                    // damit der Lauf als erfolgreich gelten kann, wenn alle relevanten Dateien ok sind.
                    $item->update(['status' => 'skipped', 'message' => 'Übersprungen (für dieses Ziel nicht vorgesehen).']);

                    continue;
                }
                $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
                $fullPath = $resolved['path'] ?? null;
                $originalBasename = basename($media->path);
                $deliveredFilename = $this->getDeliveredFilename($destination, $originalBasename);
                $item->update(['filename' => $deliveredFilename]);
                if (! is_string($fullPath) || ! is_file($fullPath)) {
                    $item->update(['status' => 'failed', 'message' => 'Datei nicht gefunden']);
                    $failed++;

                    continue;
                }
                $remoteFile = $subfolder === '' ? $deliveredFilename : $subfolder.'/'.$deliveredFilename;
                if (@ftp_put($conn, $remoteFile, $fullPath, FTP_BINARY)) {
                    $item->update(['status' => 'success']);
                    if ($destination->generate_sidecar) {
                        $sidecarFiles[] = [
                            'original_name' => $media->original_name ?? $originalBasename,
                            'delivered_name' => $deliveredFilename,
                            'size_bytes' => (int) @filesize($fullPath),
                            'sha256' => @hash_file('sha256', $fullPath) ?: '',
                        ];
                    }
                } else {
                    $item->update(['status' => 'failed', 'message' => 'FTP-Upload fehlgeschlagen']);
                    $failed++;
                }
                app(MediaStorage::class)->cleanupResolvedPath($resolved);
            }
            if ($destination->generate_sidecar && count($sidecarFiles) > 0) {
                $sidecarContent = $this->buildSidecarJson($destination, $newsItem, $sidecarFiles);
                $sidecarName = 'delivery.meta.json';
                $tmpSidecar = tempnam(sys_get_temp_dir(), 'sidecar_');
                if ($tmpSidecar !== false && file_put_contents($tmpSidecar, $sidecarContent) !== false) {
                    $remoteSidecar = $subfolder === '' ? $sidecarName : $subfolder.'/'.$sidecarName;
                    if (@ftp_put($conn, $remoteSidecar, $tmpSidecar, FTP_ASCII)) {
                        // Sidecar hochgeladen
                    }
                    @unlink($tmpSidecar);
                }
            }
        }

        ftp_close($conn);
        $this->finishRun(
            $run,
            $destination,
            $failed === 0 ? 'success' : 'failed',
            $failed === 0 ? null : $failed.' von '.count($this->mediaIds).' fehlgeschlagen'
        );
    }

    private function uploadViaSftp(DeliveryDestination $destination, DeliveryRun $run): void
    {
        $host = $destination->getHostOrConfig();
        $port = $destination->getPortOrConfig();
        $username = $destination->getUsernameOrConfig();
        $remotePath = $destination->getRemotePathOrConfig();
        $timeout = $destination->getTimeoutOrConfig();

        if (empty($host) || empty($username)) {
            $this->finishRun($run, $destination, 'failed', 'Host/Benutzername fehlt');

            return;
        }

        // Verwende für SFTP immer phpseclib, da dies auch im Verbindungstest eingesetzt wird
        $this->uploadViaSftpPhpseclib($destination, $run, $host, $port, $username, $remotePath, $timeout);
    }

    private function uploadViaSftpSsh2(DeliveryDestination $destination, DeliveryRun $run, string $host, int $port, string $username, string $remotePath, int $timeout): void
    {
        $conn = @ssh2_connect($host, $port, ['hostkey' => 'ssh-rsa,ssh-dss,ecdsa-sha2-nistp256,ecdsa-sha2-nistp384,ecdsa-sha2-nistp521,ssh-ed25519']);
        if ($conn === false) {
            $this->finishRun($run, $destination, 'failed', 'SSH2-Verbindung fehlgeschlagen');

            return;
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
                    $this->finishRun($run, $destination, 'failed', 'SSH2-Login (Key) fehlgeschlagen');

                    return;
                }
            } finally {
                if (@file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        } else {
            if (@ssh2_auth_password($conn, $username, $password ?? '') === false) {
                $this->finishRun($run, $destination, 'failed', 'SSH2-Login fehlgeschlagen');

                return;
            }
        }

        $sftp = @ssh2_sftp($conn);
        if ($sftp === false) {
            $this->finishRun($run, $destination, 'failed', 'SFTP-Subsystem fehlgeschlagen');

            return;
        }

        $basePath = $remotePath === '' || $remotePath === '/' ? '' : rtrim($remotePath, '/');
        $failed = 0;
        $groups = $this->groupMediaByNewsItem($destination);

        foreach ($groups as $group) {
            $subfolder = $group['subfolder'];
            $dirPath = $subfolder === '' ? $basePath : ($basePath === '' ? $subfolder : $basePath.'/'.$subfolder);
            if ($dirPath !== '' && ! @is_dir('ssh2.sftp://'.(int) $sftp.'/'.ltrim($dirPath, '/'))) {
                @ssh2_sftp_mkdir($sftp, ltrim($dirPath, '/'));
            }
            $sidecarFiles = [];
            $newsItem = null;
            foreach ($group['media_ids'] as $mediaId) {
                $item = $run->items()->where('news_item_media_id', $mediaId)->first();
                if (! $item) {
                    $item = $run->items()->create(['news_item_media_id' => $mediaId, 'filename' => 'unknown', 'status' => 'pending']);
                }
                $media = NewsItemMedia::find($mediaId);
                if (! $media) {
                    $item->update(['status' => 'failed', 'message' => 'Medium nicht gefunden']);
                    $failed++;

                    continue;
                }
                if ($newsItem === null) {
                    $newsItem = $media->newsItem;
                }
                if (! $this->shouldUploadMediaForDestination($destination, $media)) {
                    $item->update(['status' => 'skipped', 'message' => 'Übersprungen (für dieses Ziel nicht vorgesehen).']);

                    continue;
                }
                $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
                $fullPath = $resolved['path'] ?? null;
                $originalBasename = basename($media->path);
                $deliveredFilename = $this->getDeliveredFilename($destination, $originalBasename);
                $item->update(['filename' => $deliveredFilename]);
                if (! is_string($fullPath) || ! is_file($fullPath)) {
                    $item->update(['status' => 'failed', 'message' => 'Datei nicht gefunden']);
                    $failed++;

                    continue;
                }
                $remoteFile = $dirPath === '' ? $deliveredFilename : $dirPath.'/'.$deliveredFilename;
                $stream = @fopen('ssh2.sftp://'.(int) $sftp.'/'.ltrim($remoteFile, '/'), 'w');
                if ($stream === false) {
                    $item->update(['status' => 'failed', 'message' => 'SFTP-Datei öffnen fehlgeschlagen']);
                    $failed++;

                    continue;
                }
                $data = @file_get_contents($fullPath);
                if ($data === false || @fwrite($stream, $data) === false) {
                    @fclose($stream);
                    $item->update(['status' => 'failed', 'message' => 'SFTP-Schreiben fehlgeschlagen']);
                    $failed++;

                    continue;
                }
                @fclose($stream);
                $item->update(['status' => 'success']);
                if ($destination->generate_sidecar) {
                    $sidecarFiles[] = [
                        'original_name' => $media->original_name ?? $originalBasename,
                        'delivered_name' => $deliveredFilename,
                        'size_bytes' => (int) @filesize($fullPath),
                        'sha256' => @hash_file('sha256', $fullPath) ?: '',
                    ];
                }
                app(MediaStorage::class)->cleanupResolvedPath($resolved);
            }
            if ($destination->generate_sidecar && count($sidecarFiles) > 0) {
                $sidecarContent = $this->buildSidecarJson($destination, $newsItem, $sidecarFiles);
                $sidecarName = 'delivery.meta.json';
                $tmpSidecar = tempnam(sys_get_temp_dir(), 'sidecar_');
                if ($tmpSidecar !== false && file_put_contents($tmpSidecar, $sidecarContent) !== false) {
                    $remoteSidecar = $dirPath === '' ? $sidecarName : $dirPath.'/'.$sidecarName;
                    $stream = @fopen('ssh2.sftp://'.(int) $sftp.'/'.ltrim($remoteSidecar, '/'), 'w');
                    if ($stream !== false && @fwrite($stream, $sidecarContent) !== false) {
                        @fclose($stream);
                    }
                    @unlink($tmpSidecar);
                }
            }
        }

        $this->finishRun(
            $run,
            $destination,
            $failed === 0 ? 'success' : 'failed',
            $failed === 0 ? null : $failed.' von '.count($this->mediaIds).' fehlgeschlagen'
        );
    }

    private function uploadViaSftpPhpseclib(DeliveryDestination $destination, DeliveryRun $run, string $host, int $port, string $username, string $remotePath, int $timeout): void
    {
        $password = $destination->getDecryptedPassword();
        $privateKey = $destination->getDecryptedPrivateKey();
        $passphrase = $destination->getDecryptedPrivateKeyPassphrase();

        try {
            $sftp = new PhpseclibSftp($host, $port, $timeout);
        } catch (\Throwable $e) {
            Log::warning('SFTP connect failed', ['host' => $host, 'port' => $port, 'error' => $e->getMessage()]);
            $this->finishRun($run, $destination, 'failed', 'SFTP-Verbindung fehlgeschlagen: '.$e->getMessage());

            return;
        }

        $loginOk = false;
        if ($privateKey !== null && $privateKey !== '') {
            try {
                $key = \phpseclib3\Crypt\PublicKeyLoader::load($privateKey, $passphrase ?? false);
                $loginOk = $sftp->login($username, $key);
            } catch (\Throwable $e) {
                Log::warning('SFTP key load/login failed', ['destination' => $destination->id, 'error' => $e->getMessage()]);
                $this->finishRun($run, $destination, 'failed', 'SFTP-Login (Key): '.$e->getMessage());

                return;
            }
            if (! $loginOk) {
                $detail = $this->getSftpLastError($sftp);
                Log::warning('SFTP login (key) rejected', ['destination' => $destination->id, 'lastError' => $detail]);
                $this->finishRun($run, $destination, 'failed', 'SFTP-Login (Key) fehlgeschlagen'.$detail);

                return;
            }
        } else {
            $loginOk = $sftp->login($username, $password ?? '');
            if (! $loginOk) {
                $detail = $this->getSftpLastError($sftp);
                Log::warning('SFTP login (password) rejected', ['destination' => $destination->id, 'host' => $host, 'lastError' => $detail]);
                $this->finishRun($run, $destination, 'failed', 'SFTP-Login fehlgeschlagen'.$detail);

                return;
            }
        }

        $basePath = $remotePath === '' || $remotePath === '/' ? '' : rtrim($remotePath, '/');
        $failed = 0;
        $groups = $this->groupMediaByNewsItem($destination);

        foreach ($groups as $group) {
            $subfolder = $group['subfolder'];
            $dirPath = $subfolder === '' ? $basePath : ($basePath === '' ? $subfolder : $basePath.'/'.$subfolder);
            if ($dirPath !== '' && ! $sftp->file_exists($dirPath)) {
                $sftp->mkdir($dirPath, 0755);
            }
            $sidecarFiles = [];
            $newsItem = null;
            foreach ($group['media_ids'] as $mediaId) {
                $item = $run->items()->where('news_item_media_id', $mediaId)->first();
                if (! $item) {
                    $item = $run->items()->create(['news_item_media_id' => $mediaId, 'filename' => 'unknown', 'status' => 'pending']);
                }
                $media = NewsItemMedia::find($mediaId);
                if (! $media) {
                    $item->update(['status' => 'failed', 'message' => 'Medium nicht gefunden']);
                    $failed++;

                    continue;
                }
                if ($newsItem === null) {
                    $newsItem = $media->newsItem;
                }
                if (! $this->shouldUploadMediaForDestination($destination, $media)) {
                    $item->update(['status' => 'skipped', 'message' => 'Übersprungen (für dieses Ziel nicht vorgesehen).']);

                    continue;
                }
                $resolved = app(MediaStorage::class)->resolveReadableLocalPath($media->path);
                $fullPath = $resolved['path'] ?? null;
                $originalBasename = basename($media->path);
                $deliveredFilename = $this->getDeliveredFilename($destination, $originalBasename);
                $item->update(['filename' => $deliveredFilename]);
                if (! is_string($fullPath) || ! is_file($fullPath)) {
                    $item->update(['status' => 'failed', 'message' => 'Datei nicht gefunden']);
                    $failed++;

                    continue;
                }
                $remoteFile = $dirPath === '' ? $deliveredFilename : $dirPath.'/'.$deliveredFilename;
                if (! @$sftp->put($remoteFile, $fullPath, PhpseclibSftp::SOURCE_LOCAL_FILE)) {
                    $item->update(['status' => 'failed', 'message' => 'SFTP put fehlgeschlagen']);
                    $failed++;

                    continue;
                }
                $item->update(['status' => 'success']);
                if ($destination->generate_sidecar) {
                    $sidecarFiles[] = [
                        'original_name' => $media->original_name ?? $originalBasename,
                        'delivered_name' => $deliveredFilename,
                        'size_bytes' => (int) @filesize($fullPath),
                        'sha256' => @hash_file('sha256', $fullPath) ?: '',
                    ];
                }
                app(MediaStorage::class)->cleanupResolvedPath($resolved);
            }
            if ($destination->generate_sidecar && count($sidecarFiles) > 0) {
                $sidecarContent = $this->buildSidecarJson($destination, $newsItem, $sidecarFiles);
                $sidecarName = 'delivery.meta.json';
                $remoteSidecar = $dirPath === '' ? $sidecarName : $dirPath.'/'.$sidecarName;
                $sftp->put($remoteSidecar, $sidecarContent);
            }
        }

        $this->finishRun(
            $run,
            $destination,
            $failed === 0 ? 'success' : 'failed',
            $failed === 0 ? null : $failed.' von '.count($this->mediaIds).' fehlgeschlagen'
        );
    }

    private function getSftpLastError(PhpseclibSftp $sftp): string
    {
        if (! method_exists($sftp, 'getLastError')) {
            return '';
        }
        $err = $sftp->getLastError();
        if ($err === null || $err === '') {
            return '';
        }

        return ': '.$err;
    }

    /**
     * Run abschließen und Benachrichtigung versenden.
     */
    private function finishRun(DeliveryRun $run, DeliveryDestination $destination, string $status, ?string $message = null): void
    {
        $run->update([
            'status' => $status,
            'message' => $message,
            'finished_at' => now(),
        ]);

        try {
            Mail::to('redaktion@erftkreis-news.de')->send(new DeliveryRunFinishedMail($run, $destination));
        } catch (\Throwable) {
            // Mailfehler sollen den Upload nicht fehlschlagen lassen
        }
    }
}

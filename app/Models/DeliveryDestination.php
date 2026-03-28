<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class DeliveryDestination extends Model
{
    protected $fillable = [
        'organization_id',
        'product_id',
        'label',
        'type',
        'host',
        'port',
        'username',
        'password_encrypted',
        'private_key_encrypted',
        'private_key_passphrase_encrypted',
        'remote_path',
        'passive',
        'timeout',
        'config_json',
        'active',
        'external_reference_label',
        'external_author_id',
        'external_supplier_id',
        'external_vendor_code',
        'include_in_email',
        'include_in_filename',
        'generate_sidecar',
    ];

    protected $casts = [
        'active' => 'boolean',
        'passive' => 'boolean',
        'port' => 'integer',
        'timeout' => 'integer',
        'config_json' => 'array',
        'include_in_email' => 'boolean',
        'include_in_filename' => 'boolean',
        'generate_sidecar' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliveryRuns(): HasMany
    {
        return $this->hasMany(DeliveryRun::class, 'delivery_destination_id');
    }

    /** Host (Spalte oder Fallback config_json für Alt-Daten). */
    public function getHostOrConfig(): ?string
    {
        if ($this->host !== null && $this->host !== '') {
            return $this->host;
        }

        return $this->config_json['host'] ?? null;
    }

    /** Port (Spalte oder Fallback). */
    public function getPortOrConfig(): int
    {
        if ($this->port !== null) {
            return (int) $this->port;
        }
        $type = $this->type ?? '';
        if ($type === 'sftp') {
            return 22;
        }

        return (int) ($this->config_json['port'] ?? 21);
    }

    /** Username (Spalte oder Fallback). */
    public function getUsernameOrConfig(): ?string
    {
        if ($this->username !== null && $this->username !== '') {
            return $this->username;
        }

        return $this->config_json['username'] ?? null;
    }

    /** Remote-Pfad (Spalte oder Fallback). */
    public function getRemotePathOrConfig(): string
    {
        if ($this->remote_path !== null && $this->remote_path !== '') {
            return rtrim($this->remote_path, '/');
        }

        return rtrim((string) ($this->config_json['path'] ?? '/'), '/');
    }

    /** Passiv (Spalte oder Fallback). */
    public function getPassiveOrConfig(): bool
    {
        if (isset($this->passive)) {
            return (bool) $this->passive;
        }

        return (bool) ($this->config_json['passive'] ?? true);
    }

    /** Timeout (Sekunden). */
    public function getTimeoutOrConfig(): int
    {
        return (int) ($this->timeout ?? $this->config_json['timeout'] ?? 20);
    }

    public function setEncryptedPassword(?string $password): void
    {
        if ($password !== null && $password !== '') {
            $this->password_encrypted = Crypt::encryptString($password);
        }
        $this->save();
    }

    public function hasPasswordSet(): bool
    {
        if ($this->password_encrypted !== null && $this->password_encrypted !== '') {
            return true;
        }

        return ! empty($this->config_json['password_encrypted'] ?? null);
    }

    public function getDecryptedPassword(): ?string
    {
        if ($this->password_encrypted !== null && $this->password_encrypted !== '') {
            try {
                return Crypt::decryptString($this->password_encrypted);
            } catch (\Throwable) {
                return null;
            }
        }
        $enc = $this->config_json['password_encrypted'] ?? null;
        if ($enc === null || $enc === '') {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setPrivateKey(?string $key): void
    {
        $this->private_key_encrypted = $key !== null && $key !== '' ? Crypt::encryptString($key) : null;
        $this->save();
    }

    public function hasPrivateKeySet(): bool
    {
        return $this->private_key_encrypted !== null && $this->private_key_encrypted !== '';
    }

    public function getDecryptedPrivateKey(): ?string
    {
        if ($this->private_key_encrypted === null || $this->private_key_encrypted === '') {
            return null;
        }
        try {
            return Crypt::decryptString($this->private_key_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setPrivateKeyPassphrase(?string $passphrase): void
    {
        $this->private_key_passphrase_encrypted = $passphrase !== null && $passphrase !== '' ? Crypt::encryptString($passphrase) : null;
        $this->save();
    }

    public function getDecryptedPrivateKeyPassphrase(): ?string
    {
        if ($this->private_key_passphrase_encrypted === null || $this->private_key_passphrase_encrypted === '') {
            return null;
        }
        try {
            return Crypt::decryptString($this->private_key_passphrase_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isFtpOrFtps(): bool
    {
        $t = $this->type ?? '';

        return $t === 'ftp' || $t === 'ftps';
    }

    public function isSftp(): bool
    {
        return ($this->type ?? '') === 'sftp';
    }

    public function isEmail(): bool
    {
        return ($this->type ?? '') === 'email';
    }

    /** E-Mail-Adressen (To) aus config_json für Versand. */
    public function getEmailToAddresses(): array
    {
        if ($this->type !== 'email') {
            return [];
        }
        $to = $this->config_json['to'] ?? [];

        return is_array($to) ? array_values(array_filter(array_map('trim', $to))) : [];
    }

    /** CC/BCC für Versand. */
    public function getEmailCcAddresses(): array
    {
        if ($this->type !== 'email') {
            return [];
        }
        $cc = $this->config_json['cc'] ?? [];

        return is_array($cc) ? array_values(array_filter(array_map('trim', $cc))) : [];
    }

    public function getEmailBccAddresses(): array
    {
        if ($this->type !== 'email') {
            return [];
        }
        $bcc = $this->config_json['bcc'] ?? [];

        return is_array($bcc) ? array_values(array_filter(array_map('trim', $bcc))) : [];
    }

    /**
     * Externe Identifikatoren für E-Mail-Footer / Sidecar.
     * Nutzt die Werte der Organisation (eine Stelle), falls vorhanden; sonst die des Versandziels.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getExternalIdentifiers(): array
    {
        if ($this->organization_id && $this->relationLoaded('organization')) {
            $org = $this->organization;
        } else {
            $org = $this->organization_id ? $this->organization : null;
        }
        if ($org && $org->getExternalIdentifiers() !== []) {
            return $org->getExternalIdentifiers();
        }
        $label = trim((string) ($this->external_reference_label ?? ''));
        if ($label === '') {
            $label = 'Externe Kennung';
        }
        $out = [];
        foreach (
            [
                'external_author_id' => $this->external_author_id,
                'external_supplier_id' => $this->external_supplier_id,
                'external_vendor_code' => $this->external_vendor_code,
            ] as $value
        ) {
            $v = trim((string) ($value ?? ''));
            if ($v !== '') {
                $out[] = ['label' => $label, 'value' => $v];
            }
        }

        return $out;
    }

    /**
     * Suffix für Dateinamen (include_in_filename): Organisation oder Versandziel, nur A-Z0-9_-.
     */
    public function getFilenameSuffix(): ?string
    {
        if (! $this->include_in_filename) {
            return null;
        }
        $org = $this->organization_id ? $this->organization : null;
        $raw = $org?->getFirstExternalIdValue();
        if ($raw === null || $raw === '') {
            $raw = $this->external_vendor_code ?? $this->external_author_id ?? $this->external_supplier_id ?? null;
        }
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        $suffix = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $raw);

        return $suffix !== '' ? $suffix : null;
    }

    /**
     * Aufgelöste externe IDs für Sidecar/Export (Organisation vor Versandziel).
     *
     * @return array{label: string, author_id: string|null, supplier_id: string|null, vendor_code: string|null}
     */
    public function getExternalIdsResolved(): array
    {
        $org = $this->organization_id ? $this->organization : null;
        $label = trim((string) ($org->external_reference_label ?? $this->external_reference_label ?? ''));
        if ($label === '') {
            $label = 'Externe Kennung';
        }
        $from = fn ($oVal, $dVal) => trim((string) ($oVal ?? ''));
        $fallback = fn ($oVal, $dVal) => $from($oVal, $dVal) !== '' ? $from($oVal, $dVal) : trim((string) ($dVal ?? ''));

        return [
            'label' => $label,
            'author_id' => $org ? $fallback($org->external_author_id, $this->external_author_id) : trim((string) ($this->external_author_id ?? '')),
            'supplier_id' => $org ? $fallback($org->external_supplier_id, $this->external_supplier_id) : trim((string) ($this->external_supplier_id ?? '')),
            'vendor_code' => $org ? $fallback($org->external_vendor_code, $this->external_vendor_code) : trim((string) ($this->external_vendor_code ?? '')),
        ];
    }
}

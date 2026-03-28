<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorage
{
    /**
     * Caches pro Request-Lifecycle, um S3-exists() (HEAD) nicht mehrfach auszulösen.
     *
     * @var array<string, array<string, bool>>
     */
    private array $existsCache = [];

    /**
     * @var array<string, string>
     */
    private array $urlCache = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $sizeCache = [];

    public function generateMediaPath(mixed $news, mixed $media, mixed $file, string $role = 'media'): string
    {
        return $this->buildMediaPath(
            news: $news,
            media: $media,
            file: $file,
            role: $role,
            derived: false
        );
    }

    public function generateDerivedMediaPath(mixed $news, mixed $media, mixed $file, string $role = 'media'): string
    {
        return $this->buildMediaPath(
            news: $news,
            media: $media,
            file: $file,
            role: $role,
            derived: true
        );
    }

    public function isStructuredNewsMediaPath(?string $path): bool
    {
        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        return (bool) preg_match('#^news-media/\d{4}/\d{2}/\d{2}/[^/]+/#', $path);
    }

    public function activeDiskName(): string
    {
        return (string) config('media_storage.disk', config('filesystems.default', 'public'));
    }

    public function fallbackDiskName(): string
    {
        return (string) config('media_storage.fallback_disk', 'public');
    }

    public function activeDisk(): Filesystem
    {
        return Storage::disk($this->activeDiskName());
    }

    public function fallbackDisk(): Filesystem
    {
        return Storage::disk($this->fallbackDiskName());
    }

    public function storeUploadedFileAs(UploadedFile $file, string $directory, string $filename): string
    {
        return $file->storeAs($directory, $filename, $this->activeDiskName());
    }

    public function exists(string $path): bool
    {
        $activeDiskName = $this->activeDiskName();
        $fallbackDiskName = $this->fallbackDiskName();

        if (! isset($this->existsCache[$activeDiskName][$path])) {
            $this->existsCache[$activeDiskName][$path] = $this->activeDisk()->exists($path);
        }

        if ($this->existsCache[$activeDiskName][$path] === true) {
            return true;
        }

        if ($fallbackDiskName === $activeDiskName) {
            return false;
        }

        if (! isset($this->existsCache[$fallbackDiskName][$path])) {
            $this->existsCache[$fallbackDiskName][$path] = $this->fallbackDisk()->exists($path);
        }

        return $this->existsCache[$fallbackDiskName][$path] === true;
    }

    /**
     * Liefert Dateigröße in Bytes (aktive Disk bevorzugt, sonst Fallback).
     * Nutzt Request-Lifecycle Memoization, um bei Listen nicht permanent HEADs auszuführen.
     */
    public function size(string $path): int
    {
        $activeDiskName = $this->activeDiskName();
        $fallbackDiskName = $this->fallbackDiskName();

        if (! isset($this->sizeCache[$activeDiskName][$path])) {
            // Wenn wir bereits via exists Cache wissen, dass es existiert, können wir direkt size abfragen.
            $activeExistsKnown = array_key_exists($path, $this->existsCache[$activeDiskName] ?? []);
            $activeExists = $activeExistsKnown ? ($this->existsCache[$activeDiskName][$path] ?? false) : null;

            if ($activeExists === true || $activeExists === null && $this->activeDisk()->exists($path) === true) {
                try {
                    $bytes = $this->activeDisk()->size($path);
                    $this->sizeCache[$activeDiskName][$path] = is_int($bytes) ? $bytes : (int) $bytes;
                } catch (\Throwable $e) {
                    Log::warning('MediaStorage.size.active_failed', [
                        'disk' => $activeDiskName,
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                    $this->sizeCache[$activeDiskName][$path] = 0;
                }
            } else {
                $this->sizeCache[$activeDiskName][$path] = 0;
            }
        }

        if ($this->sizeCache[$activeDiskName][$path] > 0 || $fallbackDiskName === $activeDiskName) {
            return $this->sizeCache[$activeDiskName][$path];
        }

        if (! isset($this->sizeCache[$fallbackDiskName][$path])) {
            $fallbackExists = array_key_exists($path, $this->existsCache[$fallbackDiskName] ?? [])
                ? ($this->existsCache[$fallbackDiskName][$path] ?? false)
                : null;

            if ($fallbackExists === true || $fallbackExists === null && $this->fallbackDisk()->exists($path) === true) {
                try {
                    $bytes = $this->fallbackDisk()->size($path);
                    $this->sizeCache[$fallbackDiskName][$path] = is_int($bytes) ? $bytes : (int) $bytes;
                } catch (\Throwable $e) {
                    Log::warning('MediaStorage.size.fallback_failed', [
                        'disk' => $fallbackDiskName,
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                    $this->sizeCache[$fallbackDiskName][$path] = 0;
                }
            } else {
                $this->sizeCache[$fallbackDiskName][$path] = 0;
            }
        }

        return $this->sizeCache[$fallbackDiskName][$path];
    }

    public function url(string $path): string
    {
        if (isset($this->urlCache[$path])) {
            return $this->urlCache[$path];
        }

        $activeDiskName = $this->activeDiskName();
        $fallbackDiskName = $this->fallbackDiskName();

        if ($fallbackDiskName === $activeDiskName) {
            return $this->urlCache[$path] = $this->activeDisk()->url($path);
        }

        // Performance: Wenn Fallback lokal ist (z. B. "public"), vermeiden wir S3-HEADs.
        // Wir prüfen dann zuerst lokal, und wenn lokal nicht existiert, nehmen wir die aktive Disk URL.
        if ($this->isLocalDisk($fallbackDiskName)) {
            if (! isset($this->existsCache[$fallbackDiskName][$path])) {
                $this->existsCache[$fallbackDiskName][$path] = $this->fallbackDisk()->exists($path);
            }
            if ($this->existsCache[$fallbackDiskName][$path] === true) {
                return $this->urlCache[$path] = $this->fallbackDisk()->url($path);
            }

            return $this->urlCache[$path] = $this->activeDisk()->url($path);
        }

        // Allgemein: Für nicht-lokales Fallback prüfen wir wie bisher beide Disks (HEAD kann dann auftreten).
        if (! isset($this->existsCache[$activeDiskName][$path])) {
            $this->existsCache[$activeDiskName][$path] = $this->activeDisk()->exists($path);
        }
        if ($this->existsCache[$activeDiskName][$path] === true) {
            return $this->urlCache[$path] = $this->activeDisk()->url($path);
        }

        if (! isset($this->existsCache[$fallbackDiskName][$path])) {
            $this->existsCache[$fallbackDiskName][$path] = $this->fallbackDisk()->exists($path);
        }
        if ($this->existsCache[$fallbackDiskName][$path] === true) {
            return $this->urlCache[$path] = $this->fallbackDisk()->url($path);
        }

        // Fallback: URL der aktiven Disk, auch wenn die Datei evtl. nicht existiert.
        return $this->urlCache[$path] = $this->activeDisk()->url($path);
    }

    /**
     * Direkter Browser-Zugriff auf S3 (presigned GET) – vermeidet langsames Proxying durch PHP.
     * Nur für Driver „s3“; bei lokaler „public“-Disk null → Aufrufer nutzt Laravel-Stream-Route.
     */
    public function temporaryPlaybackUrlForPath(string $path, \DateTimeInterface $expiration): ?string
    {
        if (! config('media_storage.prefer_presigned_streaming', true)) {
            return null;
        }

        $diskNames = array_values(array_unique(array_filter([
            $this->activeDiskName(),
            $this->fallbackDiskName(),
        ])));

        foreach ($diskNames as $diskName) {
            $driver = (string) config("filesystems.disks.{$diskName}.driver", '');
            if ($driver !== 's3') {
                continue;
            }

            $disk = Storage::disk($diskName);
            if (! $disk->exists($path)) {
                continue;
            }

            try {
                return $disk->temporaryUrl($path, $expiration);
            } catch (\Throwable $e) {
                Log::warning('MediaStorage.temporaryPlaybackUrlForPath', [
                    'disk' => $diskName,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return null;
    }

    public function delete(string $path): bool
    {
        $deleted = false;

        if ($this->activeDisk()->exists($path)) {
            $deleted = $this->activeDisk()->delete($path) || $deleted;
        }

        if ($this->fallbackDiskName() !== $this->activeDiskName() && $this->fallbackDisk()->exists($path)) {
            $deleted = $this->fallbackDisk()->delete($path) || $deleted;
        }

        return $deleted;
    }

    /**
     * @return array{path:string,temporary:bool,disk:string}|null
     */
    public function resolveReadableLocalPath(string $path): ?array
    {
        $activeDiskName = $this->activeDiskName();
        $activeDisk = $this->activeDisk();
        if ($activeDisk->exists($path)) {
            if ($this->isLocalDisk($activeDiskName)) {
                return ['path' => $activeDisk->path($path), 'temporary' => false, 'disk' => $activeDiskName];
            }

            $tempPath = $this->copyDiskFileToTemp($activeDisk, $path);
            if ($tempPath !== null) {
                return ['path' => $tempPath, 'temporary' => true, 'disk' => $activeDiskName];
            }
        }

        $fallbackDiskName = $this->fallbackDiskName();
        if ($fallbackDiskName === $activeDiskName) {
            return null;
        }

        $fallbackDisk = $this->fallbackDisk();
        if (! $fallbackDisk->exists($path)) {
            return null;
        }

        if ($this->isLocalDisk($fallbackDiskName)) {
            return ['path' => $fallbackDisk->path($path), 'temporary' => false, 'disk' => $fallbackDiskName];
        }

        $tempPath = $this->copyDiskFileToTemp($fallbackDisk, $path);

        return $tempPath !== null
            ? ['path' => $tempPath, 'temporary' => true, 'disk' => $fallbackDiskName]
            : null;
    }

    public function cleanupResolvedPath(?array $resolved): void
    {
        if (! is_array($resolved) || empty($resolved['temporary']) || empty($resolved['path'])) {
            return;
        }

        @unlink((string) $resolved['path']);
    }

    public function putFromLocalFile(string $targetPath, string $localPath, array $options = []): bool
    {
        if (! is_file($localPath) || ! is_readable($localPath)) {
            return false;
        }

        $stream = @fopen($localPath, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            return (bool) $this->activeDisk()->put($targetPath, $stream, $options);
        } finally {
            fclose($stream);
        }
    }

    private function isLocalDisk(string $diskName): bool
    {
        return (string) config("filesystems.disks.{$diskName}.driver") === 'local';
    }

    private function buildMediaPath(mixed $news, mixed $media, mixed $file, string $role, bool $derived): string
    {
        $date = $news?->published_at ?? now();
        if (! $date instanceof \DateTimeInterface) {
            $date = now();
        }

        $slug = trim((string) ($news?->slug ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) ($news?->title ?? ''));
        }
        if ($slug === '') {
            $slug = 'news-item';
        }

        $mediaId = $media?->id ? (string) $media->id : uniqid('tmp', false);

        $detectedRole = trim($role);
        if ($detectedRole === '') {
            $detectedRole = $this->defaultRoleForType((string) ($media?->type ?? ''));
        }
        $detectedRole = Str::slug($detectedRole);
        if ($detectedRole === '') {
            $detectedRole = 'media';
        }

        $safeName = $this->safeOriginalBaseName($file);
        $extension = $this->safeExtension($file);

        $base = sprintf(
            'news-media/%s/%s/%s/%s',
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $slug
        );
        if ($derived) {
            $base .= '/derived';
        }

        return sprintf(
            '%s/%s-%s-%s.%s',
            $base,
            $mediaId,
            $detectedRole,
            $safeName,
            $extension
        );
    }

    private function safeOriginalBaseName(mixed $file): string
    {
        $name = '';
        if ($file instanceof UploadedFile) {
            $name = (string) $file->getClientOriginalName();
        } elseif (is_string($file)) {
            $name = $file;
        }

        $base = pathinfo($name, PATHINFO_FILENAME);
        $safe = Str::slug((string) $base);
        if ($safe === '') {
            $safe = 'media';
        }

        return Str::limit($safe, 50, '');
    }

    private function safeExtension(mixed $file): string
    {
        $ext = '';
        if ($file instanceof UploadedFile) {
            $ext = (string) $file->getClientOriginalExtension();
        } elseif (is_string($file)) {
            $ext = (string) pathinfo($file, PATHINFO_EXTENSION);
        }

        $ext = strtolower(trim($ext));
        if ($ext === '' || ! preg_match('/^[a-z0-9]+$/', $ext)) {
            return 'bin';
        }

        return $ext;
    }

    private function defaultRoleForType(string $type): string
    {
        return match ($type) {
            'image' => 'gallery',
            'video' => 'video',
            'audio' => 'audio',
            default => 'media',
        };
    }

    private function copyDiskFileToTemp(Filesystem $disk, string $path): ?string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        $suffix = pathinfo($path, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'media_');
        if ($tempPath === false) {
            fclose($stream);

            return null;
        }

        if ($suffix !== '') {
            $renamed = $tempPath.'.'.$suffix;
            @rename($tempPath, $renamed);
            $tempPath = $renamed;
        }

        $target = @fopen($tempPath, 'wb');
        if ($target === false) {
            fclose($stream);
            @unlink($tempPath);

            return null;
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $tempPath;
    }
}

<?php

namespace App\Services\Publication;

use App\Models\MediaPublicationFinding;
use App\Services\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PublicationFindingManualEvidenceService
{
    public const TYPE_SCREENSHOT_KANAL = 'screenshot_kanal';

    public const TYPE_SCREENSHOT_EINBLENDUNG = 'screenshot_einblendung';

    public const TYPE_YOUTUBE_VIDEO = 'youtube_video';

    public function __construct(
        private readonly MediaStorage $mediaStorage,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_SCREENSHOT_KANAL => 'Screenshot Kanal',
            self::TYPE_SCREENSHOT_EINBLENDUNG => 'Screenshot Einblendung',
            self::TYPE_YOUTUBE_VIDEO => 'YouTube-Video (manuell)',
        ];
    }

    public function store(MediaPublicationFinding $finding, string $type, UploadedFile $file): void
    {
        if (! array_key_exists($type, self::typeLabels())) {
            throw new \InvalidArgumentException('Unbekannter Beweismittel-Typ.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $allowed = match ($type) {
            self::TYPE_YOUTUBE_VIDEO => ['mp4', 'webm', 'mkv'],
            default => ['jpg', 'jpeg', 'png', 'webp'],
        };

        if (! in_array($extension, $allowed, true)) {
            throw new \InvalidArgumentException('Dateityp für diesen Upload nicht erlaubt.');
        }

        $base = trim((string) config('publication_evidence.storage_directory'), '/')
            .'/'.$finding->id.'/manual/'.$type.'.'.$extension;

        $this->deleteStoredFile($finding, $type);

        $tempPath = $file->getRealPath();
        if (! is_string($tempPath) || $tempPath === '') {
            throw new \RuntimeException('Upload konnte nicht gelesen werden.');
        }

        $mime = $file->getMimeType() ?: match ($type) {
            self::TYPE_YOUTUBE_VIDEO => 'video/mp4',
            default => 'image/png',
        };

        if (! $this->mediaStorage->putFromLocalFile($base, $tempPath, [
            'visibility' => 'private',
            'ContentType' => $mime,
        ])) {
            throw new \RuntimeException('Speichern auf S3 fehlgeschlagen.');
        }

        $manual = (array) ($finding->evidence_manual_files ?? []);
        $manual[$type] = [
            'path' => $base,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => Auth::id(),
        ];

        $finding->update(['evidence_manual_files' => $manual]);
    }

    public function delete(MediaPublicationFinding $finding, string $type): void
    {
        $this->deleteStoredFile($finding, $type);

        $manual = (array) ($finding->evidence_manual_files ?? []);
        unset($manual[$type]);
        $finding->update(['evidence_manual_files' => $manual === [] ? null : $manual]);
    }

    public function zipBasename(string $type): string
    {
        return match ($type) {
            self::TYPE_SCREENSHOT_KANAL => '07-screenshot-kanal.png',
            self::TYPE_SCREENSHOT_EINBLENDUNG => '06-screenshot-einblendung.png',
            self::TYPE_YOUTUBE_VIDEO => '10-youtube-video-manuell.mp4',
            default => 'manual-'.Str::slug($type).'.bin',
        };
    }

    public function checklistKey(string $type): string
    {
        return match ($type) {
            self::TYPE_SCREENSHOT_KANAL => 'screenshot_kanal',
            self::TYPE_SCREENSHOT_EINBLENDUNG => 'screenshot_einblendung',
            self::TYPE_YOUTUBE_VIDEO => 'youtube_video_lokal',
            default => $type,
        };
    }

    private function deleteStoredFile(MediaPublicationFinding $finding, string $type): void
    {
        $manual = (array) ($finding->evidence_manual_files ?? []);
        $path = (string) ($manual[$type]['path'] ?? '');
        if ($path !== '' && $this->mediaStorage->exists($path)) {
            $this->mediaStorage->delete($path);
        }
    }
}

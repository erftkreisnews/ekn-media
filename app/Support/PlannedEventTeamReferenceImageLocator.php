<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PlannedEventTeamReferenceImageLocator
{
    private const PREFIX = 'planned-event-team-references/';

    public static function pathFor(int $plannedEventId, int $startNumber): string
    {
        return self::PREFIX.$plannedEventId.'/'.$startNumber.'.jpg';
    }

    public static function isReferencePath(string $path): bool
    {
        return str_starts_with($path, self::PREFIX);
    }

    public static function exists(string $relativePath): bool
    {
        return Storage::disk('local')->exists($relativePath);
    }

    public static function absolutePath(string $relativePath): ?string
    {
        if (! self::exists($relativePath)) {
            return null;
        }

        return Storage::disk('local')->path($relativePath);
    }

    public static function delete(string $relativePath): void
    {
        Storage::disk('local')->delete($relativePath);
    }

    public static function store(int $plannedEventId, int $startNumber, string $binary): string
    {
        $path = self::pathFor($plannedEventId, $startNumber);
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public static function dataUrl(string $relativePath): ?string
    {
        $absolute = self::absolutePath($relativePath);
        if ($absolute === null || ! is_readable($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
    }

    /** @return \Symfony\Component\HttpFoundation\StreamedResponse|null */
    public static function download(string $relativePath, string $downloadName)
    {
        if (! self::exists($relativePath)) {
            return null;
        }

        return Storage::disk('local')->download($relativePath, $downloadName);
    }
}

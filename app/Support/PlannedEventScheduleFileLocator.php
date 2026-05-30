<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PlannedEventScheduleFileLocator
{
    private const PREFIX = 'planned-event-schedules/';

    public static function isSchedulePath(string $path): bool
    {
        return str_starts_with($path, self::PREFIX);
    }

    public static function exists(string $relativePath): bool
    {
        if (Storage::disk('local')->exists($relativePath)) {
            return true;
        }

        if (self::isSchedulePath($relativePath) && Storage::disk('planned_schedule_legacy')->exists($relativePath)) {
            return true;
        }

        return false;
    }

    public static function absolutePath(string $relativePath): ?string
    {
        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        if (self::isSchedulePath($relativePath) && Storage::disk('planned_schedule_legacy')->exists($relativePath)) {
            return Storage::disk('planned_schedule_legacy')->path($relativePath);
        }

        return null;
    }

    /** @return \Symfony\Component\HttpFoundation\StreamedResponse|null */
    public static function download(string $relativePath, string $downloadName)
    {
        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->download($relativePath, $downloadName);
        }

        if (self::isSchedulePath($relativePath) && Storage::disk('planned_schedule_legacy')->exists($relativePath)) {
            return Storage::disk('planned_schedule_legacy')->download($relativePath, $downloadName);
        }

        return null;
    }

    public static function delete(string $relativePath): void
    {
        Storage::disk('local')->delete($relativePath);
        if (self::isSchedulePath($relativePath)) {
            Storage::disk('planned_schedule_legacy')->delete($relativePath);
        }
    }
}

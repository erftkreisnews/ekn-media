<?php

namespace App\Services\Ingest;

/**
 * Zentrale Auflösung des Ingest-Inbox-Pfads (keine verteilte Pfadlogik im Code).
 */
final class IngestPathResolver
{
    public const SOURCE_EXPLICIT_INBOX = 'explicit_inbox';

    public const SOURCE_ROOT_UPLOAD = 'root_upload';

    public const SOURCE_DEFAULT_STORAGE = 'default_storage';

    /**
     * @return array{path: string, source: string}
     */
    public static function resolveInboxPath(?string $explicitInbox, ?string $ingestRootPath, string $defaultInboxPath): array
    {
        if (self::isNonEmptyString($explicitInbox)) {
            return ['path' => $explicitInbox, 'source' => self::SOURCE_EXPLICIT_INBOX];
        }

        if (self::isNonEmptyString($ingestRootPath)) {
            $base = rtrim($ingestRootPath, '/\\');

            return ['path' => $base.DIRECTORY_SEPARATOR.'upload', 'source' => self::SOURCE_ROOT_UPLOAD];
        }

        return ['path' => $defaultInboxPath, 'source' => self::SOURCE_DEFAULT_STORAGE];
    }

    private static function isNonEmptyString(?string $v): bool
    {
        return $v !== null && trim($v) !== '';
    }
}

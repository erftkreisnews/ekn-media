<?php

namespace Tests\Unit;

use App\Services\Ingest\IngestPathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IngestPathResolverTest extends TestCase
{
    public function test_explicit_inbox_wins_over_root(): void
    {
        $r = IngestPathResolver::resolveInboxPath(
            '/data/explicit/inbox',
            '/data/root',
            '/default/inbox'
        );

        $this->assertSame('/data/explicit/inbox', $r['path']);
        $this->assertSame(IngestPathResolver::SOURCE_EXPLICIT_INBOX, $r['source']);
    }

    public function test_root_derives_upload_subdirectory(): void
    {
        $r = IngestPathResolver::resolveInboxPath(
            null,
            '/var/www/laravel12',
            '/default/inbox'
        );

        $this->assertSame(IngestPathResolver::SOURCE_ROOT_UPLOAD, $r['source']);
        $this->assertStringEndsWith('upload', $r['path']);
        $this->assertStringContainsString('laravel12', $r['path']);
    }

    #[DataProvider('whitespaceExplicitIgnoredProvider')]
    public function test_whitespace_only_explicit_falls_through(?string $explicit, ?string $root, string $expectedSource): void
    {
        $r = IngestPathResolver::resolveInboxPath($explicit, $root, '/default/inbox');

        $this->assertSame($expectedSource, $r['source']);
    }

    /**
     * @return list<array{0: ?string, 1: ?string, 2: string}>
     */
    public static function whitespaceExplicitIgnoredProvider(): array
    {
        return [
            ['   ', null, IngestPathResolver::SOURCE_DEFAULT_STORAGE],
            ['', '/r', IngestPathResolver::SOURCE_ROOT_UPLOAD],
        ];
    }

    public function test_neither_explicit_nor_root_uses_default(): void
    {
        $r = IngestPathResolver::resolveInboxPath(null, null, '/storage/app/ingest/inbox');

        $this->assertSame('/storage/app/ingest/inbox', $r['path']);
        $this->assertSame(IngestPathResolver::SOURCE_DEFAULT_STORAGE, $r['source']);
    }

    public function test_ingest_config_wires_paths_inbox_and_source(): void
    {
        $this->assertNotEmpty(config('ingest.paths.inbox'));
        $this->assertContains(config('ingest.inbox_path_source'), [
            IngestPathResolver::SOURCE_EXPLICIT_INBOX,
            IngestPathResolver::SOURCE_ROOT_UPLOAD,
            IngestPathResolver::SOURCE_DEFAULT_STORAGE,
        ]);
    }
}

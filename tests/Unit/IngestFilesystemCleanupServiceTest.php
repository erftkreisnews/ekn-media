<?php

namespace Tests\Unit;

use App\Services\Ingest\IngestFilesystemCleanupService;
use Tests\TestCase;

class IngestFilesystemCleanupServiceTest extends TestCase
{
    public function test_prune_deletes_only_old_top_level_files(): void
    {
        $base = sys_get_temp_dir().'/ingest_cleanup_ut_'.uniqid('', true);
        mkdir($base, 0777, true);

        $old = $base.'/old.bin';
        file_put_contents($old, 'x');
        touch($old, time() - 86400 * 2);

        $recent = $base.'/recent.bin';
        file_put_contents($recent, 'y');
        touch($recent, time() - 3600);

        $nested = $base.'/nested';
        mkdir($nested, 0777, true);
        $nestedFile = $nested.'/stale.bin';
        file_put_contents($nestedFile, 'z');
        touch($nestedFile, time() - 86400 * 3);

        $svc = new IngestFilesystemCleanupService;
        $removed = $svc->pruneTopLevelFilesOlderThanHours($base, 24);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertFileExists($nestedFile);

        @unlink($recent);
        @unlink($nestedFile);
        @rmdir($nested);
        @rmdir($base);
    }

    public function test_prune_respects_zero_hours(): void
    {
        $base = sys_get_temp_dir().'/ingest_cleanup_ut2_'.uniqid('', true);
        mkdir($base, 0777, true);
        $f = $base.'/a.bin';
        file_put_contents($f, 'x');
        touch($f, time() - 86400 * 10);

        $svc = new IngestFilesystemCleanupService;
        $this->assertSame(0, $svc->pruneTopLevelFilesOlderThanHours($base, 0));
        $this->assertFileExists($f);

        @unlink($f);
        @rmdir($base);
    }
}

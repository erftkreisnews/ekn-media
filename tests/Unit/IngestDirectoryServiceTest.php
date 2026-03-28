<?php

namespace Tests\Unit;

use App\Services\Ingest\IngestDirectoryService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class IngestDirectoryServiceTest extends TestCase
{
    public function test_managed_file_must_be_under_configured_roots(): void
    {
        $base = sys_get_temp_dir().'/ingest_ut_'.uniqid('', true);
        mkdir($base.'/inbox', 0777, true);
        $file = $base.'/inbox/clip.mp4';
        file_put_contents($file, 'x');

        Config::set('ingest.paths', [
            'inbox' => $base.'/inbox',
            'processing' => $base.'/processing',
            'rendered' => $base.'/rendered',
            'archive' => $base.'/archive',
            'failed' => $base.'/failed',
            'tmp' => $base.'/tmp',
        ]);

        $svc = app(IngestDirectoryService::class);

        $this->assertTrue($svc->isManagedAbsoluteFile($file));
        $this->assertFalse($svc->isManagedAbsoluteFile('/etc/passwd'));
    }
}

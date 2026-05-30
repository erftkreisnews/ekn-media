<?php

namespace Tests\Unit;

use App\Services\ImageExifDetailsReader;
use Tests\TestCase;

class ImageExifDetailsReaderTest extends TestCase
{
    public function test_returns_empty_for_missing_file(): void
    {
        $this->assertSame([], ImageExifDetailsReader::read('/nonexistent/file.jpg'));
    }

    public function test_returns_empty_for_unsupported_extension(): void
    {
        $path = sys_get_temp_dir().'/exif-test-'.uniqid().'.png';
        file_put_contents($path, 'not-a-real-png');
        try {
            $this->assertSame([], ImageExifDetailsReader::read($path));
        } finally {
            @unlink($path);
        }
    }
}

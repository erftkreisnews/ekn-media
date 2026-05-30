<?php

namespace Tests\Unit;

use App\Models\IngestFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestFileImageTest extends TestCase
{
    #[Test]
    public function detects_jpeg_by_extension(): void
    {
        $f = new IngestFile(['original_name' => 'Foto.JPG']);
        $this->assertTrue($f->isIngestImageFile());
        $this->assertFalse($f->isIngestVideoCandidate());
    }

    #[Test]
    public function video_candidate_for_mp4(): void
    {
        $f = new IngestFile(['original_name' => 'clip.mp4', 'mime' => 'video/mp4']);
        $this->assertFalse($f->isIngestImageFile());
        $this->assertTrue($f->isIngestVideoFile());
        $this->assertTrue($f->isIngestVideoCandidate());
        $this->assertTrue($f->isBrowserPlayableOriginal());
    }

    #[Test]
    public function video_candidate_for_mxf_and_mov(): void
    {
        $mxf = new IngestFile(['original_name' => 'cam_a.mxf', 'mime' => 'application/mxf']);
        $this->assertTrue($mxf->isIngestVideoFile());
        $this->assertTrue($mxf->isIngestVideoCandidate());
        $this->assertFalse($mxf->isBrowserPlayableOriginal());

        $mov = new IngestFile(['original_name' => 'clip.MOV', 'mime' => 'video/quicktime']);
        $this->assertTrue($mov->isIngestVideoFile());
        $this->assertFalse($mov->isBrowserPlayableOriginal());
    }
}

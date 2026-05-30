<?php

namespace Tests\Unit;

use App\Services\PdfScheduleTextExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfScheduleTextExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_text_from_simple_generated_pdf(): void
    {
        Storage::fake('local');

        $binary = Pdf::loadHTML('<html><body><p>Qualifying 14:30 Uhr Nürburgring</p></body></html>')->output();
        Storage::disk('local')->put('fixtures/test-schedule.pdf', $binary);

        $extractor = new PdfScheduleTextExtractor;
        $result = $extractor->extractFromLocalDisk('fixtures/test-schedule.pdf');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Qualifying', $result['text']);
        $this->assertStringContainsString('14:30', $result['text']);
    }
}

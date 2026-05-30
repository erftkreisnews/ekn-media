<?php

namespace Tests\Unit;

use App\Services\Presseportal\PresseportalBodyNormalizer;
use PHPUnit\Framework\TestCase;

class PresseportalBodyNormalizerTest extends TestCase
{
    public function test_collapse_ots_removes_line_breaks_after_ots_dash(): void
    {
        $in = "Bonn (ots) - \n\n\nIn der Nacht zu Montag kam es zu einer Explosion.";
        $out = PresseportalBodyNormalizer::collapseOtsDashWhitespace($in);
        $this->assertSame('Bonn (ots) - In der Nacht zu Montag kam es zu einer Explosion.', $out);
    }

    public function test_collapse_ots_keeps_single_space_after_dash(): void
    {
        $in = "Köln (ots) -\t  Text folgt.";
        $out = PresseportalBodyNormalizer::collapseOtsDashWhitespace($in);
        $this->assertSame('Köln (ots) - Text folgt.', $out);
    }

    public function test_strip_bonn_polizei_url_and_preserves_original_content_line(): void
    {
        $in = 'Ermittlungen dauern an. Rückfragen: https://bonn.polizei.nrw Folgen Sie WhatsApp. Original-Content von: Polizei Bonn, übermittelt durch news aktuell.';
        $out = PresseportalBodyNormalizer::stripBonnPolizeiNrwTail($in, true);
        $this->assertSame(
            "Ermittlungen dauern an. Rückfragen:\n\nOriginal-Content von: Polizei Bonn, übermittelt durch news aktuell.",
            $out
        );
    }

    public function test_strip_bonn_polizei_without_original_content_line(): void
    {
        $in = 'Text. https://bonn.polizei.nrw/mehr';
        $out = PresseportalBodyNormalizer::stripBonnPolizeiNrwTail($in, true);
        $this->assertSame('Text.', $out);
    }

    public function test_normalize_runs_ots_then_strip(): void
    {
        $in = "Bonn (ots) -\n\nhttps://bonn.polizei.nrw/x Original-Content von: Polizei Bonn, übermittelt durch news aktuell.";
        $out = PresseportalBodyNormalizer::normalize($in);
        $this->assertSame(
            "Bonn (ots) -\n\nOriginal-Content von: Polizei Bonn, übermittelt durch news aktuell.",
            $out
        );
    }
}

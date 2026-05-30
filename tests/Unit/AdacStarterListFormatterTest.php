<?php

namespace Tests\Unit;

use App\Services\AdacStarterListFormatter;
use PHPUnit\Framework\TestCase;

class AdacStarterListFormatterTest extends TestCase
{
    public function test_formats_double_number_rows(): void
    {
        $raw = "3 9 Mercedes-AMG Team Verstappen Racing SP 9 PRO\nMax Verstappen (NLD)\n\n4 10 Other SP9\nJane";

        $f = new AdacStarterListFormatter;
        $out = $f->format($raw, 'Kopf');

        $this->assertStringContainsString('Kopf', $out);
        // PDF: erste Zahl = Startnummer, zweite = Box
        $this->assertStringContainsString('Box 9 | Startnr. 3 | Mercedes-AMG Team Verstappen Racing SP 9 PRO', $out);
        $this->assertStringContainsString('Box 10 | Startnr. 4 | Other SP9', $out);
    }

    public function test_formats_pdf_style_block_with_header_and_driver_vehicle_split_lines(): void
    {
        $raw = <<<'TXT'
# Box Team / Fahrer Ort / Nation Klasse / Fahrzeug
3 9 Mercedes-AMG Team Verstappen Racing SP 9 PRO
Max Verstappen Swalmen (NLD) Mercedes-AMG
Lucas Auer Kufstein (AUT) GT3
TXT;

        $f = new AdacStarterListFormatter;
        $out = $f->format($raw);

        $this->assertStringContainsString('Box 9 | Startnr. 3 | Mercedes-AMG Team Verstappen Racing SP 9 PRO', $out);
        $this->assertStringContainsString('Fahrer / Fahrzeugdetails:', $out);
        $this->assertStringContainsString('Max Verstappen Swalmen (NLD) Mercedes-AMG', $out);
        $this->assertStringContainsString('Lucas Auer Kufstein (AUT) GT3', $out);
    }
}

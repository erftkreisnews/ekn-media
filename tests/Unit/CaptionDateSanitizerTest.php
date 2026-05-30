<?php

namespace Tests\Unit;

use App\Support\CaptionDateSanitizer;
use Tests\TestCase;

class CaptionDateSanitizerTest extends TestCase
{
    public function test_it_removes_disallowed_system_date_from_caption(): void
    {
        $caption = 'Mercedes-AMG Team Verstappen Racing (#3) im Rennen. Nürburg, 20.04.2026.';

        $sanitized = CaptionDateSanitizer::sanitize($caption, ['19.04.2026']);

        $this->assertSame('Mercedes-AMG Team Verstappen Racing (#3) im Rennen. Nürburg.', $sanitized);
    }

    public function test_it_keeps_allowed_capture_date_in_caption(): void
    {
        $caption = 'Manthey Porsche SP 9 PRO (#911) im Vordergrund. Nürburg, 19.04.2026.';

        $sanitized = CaptionDateSanitizer::sanitize($caption, ['19.04.2026']);

        $this->assertSame($caption, $sanitized);
    }

    public function test_it_removes_all_dates_when_no_capture_date_is_allowed(): void
    {
        $caption = 'Fahrzeug im Rennen. Nürburg, 19.04.2026.';

        $sanitized = CaptionDateSanitizer::sanitize($caption, []);

        $this->assertSame('Fahrzeug im Rennen. Nürburg.', $sanitized);
    }
}

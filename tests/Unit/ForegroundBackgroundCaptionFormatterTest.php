<?php

namespace Tests\Unit;

use App\Support\ForegroundBackgroundCaptionFormatter;
use Tests\TestCase;

class ForegroundBackgroundCaptionFormatterTest extends TestCase
{
    public function test_compose_preserves_existing_caption_when_both_subjects_present(): void
    {
        $existing = 'Rennszene auf der Strecke. Nürburg, 20.04.2026.';

        $caption = ForegroundBackgroundCaptionFormatter::compose(
            $existing,
            'Manthey Porsche SP 9 PRO (#911)',
            'Mercedes-AMG Team Verstappen Racing (#3)'
        );

        $this->assertSame(
            'Rennszene auf der Strecke. Nürburg, 20.04.2026.',
            $caption
        );
    }

    public function test_compose_returns_existing_when_subjects_missing(): void
    {
        $existing = 'Neutraler Text.';

        $caption = ForegroundBackgroundCaptionFormatter::compose($existing, '', 'Fahrzeug B');

        $this->assertSame('Neutraler Text.', $caption);
    }

    public function test_compose_handles_plural_subjects_without_grammar_errors(): void
    {
        $caption = ForegroundBackgroundCaptionFormatter::compose(
            '',
            'zwei Mitarbeiter des Kampfmittelbeseitigungsdienstes',
            'Einsatzfahrzeug mit Ausrüstung'
        );

        $this->assertSame(
            'zwei Mitarbeiter des Kampfmittelbeseitigungsdienstes, dahinter Einsatzfahrzeug mit Ausrüstung.',
            $caption
        );
    }
}

<?php

namespace Tests\Unit;

use App\Support\RaceStartNumberSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RaceStartNumberSanitizerTest extends TestCase
{
    private RaceStartNumberSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new RaceStartNumberSanitizer;
    }

    #[DataProvider('marshalDisplayProvider')]
    public function test_it_ignores_marshal_led_windshield_numbers(int $number, string $text): void
    {
        $this->assertTrue($this->sanitizer->shouldIgnoreAsMarshalDisplay($number, $text));
    }

    public static function marshalDisplayProvider(): array
    {
        return [
            'three digit led on windshield' => [
                58,
                'Mercedes-AMG GT3 mit sichtbarer Nummer 058 auf nasser Strecke, grün beleuchteter Kühlergrill.',
            ],
            'explicit windshield led' => [
                12,
                'Die LED-Anzeige an der Windschutzscheibe zeigt 12, die Renn-Startnummer am Kotflügel ist nicht sichtbar.',
            ],
            'marshal display' => [
                7,
                'Marshal-Display an der Frontscheibe mit der Ziffer 7.',
            ],
        ];
    }

    #[DataProvider('bodyNumberProvider')]
    public function test_it_keeps_body_race_numbers(int $number, string $text): void
    {
        $this->assertFalse($this->sanitizer->shouldIgnoreAsMarshalDisplay($number, $text));
    }

    public static function bodyNumberProvider(): array
    {
        return [
            'door number' => [
                524,
                'Toyota GR Supra mit Startnummer 524 am Kotflügel.',
            ],
            'hash caption' => [
                54,
                'Porsche 911 GT3 R (#54) von Dinamic GT auf der Nordschleife.',
            ],
            'both marshal hint and body number' => [
                26,
                'LED-Anzeige 026 an der Scheibe, große Startnummer 26 auf der Tür.',
            ],
        ];
    }

    public function test_sanitize_vision_result_strips_marshal_only_detection(): void
    {
        $result = $this->sanitizer->sanitizeVisionResult([
            'caption' => 'Rennwagen mit beleuchteter Nummer 058 an der Windschutzscheibe.',
            'detected_start_number' => 58,
            'detected_car_numbers' => [58],
            'number_readability' => 'high',
            'needs_review' => [],
        ]);

        $this->assertNull($result['detected_start_number']);
        $this->assertSame([], $result['detected_car_numbers']);
        $this->assertNull($result['number_readability']);
        $this->assertSame('', $result['caption']);
    }

    public function test_strip_marshal_mentions_from_caption_keeps_race_content(): void
    {
        $caption = 'Mercedes-AMG Team RAVENOL (#80) SP 9. Ein orange GT3 fährt auf nasser Strecke, mit einer Marshal-Anzeige an der Frontscheibe.';
        $stripped = $this->sanitizer->stripMarshalMentionsFromCaption($caption);

        $this->assertStringContainsString('Mercedes-AMG Team RAVENOL (#80)', $stripped);
        $this->assertStringNotContainsString('Marshal', $stripped);
        $this->assertStringNotContainsString('Frontscheibe', $stripped);
    }

    public function test_is_body_start_number_clearly_visible_requires_body_mention_or_high_readability(): void
    {
        $this->assertFalse($this->sanitizer->isBodyStartNumberClearlyVisible(null, []));
        $this->assertFalse($this->sanitizer->isBodyStartNumberClearlyVisible(175, [
            'caption' => 'Orangefarbener Mercedes auf nasser Strecke.',
            'number_readability' => 'low',
        ]));
        $this->assertTrue($this->sanitizer->isBodyStartNumberClearlyVisible(524, [
            'caption' => 'Toyota mit Startnummer 524 am Kotflügel.',
            'number_readability' => 'medium',
        ]));
        $this->assertTrue($this->sanitizer->isBodyStartNumberClearlyVisible(54, [
            'caption' => 'Porsche auf der Strecke.',
            'number_readability' => 'high',
        ]));
    }

    public function test_clear_detected_start_numbers(): void
    {
        $cleared = $this->sanitizer->clearDetectedStartNumbers([
            'detected_start_number' => 58,
            'detected_car_numbers' => [58],
            'number_readability' => 'high',
        ]);

        $this->assertNull($cleared['detected_start_number']);
        $this->assertSame([], $cleared['detected_car_numbers']);
        $this->assertNull($cleared['number_readability']);
    }

    public function test_strip_marshal_mentions_removes_standalone_sentence(): void
    {
        $caption = 'Ein Porsche fährt eine Kurve. Die LED-Marshal-Anzeige an der Frontscheibe zeigt die Zahl 058. Nürburgring, 14.05.2026.';
        $stripped = $this->sanitizer->stripMarshalMentionsFromCaption($caption);

        $this->assertStringContainsString('Ein Porsche fährt eine Kurve.', $stripped);
        $this->assertStringNotContainsString('058', $stripped);
        $this->assertStringNotContainsString('Marshal', $stripped);
        $this->assertStringContainsString('Nürburgring', $stripped);
    }
}

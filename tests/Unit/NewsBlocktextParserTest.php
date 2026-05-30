<?php

namespace Tests\Unit;

use App\Support\NewsBlocktextParser;
use Tests\TestCase;

class NewsBlocktextParserTest extends TestCase
{
    public function test_parse_splits_sample_block(): void
    {
        $raw = <<<'TXT'
Titel: Kurz

Dachzeile: Dach

Webtext: Fliesstext Absatz eins.

Schlagwörter: a, b

Metadaten:
Bundesland: NRW
Stadt: Köln
Status: Entwurf
Veröffentlichungsdatum: sofort
Byline: Redaktion
TXT;

        $p = NewsBlocktextParser::parse($raw);
        $this->assertSame('Kurz', $p['title']);
        $this->assertSame('Dach', $p['teaser']);
        $this->assertStringContainsString('Fliesstext', $p['body']);
        $this->assertSame('NRW', $p['federal_state']);
        $this->assertSame('Entwurf', $p['status']);

        $f = NewsBlocktextParser::toFormFields($p);
        $this->assertSame('draft', $f['status']);
        $this->assertArrayHasKey('published_at', $f);
        $this->assertSame('Redaktion', $f['author_credit']);
    }

    public function test_parse_accepts_fullwidth_colon_and_web_space_text(): void
    {
        $raw = "Titel： Kurz\n\nWeb Text： Nur hier der Text.\n";
        $p = NewsBlocktextParser::parse($raw);
        $this->assertSame('Kurz', $p['title']);
        $this->assertSame('Nur hier der Text.', $p['body']);
    }

    public function test_looks_structured_requires_titel_and_marker(): void
    {
        $this->assertFalse(NewsBlocktextParser::looksStructured('nur etwas text'));
        $this->assertFalse(NewsBlocktextParser::looksStructured("Titel: x\n\nkein weiterer marker hier lang genug für die mindestlänge"));
        $this->assertTrue(NewsBlocktextParser::looksStructured("Titel: ein etwas längerer titeltext\n\nDachzeile: und eine dachzeile dazu"));
        $this->assertTrue(NewsBlocktextParser::looksStructured("Titel: Kurz\n\nWebtext: Nur der Fließtextabschnitt."));
    }

    public function test_parse_accepts_markdown_bold_around_labels(): void
    {
        $raw = <<<'TXT'
**Titel:** Kurztitel

**Dachzeile:** Dach

**Webtext:** Nur der Fließtext.

**Schlagwörter:** a, b

**Bundesland:** NRW
**Stadt:** Wesseling
**Status:** Entwurf
**Veröffentlichungsdatum:** sofort
**Byline:** Redaktion
TXT;

        $this->assertTrue(NewsBlocktextParser::looksStructured($raw));
        $p = NewsBlocktextParser::parse($raw);
        $this->assertSame('Kurztitel', $p['title']);
        $this->assertSame('Dach', $p['teaser']);
        $this->assertSame('Nur der Fließtext.', $p['body']);
        $this->assertSame('a, b', $p['keywords']);
        $this->assertSame('NRW', $p['federal_state']);
        $this->assertSame('Wesseling', $p['city']);
        $this->assertSame('Entwurf', $p['status']);
        $this->assertSame('Redaktion', $p['author_credit']);
    }
}

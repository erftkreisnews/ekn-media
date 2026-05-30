<?php

namespace Tests\Unit;

use App\Services\Presseportal\PresseportalStoryService;
use PHPUnit\Framework\TestCase;

class PresseportalStoryServiceTest extends TestCase
{
    public function test_parse_url_extracts_office_and_story_id(): void
    {
        $s = new PresseportalStoryService;
        $parsed = $s->parseUrl('https://www.presseportal.de/blaulicht/pm/7304/6245919');
        $this->assertNotNull($parsed);
        $this->assertSame(7304, $parsed['office_id']);
        $this->assertSame(6245919, $parsed['story_id']);
    }

    public function test_parse_url_accepts_ch_domain(): void
    {
        $s = new PresseportalStoryService;
        $parsed = $s->parseUrl('https://www.presseportal.ch/foo/pm/1/2');
        $this->assertNotNull($parsed);
        $this->assertSame(1, $parsed['office_id']);
        $this->assertSame(2, $parsed['story_id']);
    }

    public function test_parse_url_rejects_non_presseportal(): void
    {
        $s = new PresseportalStoryService;
        $this->assertNull($s->parseUrl('https://example.com/pm/1/2'));
    }
}

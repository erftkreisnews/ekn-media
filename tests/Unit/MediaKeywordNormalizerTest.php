<?php

namespace Tests\Unit;

use App\Support\MediaKeywordNormalizer;
use Tests\TestCase;

class MediaKeywordNormalizerTest extends TestCase
{
    public function test_it_normalizes_start_number_tokens_to_hashtag_format(): void
    {
        $year = date('Y');
        $raw = 'Mercedes-AMG, Startnummer 3, Startnr. 3, # 3, GT3';

        $normalized = MediaKeywordNormalizer::normalizeCommaSeparated($raw);

        $this->assertSame([$year, 'Mercedes-AMG', '#3', 'GT3'], $normalized);
    }

    public function test_it_deduplicates_case_insensitive_and_trims_spaces(): void
    {
        $year = date('Y');
        $raw = '  Nürburgring  , nürburgring,  ADAC 24h  Nürburgring  ,  #3 ';

        $normalized = MediaKeywordNormalizer::normalizeCommaSeparated($raw);

        $this->assertSame([$year, 'Nürburgring', 'ADAC 24h Nürburgring', '#3'], $normalized);
    }

    public function test_it_keeps_year_only_once_when_already_present(): void
    {
        $year = date('Y');
        $raw = $year.', Nürburgring, GT3';

        $normalized = MediaKeywordNormalizer::normalizeCommaSeparated($raw);

        $this->assertSame([$year, 'Nürburgring', 'GT3'], $normalized);
    }
}

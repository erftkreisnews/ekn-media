<?php

namespace Tests\Unit;

use App\Support\ImportTextNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportTextNormalizerTest extends TestCase
{
    #[Test]
    public function it_replaces_backspace_with_space(): void
    {
        $this->assertSame(
            'Kevin Estre Heilbronn',
            ImportTextNormalizer::normalize("Kevin Estre\x08Heilbronn")
        );
    }

    #[Test]
    public function it_removes_soft_hyphen_and_zero_width_chars(): void
    {
        $raw = 'Heil'."\xC2\xAD"."\u{200B}".'bronn';

        $this->assertSame('Heilbronn', ImportTextNormalizer::normalize($raw));
    }
}

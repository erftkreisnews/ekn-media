<?php

namespace Tests\Unit;

use App\Services\Ingest\IngestStereoPanFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestStereoPanFilterTest extends TestCase
{
    #[Test]
    public function quad_source_maps_oton_left_and_atmo_mix_right(): void
    {
        config([
            'ingest.output.stereo_broadcast_map' => true,
            'ingest.output.stereo_oton_channel_index' => 0,
            'ingest.output.stereo_atmo_pan_expression' => '0.25*c1+0.25*c2+0.5*c3',
        ]);

        $filters = IngestStereoPanFilter::preFormatFilters(4, 2);

        $this->assertSame(['pan=stereo|c0=c0|c1=0.25*c1+0.25*c2+0.5*c3'], $filters);
    }

    #[Test]
    public function stereo_source_skips_pan_when_broadcast_map_enabled(): void
    {
        config(['ingest.output.stereo_broadcast_map' => true]);

        $this->assertSame([], IngestStereoPanFilter::preFormatFilters(2, 2));
    }

    #[Test]
    public function mono_source_puts_oton_on_left_only(): void
    {
        config(['ingest.output.stereo_broadcast_map' => true]);

        $this->assertSame(['pan=stereo|c0=c0|c1=0*c0'], IngestStereoPanFilter::preFormatFilters(1, 2));
    }

    #[Test]
    public function build_chain_ends_with_stereo_aformat(): void
    {
        config(['ingest.output.stereo_broadcast_map' => true]);

        $chain = IngestStereoPanFilter::buildAudioFilterChain(4, 2, 48000);

        $this->assertStringContainsString('pan=stereo|c0=c0', $chain);
        $this->assertStringContainsString('channel_layouts=stereo:sample_rates=48000', $chain);
    }
}

<?php

namespace Tests\Unit;

use App\Services\PlannedEvents\Adac24hParticipantListFetcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Adac24hParticipantListFetcherTest extends TestCase
{
    #[Test]
    public function test_parses_participant_rows_from_html_fixture(): void
    {
        $html = <<<'HTML'
        <tr class="teilnehmerliste SP-9 alle">
            <td class="text-center mobhide stnr">1</td>
            <td class="text-center mobshow stnr"><b>1</b><br /><small>SP 9</small></td>
            <td class="text-center mobhide">SP 9</td>
            <td class="text-center mobhide">16</td>
            <td><b>ROWE RACING</b><br />Farfus, Augusto <span class="mobhide">(Monaco, Brasilien)</span><br />Marciello, Raffaele <span class="mobhide">(Breganzano, Schweiz)</span></td>
            <td class="mobhide">BMW<br />M4 GT3 EVO</td>
            <td class="p-0 text-right"><img src="/wp-content/uploads/teilnehmer_26h/small/1.jpg" alt="#1" /></td>
        </tr>
        HTML;

        $fetcher = new Adac24hParticipantListFetcher;
        $entries = $fetcher->parseHtml($html);

        $this->assertCount(1, $entries);
        $this->assertSame(1, $entries[0]['start_number']);
        $this->assertSame('SP 9', $entries[0]['class']);
        $this->assertSame('16', $entries[0]['box']);
        $this->assertSame('ROWE RACING', $entries[0]['team']);
        $this->assertSame('BMW M4 GT3 EVO', $entries[0]['vehicle']);
        $this->assertStringContainsString('teilnehmer_26h/small/1.jpg', $entries[0]['image_url']);
        $this->assertStringContainsString('Farfus, Augusto', implode('; ', $entries[0]['drivers']));

        $name = $fetcher->formatTeamName($entries[0]);
        $this->assertStringContainsString('Box 16 | Startnr. 1 | ROWE RACING', $name);

        $notes = $fetcher->formatTeamNotes($entries[0]);
        $this->assertStringContainsString('Fahrer / Fahrzeugdetails:', $notes);
        $this->assertStringContainsString('Fahrzeug: BMW M4 GT3 EVO', $notes);
    }
}

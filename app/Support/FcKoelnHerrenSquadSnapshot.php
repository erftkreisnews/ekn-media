<?php

namespace App\Support;

use App\Models\Squad;
use App\Models\SquadPlayer;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot des Kaders 1. FC Köln Herren (Saison 2025/2026), manuell aus öffentlich einsehbarer Quelle übernommen:
 * https://sportdaten.11freunde.de/verein/te9/1-fc-koeln/kader/ (Angabe: Daten von Heimspiel).
 *
 * Bei Wechseln im Kader: im Admin unter Haus-Kader anpassen oder Seeder mit Ersetzen erneut ausführen.
 */
final class FcKoelnHerrenSquadSnapshot
{
    /**
     * @return list<array{shirt_number: ?string, full_name: string, position_label: string}>
     */
    public static function players(): array
    {
        return [
            ['shirt_number' => '44', 'full_name' => 'Matthias Köbbing', 'position_label' => 'Torwart'],
            ['shirt_number' => '1', 'full_name' => 'Marvin Schwäbe', 'position_label' => 'Torwart'],
            ['shirt_number' => '20', 'full_name' => 'Ron-Robert Zieler', 'position_label' => 'Torwart'],
            ['shirt_number' => '49', 'full_name' => 'David Fürst', 'position_label' => 'Abwehr'],
            ['shirt_number' => '3', 'full_name' => 'Dominique Heintz', 'position_label' => 'Abwehr'],
            ['shirt_number' => '4', 'full_name' => 'Timo Hübers', 'position_label' => 'Abwehr'],
            ['shirt_number' => '15', 'full_name' => 'Luca Kilian', 'position_label' => 'Abwehr'],
            ['shirt_number' => '32', 'full_name' => 'Kristoffer Lund', 'position_label' => 'Abwehr'],
            ['shirt_number' => '36', 'full_name' => 'Cenny Neumann', 'position_label' => 'Abwehr'],
            ['shirt_number' => '39', 'full_name' => 'Cenk Özkaçar', 'position_label' => 'Abwehr'],
            ['shirt_number' => '2', 'full_name' => 'Joël Schmied', 'position_label' => 'Abwehr'],
            ['shirt_number' => '28', 'full_name' => 'Sebastian Sebulonsen', 'position_label' => 'Abwehr'],
            ['shirt_number' => '22', 'full_name' => 'Jahmai Simpson-Pusey', 'position_label' => 'Abwehr'],
            ['shirt_number' => '33', 'full_name' => 'Rav van den Berg', 'position_label' => 'Abwehr'],
            ['shirt_number' => '17', 'full_name' => 'Alessio Castro-Montes', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '27', 'full_name' => 'Felipe Chávez', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '13', 'full_name' => 'Saïd El Mala', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '34', 'full_name' => 'Fayssal Harchaoui', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '8', 'full_name' => 'Denis Huseinbašić', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '18', 'full_name' => 'Ísak Jóhannesson', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '11', 'full_name' => 'Florian Kainz', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '5', 'full_name' => 'Tom Krauß', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '6', 'full_name' => 'Eric Martel', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '29', 'full_name' => 'Jan Thielmann', 'position_label' => 'Mittelfeld'],
            ['shirt_number' => '9', 'full_name' => 'Ragnar Ache', 'position_label' => 'Sturm'],
            ['shirt_number' => '30', 'full_name' => 'Marius Bülter', 'position_label' => 'Sturm'],
            ['shirt_number' => '19', 'full_name' => 'Malek El Mala', 'position_label' => 'Sturm'],
            ['shirt_number' => '16', 'full_name' => 'Jakub Kamiński', 'position_label' => 'Sturm'],
            ['shirt_number' => '37', 'full_name' => 'Linton Maina', 'position_label' => 'Sturm'],
            ['shirt_number' => '38', 'full_name' => 'Youssoupha Niang', 'position_label' => 'Sturm'],
            ['shirt_number' => '40', 'full_name' => 'Fynn Schenten', 'position_label' => 'Sturm'],
            ['shirt_number' => '7', 'full_name' => 'Luca Waldschmidt', 'position_label' => 'Sturm'],
            ['shirt_number' => null, 'full_name' => 'René Wagner', 'position_label' => 'Cheftrainer'],
            ['shirt_number' => null, 'full_name' => 'Armin Reutershahn', 'position_label' => 'Co-Trainer'],
            ['shirt_number' => null, 'full_name' => 'Lukas Sinkiewicz', 'position_label' => 'Co-Trainer'],
            ['shirt_number' => null, 'full_name' => 'Peter Greiber', 'position_label' => 'Torwart-Trainer'],
        ];
    }

    public static function apply(bool $onlyIfEmpty = true): void
    {
        if (! Schema::hasTable('squads') || ! Schema::hasTable('squad_players')) {
            return;
        }

        $squad = Squad::fcKoelnHerren();

        if ($onlyIfEmpty && $squad->players()->exists()) {
            return;
        }

        $squad->players()->delete();

        foreach (self::players() as $index => $row) {
            SquadPlayer::query()->create([
                'squad_id' => $squad->id,
                'shirt_number' => $row['shirt_number'],
                'full_name' => $row['full_name'],
                'position_label' => $row['position_label'],
                'sort_order' => $index,
            ]);
        }
    }
}

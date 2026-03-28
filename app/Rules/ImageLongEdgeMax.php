<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ImageLongEdgeMax implements ValidationRule
{
    public function __construct(
        protected int $maxPixels = 8064,
        protected ?int $maxShortPixels = null,
    ) {}

    /**
     * Prüft: Die lange Kante des Bildes (max. Breite/Höhe) darf maximal $maxPixels Pixel haben.
     * Entspricht der Master-Größe (z. B. 8064×6048 bzw. 6048×8064).
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $path = $value->getRealPath();
        if (! $path || ! is_readable($path)) {
            return;
        }

        $info = @getimagesize($path);
        if ($info === false || ! isset($info[0], $info[1])) {
            $fail('Die Datei konnte nicht als Bild gelesen werden.');

            return;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $longEdge = max($width, $height);
        $shortEdge = min($width, $height);

        if ($longEdge > $this->maxPixels) {
            $fail("Das Bild darf an der langen Kante maximal {$this->maxPixels} Pixel aufweisen (aktuell: {$longEdge} Pixel). Bitte vor dem Upload verkleinern.");

            return;
        }

        if ($this->maxShortPixels !== null && $shortEdge > $this->maxShortPixels) {
            $fail("Das Bild darf an der kurzen Kante maximal {$this->maxShortPixels} Pixel aufweisen (aktuell: {$shortEdge} Pixel). Bitte vor dem Upload verkleinern.");
        }
    }
}

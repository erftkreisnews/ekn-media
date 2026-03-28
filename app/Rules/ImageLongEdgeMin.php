<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ImageLongEdgeMin implements ValidationRule
{
    public function __construct(
        protected int $minPixels = 3500
    ) {}

    /**
     * Prüft: Die lange Kante des Bildes (max. Breite/Höhe) muss mindestens $minPixels Pixel haben.
     * Hochskalierung ist unzulässig – zu kleine Bilder werden abgelehnt.
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

        $longEdge = max((int) $info[0], (int) $info[1]);
        if ($longEdge < $this->minPixels) {
            $fail("Das Bild muss an der langen Kante mindestens {$this->minPixels} Pixel aufweisen (aktuell: {$longEdge} Pixel). Eine Hochskalierung ist unzulässig.");
        }
    }
}

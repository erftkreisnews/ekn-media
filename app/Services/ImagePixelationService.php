<?php

namespace App\Services;

/**
 * Macht definierte Bildbereiche dauerhaft unkenntlich (Originaldaten gehen verloren).
 * Zwei Modi: Verwischen (weich, z. B. für Gesichter) oder Pixelieren (stark, z. B. für Kennzeichen).
 */
class ImagePixelationService
{
    /** Pixelationsstärke: Region wird auf 1/N der Größe skaliert und wieder hochskaliert. */
    private const PIXELATE_FACTOR = 12;

    /** Anzahl Blur-Durchläufe: stark unerkennbar, bleibt weich/ästhetisch. */
    private const BLUR_PASSES = 36;

    /**
     * Wendet Unkenntlichmachung auf die Bereiche an und überschreibt die Datei.
     * mode: 'blur' = weiches Verwischen (für Gesichter), 'pixelate' = starke Pixelierung (z. B. Kennzeichen).
     *
     * @param  array<int, array{x: float, y: float, w: float, h: float}>  $regionsPercent
     */
    public function applyRegions(string $path, array $regionsPercent, string $mode = 'blur'): bool
    {
        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($path);
        $fullPath = $resolved['path'] ?? null;
        if (! is_string($fullPath) || ! is_readable($fullPath)) {
            return false;
        }

        $img = @imagecreatefromjpeg($fullPath);
        if ($img === false) {
            $img = @imagecreatefrompng($fullPath);
        }
        if ($img === false) {
            return false;
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $useBlur = ($mode === 'blur');

        foreach ($regionsPercent as $region) {
            $x = (int) round($region['x'] / 100 * $width);
            $y = (int) round($region['y'] / 100 * $height);
            $w = (int) round($region['w'] / 100 * $width);
            $h = (int) round($region['h'] / 100 * $height);

            if ($w < 2 || $h < 2) {
                continue;
            }
            $x = max(0, min($x, $width - 2));
            $y = max(0, min($y, $height - 2));
            $w = min($w, $width - $x);
            $h = min($h, $height - $y);

            if ($useBlur) {
                $this->blurRegion($img, $x, $y, $w, $h);
            } else {
                $this->pixelateRegion($img, $x, $y, $w, $h);
            }
        }

        $result = imagejpeg($img, $fullPath, 90);
        if ($result && (($resolved['temporary'] ?? false) === true)) {
            app(MediaStorage::class)->putFromLocalFile($path, $fullPath, ['visibility' => 'public']);
        }
        app(MediaStorage::class)->cleanupResolvedPath($resolved);
        imagedestroy($img);

        return $result;
    }

    /** @deprecated Nutzen Sie applyRegions() mit mode 'pixelate'. */
    public function pixelateRegions(string $path, array $regionsPercent): bool
    {
        return $this->applyRegions($path, $regionsPercent, 'pixelate');
    }

    /**
     * Pixeliert einen rechteckigen Ausschnitt: stark herunterskalieren, dann mit
     * nächster-Nachbar-Interpolation wieder hochskalieren (zerstört Details dauerhaft).
     */
    private function pixelateRegion(\GdImage $img, int $x, int $y, int $w, int $h): void
    {
        $smallW = max(1, (int) floor($w / self::PIXELATE_FACTOR));
        $smallH = max(1, (int) floor($h / self::PIXELATE_FACTOR));

        $small = imagecreatetruecolor($smallW, $smallH);
        if ($small === false) {
            return;
        }
        imagecopy($small, $img, 0, 0, $x, $y, $w, $h);

        $big = imagescale($small, $w, $h, defined('IMG_NEAREST_NEIGHBOUR') ? IMG_NEAREST_NEIGHBOUR : 0);
        imagedestroy($small);
        if ($big === false) {
            return;
        }

        imagecopy($img, $big, $x, $y, 0, 0, $w, $h);
        imagedestroy($big);
    }

    /** Weicher Rand (Feather) in Pixeln – Übergang Blur ↔ Bild. */
    private const BLUR_FEATHER = 45;

    /**
     * Verwischt einen rechteckigen Ausschnitt mit weichem Rand (Gaussian Blur + Feather).
     * Die Person bleibt unkenntlich, der Übergang zum Rest des Bildes ist weich.
     */
    private function blurRegion(\GdImage $img, int $x, int $y, int $w, int $h): void
    {
        $imgW = imagesx($img);
        $imgH = imagesy($img);
        $feather = min(
            self::BLUR_FEATHER,
            max(8, (int) floor(min($w, $h) * 0.2)),
            $x,
            $y,
            max(0, $imgW - ($x + $w)),
            max(0, $imgH - ($y + $h))
        );
        $feather = max(8, $feather);
        $pad = $feather;
        $x0 = max(0, $x - $pad);
        $y0 = max(0, $y - $pad);
        $x1 = min(imagesx($img), $x + $w + $pad);
        $y1 = min(imagesy($img), $y + $h + $pad);
        $pw = $x1 - $x0;
        $ph = $y1 - $y0;
        $innerL = $x - $x0;
        $innerT = $y - $y0;
        $innerR = $innerL + $w;
        $innerB = $innerT + $h;

        $patch = imagecreatetruecolor($pw, $ph);
        if ($patch === false) {
            return;
        }
        imagecopy($patch, $img, 0, 0, $x0, $y0, $pw, $ph);

        $blurred = imagecreatetruecolor($pw, $ph);
        if ($blurred === false) {
            imagedestroy($patch);

            return;
        }
        imagecopy($blurred, $patch, 0, 0, 0, 0, $pw, $ph);

        if (defined('IMG_FILTER_GAUSSIAN_BLUR')) {
            for ($i = 0; $i < self::BLUR_PASSES; $i++) {
                imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
                imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
            }
        } else {
            for ($i = 0; $i < self::BLUR_PASSES; $i++) {
                imagefilter($blurred, IMG_FILTER_SELECTIVE_BLUR);
            }
        }

        for ($jy = 0; $jy < $ph; $jy++) {
            for ($ix = 0; $ix < $pw; $ix++) {
                $dist = $this->distanceToRect($ix, $jy, $innerL, $innerT, $innerR, $innerB);
                if ($dist >= $feather) {
                    continue;
                }
                if ($dist <= 0) {
                    $alpha = 255;
                } else {
                    $t = $dist / (float) $feather;
                    $t = $t * $t * (3 - 2 * $t);
                    $alpha = (int) round(255 * (1 - $t));
                }
                if ($alpha <= 0) {
                    continue;
                }
                $orig = imagecolorat($patch, $ix, $jy);
                $blur = imagecolorat($blurred, $ix, $jy);
                $r = (int) (($alpha * (($blur >> 16) & 0xFF) + (255 - $alpha) * (($orig >> 16) & 0xFF)) / 255);
                $g = (int) (($alpha * (($blur >> 8) & 0xFF) + (255 - $alpha) * (($orig >> 8) & 0xFF)) / 255);
                $b = (int) (($alpha * ($blur & 0xFF) + (255 - $alpha) * ($orig & 0xFF)) / 255);
                $r = max(0, min(255, $r));
                $g = max(0, min(255, $g));
                $b = max(0, min(255, $b));
                imagesetpixel($img, $x0 + $ix, $y0 + $jy, ($r << 16) | ($g << 8) | $b);
            }
        }

        imagedestroy($patch);
        imagedestroy($blurred);
    }

    private function distanceToRect(int $px, int $py, int $left, int $top, int $right, int $bottom): float
    {
        $dx = max(0, $left - $px, $px - $right + 1);
        $dy = max(0, $top - $py, $py - $bottom + 1);

        return sqrt((float) ($dx * $dx + $dy * $dy));
    }
}

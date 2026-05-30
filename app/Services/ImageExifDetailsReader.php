<?php

namespace App\Services;

/**
 * Liest technische EXIF-Daten (Kamera, Belichtung, Auflösung) für die Admin-Anzeige.
 */
class ImageExifDetailsReader
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public static function read(string $fullPath): array
    {
        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return [];
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'tiff', 'tif'], true)) {
            return [];
        }

        $rows = [];

        $size = @getimagesize($fullPath);
        if (is_array($size) && isset($size[0], $size[1]) && $size[0] > 0 && $size[1] > 0) {
            $rows[] = ['label' => 'Abmessungen', 'value' => $size[0].' × '.$size[1].' px'];
            if (! empty($size['mime'])) {
                $rows[] = ['label' => 'MIME-Typ', 'value' => (string) $size['mime']];
            }
        }

        if (! function_exists('exif_read_data')) {
            return $rows;
        }

        $exif = @exif_read_data($fullPath, 'EXIF,IFD0,COMPUTED', true);
        if (! is_array($exif)) {
            return $rows;
        }

        $ifd0 = is_array($exif['IFD0'] ?? null) ? $exif['IFD0'] : [];
        $exifBlock = is_array($exif['EXIF'] ?? null) ? $exif['EXIF'] : [];
        $computed = is_array($exif['COMPUTED'] ?? null) ? $exif['COMPUTED'] : [];

        $make = self::stringValue($ifd0['Make'] ?? null);
        $model = self::stringValue($ifd0['Model'] ?? null);
        $camera = trim($make.' '.$model);
        if ($camera !== '') {
            $rows[] = ['label' => 'Kamera', 'value' => $camera];
        }

        $lens = self::stringValue($exifBlock['LensModel'] ?? $exifBlock['UndefinedTag:0xA434'] ?? null);
        if ($lens !== '') {
            $rows[] = ['label' => 'Objektiv', 'value' => $lens];
        }

        $dateOriginal = self::stringValue($exifBlock['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? null);
        if ($dateOriginal !== '') {
            $rows[] = ['label' => 'Aufnahmezeit (EXIF)', 'value' => self::formatExifDateTime($dateOriginal)];
        }

        $exposure = self::formatExposureTime($exifBlock['ExposureTime'] ?? $computed['ExposureTime'] ?? null);
        if ($exposure !== null) {
            $rows[] = ['label' => 'Verschlusszeit', 'value' => $exposure];
        }

        $aperture = self::formatAperture($exifBlock['FNumber'] ?? $exifBlock['ApertureValue'] ?? $computed['ApertureFNumber'] ?? null);
        if ($aperture !== null) {
            $rows[] = ['label' => 'Blende', 'value' => $aperture];
        }

        $iso = self::stringValue($exifBlock['ISOSpeedRatings'] ?? $exifBlock['PhotographicSensitivity'] ?? null);
        if ($iso !== '') {
            $rows[] = ['label' => 'ISO', 'value' => $iso];
        }

        $focal = self::formatFocalLength($exifBlock['FocalLength'] ?? $computed['FocalLength'] ?? null);
        if ($focal !== null) {
            $rows[] = ['label' => 'Brennweite', 'value' => $focal];
        }

        $flash = self::formatFlash($exifBlock['Flash'] ?? null);
        if ($flash !== null) {
            $rows[] = ['label' => 'Blitz', 'value' => $flash];
        }

        $program = self::formatExposureProgram($exifBlock['ExposureProgram'] ?? null);
        if ($program !== null) {
            $rows[] = ['label' => 'Belichtungsprogramm', 'value' => $program];
        }

        $metering = self::formatMeteringMode($exifBlock['MeteringMode'] ?? null);
        if ($metering !== null) {
            $rows[] = ['label' => 'Messmethode', 'value' => $metering];
        }

        $whiteBalance = self::formatWhiteBalance($exifBlock['WhiteBalance'] ?? null);
        if ($whiteBalance !== null) {
            $rows[] = ['label' => 'Weißabgleich', 'value' => $whiteBalance];
        }

        $orientation = self::formatOrientation($ifd0['Orientation'] ?? null);
        if ($orientation !== null) {
            $rows[] = ['label' => 'Ausrichtung', 'value' => $orientation];
        }

        $colorSpace = self::formatColorSpace($exifBlock['ColorSpace'] ?? null);
        if ($colorSpace !== null) {
            $rows[] = ['label' => 'Farbraum', 'value' => $colorSpace];
        }

        $software = self::stringValue($ifd0['Software'] ?? null);
        if ($software !== '') {
            $rows[] = ['label' => 'Software', 'value' => $software];
        }

        $artist = self::stringValue($ifd0['Artist'] ?? null);
        if ($artist !== '') {
            $rows[] = ['label' => 'Artist (EXIF)', 'value' => $artist];
        }

        return $rows;
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private static function formatExifDateTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '—';
        }

        try {
            $dt = \Carbon\Carbon::createFromFormat('Y:m:d H:i:s', $raw);

            return $dt->format('d.m.Y, H:i:s').' Uhr';
        } catch (\Throwable) {
            return $raw;
        }
    }

    private static function formatExposureTime(mixed $value): ?string
    {
        $seconds = self::rationalToFloat($value);
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        if ($seconds >= 1) {
            return rtrim(rtrim(number_format($seconds, 2, ',', '.'), '0'), ',').' s';
        }

        $denominator = (int) round(1 / $seconds);

        return '1/'.$denominator.' s';
    }

    private static function formatAperture(mixed $value): ?string
    {
        $f = self::rationalToFloat($value);
        if ($f === null || $f <= 0) {
            return null;
        }

        return 'f/'.rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.');
    }

    private static function formatFocalLength(mixed $value): ?string
    {
        $mm = self::rationalToFloat($value);
        if ($mm === null || $mm <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format($mm, 1, ',', '.'), '0'), ',').' mm';
    }

    private static function rationalToFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '/')) {
            [$num, $den] = array_pad(explode('/', $raw, 2), 2, null);
            $n = is_numeric($num) ? (float) $num : null;
            $d = is_numeric($den) ? (float) $den : null;
            if ($n === null || $d === null || $d == 0.0) {
                return null;
            }

            return $n / $d;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private static function formatFlash(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $code = (int) $value;

        return match (true) {
            $code === 0 => 'Nicht ausgelöst',
            $code === 1 => 'Ausgelöst',
            ($code & 1) === 0 => 'Nicht ausgelöst',
            default => 'Ausgelöst',
        };
    }

    private static function formatExposureProgram(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((int) $value) {
            0 => 'Nicht definiert',
            1 => 'Manuell',
            2 => 'Programmautomatik',
            3 => 'Zeitautomatik',
            4 => 'Blendenautomatik',
            5 => 'Kreativprogramm',
            6 => 'Action-Programm',
            7 => 'Portrait',
            8 => 'Landschaft',
            default => 'Programm '.(int) $value,
        };
    }

    private static function formatMeteringMode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((int) $value) {
            0 => 'Unbekannt',
            1 => 'Durchschnitt',
            2 => 'Mittenbetont',
            3 => 'Spot',
            4 => 'Mehrfach-Spot',
            5 => 'Muster',
            6 => 'Bildteil',
            default => 'Modus '.(int) $value,
        };
    }

    private static function formatWhiteBalance(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((int) $value) {
            0 => 'Automatisch',
            1 => 'Manuell',
            default => 'Modus '.(int) $value,
        };
    }

    private static function formatOrientation(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((int) $value) {
            1 => 'Normal',
            2 => 'Horizontal gespiegelt',
            3 => '180° gedreht',
            4 => 'Vertikal gespiegelt',
            5 => '90° gegen Uhrzeigersinn + gespiegelt',
            6 => '90° im Uhrzeigersinn',
            7 => '90° im Uhrzeigersinn + gespiegelt',
            8 => '90° gegen Uhrzeigersinn',
            default => 'Orientierung '.(int) $value,
        };
    }

    private static function formatColorSpace(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((int) $value) {
            1 => 'sRGB',
            65535 => 'Nicht kalibriert',
            default => 'Farbraum '.(int) $value,
        };
    }
}

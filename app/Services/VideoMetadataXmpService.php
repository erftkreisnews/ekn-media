<?php

namespace App\Services;

use App\Models\NewsItemMedia;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class VideoMetadataXmpService
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function validateAndNormalize(array $metadata): array
    {
        $headline = $this->requiredString($metadata, 'headline');
        $caption = $this->requiredString($metadata, 'caption');
        $location = $this->requiredString($metadata, 'location');
        $date = $this->requiredString($metadata, 'date');

        try {
            $parsedDate = Carbon::parse($date)->toIso8601String();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Feld "date" muss ein gültiges ISO-Datum sein.');
        }

        $keywords = $this->normalizeKeywords(Arr::get($metadata, 'keywords', []));

        return [
            'headline' => $headline,
            'caption' => $caption,
            'keywords' => $keywords,
            'city' => $this->optionalString($metadata, 'city'),
            'location' => $location,
            'country' => $this->optionalString($metadata, 'country'),
            'date' => $parsedDate,
            'creator' => $this->optionalString($metadata, 'creator'),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function generateXmp(array $normalized): string
    {
        $headline = $this->xml($normalized['headline'] ?? '');
        $caption = $this->xml($normalized['caption'] ?? '');
        $city = $this->xml($normalized['city'] ?? '');
        $location = $this->xml($normalized['location'] ?? '');
        $country = $this->xml($normalized['country'] ?? '');
        $date = $this->xml($normalized['date'] ?? '');
        $creator = $this->xml($normalized['creator'] ?? '');

        $keywordsXml = '';
        foreach (($normalized['keywords'] ?? []) as $keyword) {
            $keywordsXml .= '      <rdf:li>'.$this->xml((string) $keyword).'</rdf:li>'."\n";
        }

        return
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n".
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n".
            '<rdf:Description rdf:about=""'."\n".
            'xmlns:dc="http://purl.org/dc/elements/1.1/"'."\n".
            'xmlns:Iptc4xmpCore="http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/">'."\n\n".
            '  <dc:title>'."\n".
            '    <rdf:Alt>'."\n".
            '      <rdf:li xml:lang="x-default">'.$headline.'</rdf:li>'."\n".
            '    </rdf:Alt>'."\n".
            '  </dc:title>'."\n\n".
            '  <dc:description>'."\n".
            '    <rdf:Alt>'."\n".
            '      <rdf:li xml:lang="x-default">'.$caption.'</rdf:li>'."\n".
            '    </rdf:Alt>'."\n".
            '  </dc:description>'."\n\n".
            '  <dc:subject>'."\n".
            '    <rdf:Bag>'."\n".
            $keywordsXml.
            '    </rdf:Bag>'."\n".
            '  </dc:subject>'."\n\n".
            '  <Iptc4xmpCore:City>'.$city.'</Iptc4xmpCore:City>'."\n".
            '  <Iptc4xmpCore:Location>'.$location.'</Iptc4xmpCore:Location>'."\n".
            '  <Iptc4xmpCore:CountryName>'.$country.'</Iptc4xmpCore:CountryName>'."\n\n".
            '  <dc:date>'.$date.'</dc:date>'."\n\n".
            '  <dc:creator>'."\n".
            '    <rdf:Seq>'."\n".
            '      <rdf:li>'.$creator.'</rdf:li>'."\n".
            '    </rdf:Seq>'."\n".
            '  </dc:creator>'."\n\n".
            '</rdf:Description>'."\n".
            '</rdf:RDF>'."\n".
            '</x:xmpmeta>'."\n";
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function ffmpegMetadataArguments(array $normalized): array
    {
        $map = [
            'title' => (string) ($normalized['headline'] ?? ''),
            'description' => (string) ($normalized['caption'] ?? ''),
            'location' => (string) ($normalized['location'] ?? ''),
            'author' => (string) ($normalized['creator'] ?? ''),
            'date' => (string) ($normalized['date'] ?? ''),
        ];

        $args = [];
        foreach ($map as $key => $value) {
            if (trim($value) === '') {
                continue;
            }
            $args[] = '-metadata';
            $args[] = $key.'='.$value;
        }

        return $args;
    }

    public function generateForVideoMedia(NewsItemMedia $media): string
    {
        $media->loadMissing('newsItem');
        $newsItem = $media->newsItem;

        $headline = trim((string) ($media->image_title ?: $newsItem?->title ?: 'Video'));
        $caption = trim((string) ($media->caption ?: $newsItem?->subheadline ?: $newsItem?->title ?: $headline));
        $location = trim((string) ($media->metadata_location ?: $newsItem?->location_label ?: 'Unbekannter Ort'));
        $date = $media->metadata_recorded_at?->toIso8601String()
            ?: $newsItem?->published_at?->toIso8601String()
            ?: now()->toIso8601String();
        $creator = trim((string) ($media->photographer ?: $newsItem?->author_credit ?: 'Erftkreis News'));

        $keywords = [];
        foreach (preg_split('/\s*,\s*/u', (string) ($media->media_keywords ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $kw) {
            $kw = trim((string) $kw);
            if ($kw !== '') {
                $keywords[$kw] = true;
            }
        }
        foreach (preg_split('/\s*,\s*/u', (string) ($newsItem?->keywords ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $kw) {
            $kw = trim((string) $kw);
            if ($kw !== '') {
                $keywords[$kw] = true;
            }
        }

        $normalized = $this->validateAndNormalize([
            'headline' => $headline,
            'caption' => $caption,
            'keywords' => array_keys($keywords),
            'city' => $media->city ?? '',
            'location' => $location,
            'country' => $media->country ?? '',
            'date' => $date,
            'creator' => $creator,
        ]);

        return $this->generateXmp($normalized);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function requiredString(array $metadata, string $key): string
    {
        $value = $this->optionalString($metadata, $key);
        if ($value === '') {
            throw new InvalidArgumentException('Pflichtfeld fehlt oder leer: '.$key);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function optionalString(array $metadata, string $key): string
    {
        $value = Arr::get($metadata, $key);
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @return list<string>
     */
    private function normalizeKeywords(mixed $keywords): array
    {
        if (is_string($keywords)) {
            $parts = preg_split('/\s*,\s*/u', $keywords) ?: [];
        } elseif (is_array($keywords)) {
            $parts = $keywords;
        } else {
            $parts = [];
        }

        $out = [];
        foreach ($parts as $part) {
            $v = trim((string) $part);
            if ($v !== '') {
                $out[$v] = true;
            }
        }

        return array_keys($out);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

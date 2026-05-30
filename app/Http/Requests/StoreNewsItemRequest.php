<?php

namespace App\Http\Requests;

use App\Rules\ImageLongEdgeMax;
use App\Rules\ImageLongEdgeMin;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreNewsItemRequest extends FormRequest
{
    /** Nur von `admin.news.create.koelnimage` gesetzt — für Validierung & Redirect bei Fehlern. */
    public const NEWS_FLOW_KOELNIMAGE_PHOTO = 'koelnimage_photo';

    protected function prepareForValidation(): void
    {
        if (! $this->filled('update_type')) {
            $this->merge([
                'update_type' => $this->input('news_entry_flow') === self::NEWS_FLOW_KOELNIMAGE_PHOTO
                    ? 'final'
                    : 'first_report',
            ]);
        }

        if ($this->has('planned_event_id') && $this->input('planned_event_id') === '') {
            $this->merge(['planned_event_id' => null]);
        }
        if ($this->has('latitude') && $this->input('latitude') === '') {
            $this->merge(['latitude' => null]);
        }
        if ($this->has('longitude') && $this->input('longitude') === '') {
            $this->merge(['longitude' => null]);
        }
        if ($this->has('brand_id') && $this->input('brand_id') === '') {
            $this->merge(['brand_id' => null]);
        }

        foreach (['country', 'federal_state', 'region', 'city', 'street'] as $addressField) {
            $raw = $this->input($addressField);
            if (! is_string($raw)) {
                continue;
            }
            $normalized = mb_strtolower(trim($raw));
            if ($normalized === 'nicht bekannt') {
                $this->merge([$addressField => null]);
            }
        }

        $keywordsRaw = $this->input('keywords');
        if (is_string($keywordsRaw) && trim($keywordsRaw) !== '') {
            $eventAtRaw = $this->input('event_at');
            $eventYear = null;
            if (is_string($eventAtRaw) && trim($eventAtRaw) !== '') {
                try {
                    $eventYear = (string) Carbon::parse($eventAtRaw)->year;
                } catch (\Throwable) {
                    $eventYear = null;
                }
            }
            $targetYear = $eventYear ?: (string) now()->year;
            $normalizedKeywords = (string) preg_replace('/\b20\d{2}\b/u', $targetYear, $keywordsRaw);
            $this->merge(['keywords' => $normalizedKeywords]);
        }

        $taxonomyRaw = $this->input('taxonomy_terms');
        if (is_string($taxonomyRaw) && trim($taxonomyRaw) !== '') {
            $selectedTerms = array_values(array_unique(array_filter(array_map(
                static fn (string $term): string => trim($term),
                explode(',', $taxonomyRaw)
            ), static fn (string $term): bool => $term !== '')));

            if ($selectedTerms !== []) {
                $existingKeywords = is_string($this->input('keywords')) ? (string) $this->input('keywords') : '';
                $keywordParts = array_values(array_unique(array_filter(array_map(
                    static fn (string $term): string => trim($term),
                    explode(',', $existingKeywords)
                ), static fn (string $term): bool => $term !== '')));
                $mergedKeywords = array_values(array_unique(array_merge($keywordParts, $selectedTerms)));
                $this->merge(['keywords' => implode(', ', $mergedKeywords)]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ohne Referer landet „back()“ sonst auf der zuletzt besuchten Admin-URL (oft /admin/news) —
     * Nutzer sehen dort ggf. eine maskierte Fehlerseite statt der Kölnimage-Maske mit Validierungsfehlern.
     */
    protected function getRedirectUrl(): string
    {
        if ($this->input('news_entry_flow') === self::NEWS_FLOW_KOELNIMAGE_PHOTO) {
            return route('admin.news.create.koelnimage');
        }

        return route('admin.news.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statuses = ['draft', 'review', 'published', 'archived'];
        $isKoelnimagePhotoDraft = $this->input('news_entry_flow') === self::NEWS_FLOW_KOELNIMAGE_PHOTO
            && (string) $this->input('status', '') === 'draft';

        $rules = [
            'title' => ['required', 'string', 'max:265'],
            'teaser' => ['nullable', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:512'],
            'body' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string', 'max:512'],
            'taxonomy_terms' => ['nullable', 'string', 'max:2000'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'federal_state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in($statuses)],
            'published_at' => ['nullable', 'date'],
            'event_at' => ['nullable', 'date'],
            'embargo_at' => ['nullable', 'date'],
            'source_type' => ['nullable', 'string', 'max:64'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'verification_status' => ['nullable', Rule::in(['unverified', 'verified', 'official'])],
            'update_type' => ['nullable', Rule::in(['first_report', 'update', 'correction', 'final'])],
            'author_credit_user_id' => ['required', 'integer', 'exists:users,id'],
            'media_ai_context' => ['nullable', 'string', 'max:8000'],
            'planned_event_id' => ['nullable', 'integer', 'exists:planned_events,id'],
            'source_news_item_id' => ['nullable', 'integer', 'exists:news_items,id'],
            'source_media_ids' => ['nullable', 'array'],
            'source_media_ids.*' => ['integer', 'exists:news_item_media,id'],
            'moid' => ['nullable', 'string', 'max:128'],
            'no_wdr_job' => ['sometimes', 'boolean'],
            'is_breaking' => ['sometimes', 'boolean'],
            'planned_video_upload' => ['sometimes', 'boolean'],
            'liveu_on_site' => ['sometimes', 'boolean'],
            'is_confidential' => ['sometimes', 'boolean'],
            'news_entry_flow' => ['nullable', 'string', 'in:'.self::NEWS_FLOW_KOELNIMAGE_PHOTO],
            'images' => $isKoelnimagePhotoDraft
                ? ['nullable', 'array']
                : ['required_if:update_type,first_report', 'array', 'min:1'],
            'images.*' => [
                'file', 'image', 'mimes:jpeg,jpg,png,webp',
                'max:'.config('media.image_upload_max_kb', 20480),
                new ImageLongEdgeMin((int) config('media.image_long_edge_min', 1800)),
                new ImageLongEdgeMax(config('media.image_master_long_edge_max', 6048)),
            ],
            'videos' => ['sometimes', 'array'],
            'videos.*' => ['file', 'mimes:mp4,mov,webm,avi', 'max:'.config('media.video_upload_max_kb', 3145728)], // 3 GB
            'audios' => ['sometimes', 'array'],
            'audios.*' => [
                'file',
                'mimes:mp3,wav,ogg,m4a,mpga,aac,flac,opus,webm,wma',
                'max:'.config('media.audio_upload_max_kb', 51200),
            ],
        ];

        if (Schema::hasTable('brands') && Schema::hasColumn('news_items', 'brand_id')) {
            $rules['brand_id'] = [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('is_active', true),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'images.required_if' => 'Bei einer Erstmeldung ist mindestens ein Bild erforderlich.',
            'images.min' => 'Bei einer Erstmeldung ist mindestens ein Bild erforderlich.',
            'images.*.mimes' => 'Bitte JPG, PNG oder WEBP hochladen. HEIC/HEIF wird aktuell nicht unterstützt.',
            'images.*.image' => 'Die ausgewählte Datei ist kein unterstütztes Bildformat.',
        ];
    }
}

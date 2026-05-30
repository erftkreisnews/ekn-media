<?php

namespace App\Http\Requests;

use App\Models\NewsItem;
use App\Rules\ImageLongEdgeMax;
use App\Rules\ImageLongEdgeMin;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNewsItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statuses = ['draft', 'review', 'published', 'archived'];

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
            'images' => ['sometimes', 'array'],
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
            'delete_media' => ['sometimes', 'array'],
            'delete_media.*' => ['integer', 'exists:news_item_media,id'],
            'media' => ['sometimes', 'array'],
            'media.*' => ['array'],
            'media.*.is_visible' => ['sometimes', 'boolean'],
            'media.*.versand' => ['sometimes', 'boolean'],
            'media.*.delivery_visibility_set' => ['sometimes'],
            'media.*.delivery_visible_for_organization_ids' => ['sometimes', 'array'],
            'media.*.delivery_visible_for_organization_ids.*' => ['integer', Rule::exists('organizations', 'id')],
            'media.*.is_teaser' => ['sometimes', 'boolean'],
            'media.*.caption' => ['sometimes', 'nullable', 'string', 'max:1800'],
            'media.*.media_keywords' => ['sometimes', 'nullable', 'string', 'max:512'],
            'media.*.description' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'media.*.metadata_location' => ['sometimes', 'nullable', 'string', 'max:512'],
            'media.*.metadata_recorded_at' => ['sometimes', 'nullable', 'date'],
            'teaser_media_id' => ['sometimes', 'nullable', 'integer', 'exists:news_item_media,id'],
            'save_and_redirect_to_send' => ['sometimes'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('update_type') !== 'first_report') {
                return;
            }

            $newsItem = $this->route('newsItem');
            if ($newsItem instanceof NewsItem && $newsItem->hasPriorDeliveries()) {
                $validator->errors()->add(
                    'update_type',
                    'Nach dem ersten Versand ist „Erstmeldung“ nicht mehr möglich. Bitte Update, Korrektur oder Abschluss wählen.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'images.*.mimes' => 'Bitte JPG, PNG oder WEBP hochladen. HEIC/HEIF wird aktuell nicht unterstützt.',
            'images.*.image' => 'Die ausgewählte Datei ist kein unterstütztes Bildformat.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Rules\ImageLongEdgeMax;
use App\Rules\ImageLongEdgeMin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNewsItemRequest extends FormRequest
{
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

        return [
            'title' => ['required', 'string', 'max:265'],
            'teaser' => ['nullable', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:512'],
            'body' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string', 'max:512'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'federal_state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in($statuses)],
            'published_at' => ['nullable', 'date'],
            'embargo_at' => ['nullable', 'date'],
            'author_credit' => ['nullable', 'string', 'max:255'],
            'moid' => ['nullable', 'string', 'max:128'],
            'no_wdr_job' => ['sometimes', 'boolean'],
            'is_breaking' => ['sometimes', 'boolean'],
            'planned_video_upload' => ['sometimes', 'boolean'],
            'liveu_on_site' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*' => [
                'file', 'image', 'mimes:jpeg,jpg',
                'max:'.config('media.image_upload_max_kb', 20480),
                new ImageLongEdgeMin(3500),
                new ImageLongEdgeMax(config('media.image_master_long_edge_max', 6048)),
            ],
            'videos' => ['sometimes', 'array'],
            'videos.*' => ['file', 'mimes:mp4,mov,webm,avi', 'max:2097152'], // 2 GB
            'audios' => ['sometimes', 'array'],
            'audios.*' => ['file', 'mimes:mp3,wav,ogg,m4a,mpga', 'max:51200'],
            'delete_media' => ['sometimes', 'array'],
            'delete_media.*' => ['integer', 'exists:news_item_media,id'],
            'media' => ['sometimes', 'array'],
            'media.*' => ['array'],
            'media.*.is_visible' => ['sometimes', 'boolean'],
            'media.*.versand' => ['sometimes', 'boolean'],
            'media.*.is_teaser' => ['sometimes', 'boolean'],
            'media.*.caption' => ['sometimes', 'nullable', 'string', 'max:1800'],
            'teaser_media_id' => ['sometimes', 'nullable', 'integer', 'exists:news_item_media,id'],
        ];
    }
}

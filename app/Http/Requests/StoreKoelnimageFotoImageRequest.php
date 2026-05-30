<?php

namespace App\Http\Requests;

use App\Rules\ImageLongEdgeMax;
use App\Rules\ImageLongEdgeMin;
use Illuminate\Foundation\Http\FormRequest;

class StoreKoelnimageFotoImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:'.config('media.image_upload_max_kb', 20480),
                new ImageLongEdgeMin((int) config('media.image_long_edge_min', 1800)),
                new ImageLongEdgeMax(config('media.image_master_long_edge_max', 6048)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimes' => 'Bitte JPG, PNG oder WEBP hochladen.',
            'image.image' => 'Die ausgewählte Datei ist kein unterstütztes Bildformat.',
        ];
    }
}

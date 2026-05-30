<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteWitnessSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_type' => ['required', Rule::in(['image', 'video', 'audio'])],
            'image_title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:4000'],
            'photographer' => ['nullable', 'string', 'max:255'],
            'apply_anonymous_credit' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'apply_anonymous_credit' => $this->boolean('apply_anonymous_credit'),
        ]);
    }
}

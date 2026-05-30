<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseNewsBlocktextRequest extends FormRequest
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
            'text' => ['required', 'string', 'max:50000'],
        ];
    }
}

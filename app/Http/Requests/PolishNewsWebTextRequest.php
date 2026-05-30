<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PolishNewsWebTextRequest extends FormRequest
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
        $max = (int) config('news_ai.max_input_chars', 24000);

        return [
            'text' => ['required', 'string', 'min:3', 'max:'.$max],
        ];
    }
}

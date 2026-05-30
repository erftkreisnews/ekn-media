<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNewsWebTextAiPromptRequest extends FormRequest
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
            'news_web_text_ai_system_prompt' => ['nullable', 'string', 'max:12000'],
        ];
    }
}

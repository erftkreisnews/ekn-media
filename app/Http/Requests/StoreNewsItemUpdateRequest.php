<?php

namespace App\Http\Requests;

use App\Models\NewsItemUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(NewsItemUpdate::updateTypeOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'source_type' => ['nullable', 'string', Rule::in(array_keys(NewsItemUpdate::sourceTypeOptions()))],
            'source_label' => ['nullable', 'string', 'max:255'],
            'happened_at' => ['nullable', 'date'],
            'show_in_mail' => ['sometimes', 'boolean'],
            'show_in_article' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'statement_id' => ['nullable', 'integer', 'exists:news_item_statements,id'],
            'presseportal_url' => ['nullable', 'string', 'max:2000'],
            'presseportal_story_id' => ['nullable', 'integer'],
            'presseportal_office_id' => ['nullable', 'integer'],
        ];
    }
}

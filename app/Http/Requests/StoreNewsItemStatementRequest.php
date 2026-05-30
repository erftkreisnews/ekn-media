<?php

namespace App\Http\Requests;

use App\Models\NewsItemStatement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsItemStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', Rule::in(array_keys(NewsItemStatement::sourceTypeOptions()))],
            'source_label' => ['nullable', 'string', 'max:255'],
            'statement_type' => ['required', 'string', Rule::in(array_keys(NewsItemStatement::statementTypeOptions()))],
            'received_at' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'transcript' => ['required', 'string'],
            'show_in_mail' => ['sometimes', 'boolean'],
            'is_publishable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'create_update' => ['sometimes', 'boolean'],
        ];
    }
}

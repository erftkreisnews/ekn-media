<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKoelnimageFotoRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:265'],
            'planned_event_id' => ['nullable', 'integer', 'exists:planned_events,id'],
            'author_credit_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('planned_event_id') && $this->input('planned_event_id') === '') {
            $this->merge(['planned_event_id' => null]);
        }
    }
}

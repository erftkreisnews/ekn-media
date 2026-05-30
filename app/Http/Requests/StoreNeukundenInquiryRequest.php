<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNeukundenInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medienhaus' => ['required', 'string', 'max:200'],
            'redaktion' => ['required', 'string', 'max:200'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'medienhaus.required' => 'Bitte geben Sie das Medienhaus (Verlag / Sender) an.',
            'redaktion.required' => 'Bitte geben Sie die Redaktion bzw. das Format an.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ];
    }

    /**
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $started = session('neukunden_form_started_at');
            if ($started === null) {
                $validator->errors()->add('form', 'Bitte laden Sie die Seite neu und senden Sie das Formular erneut.');

                return;
            }
            $elapsed = time() - (int) $started;
            if ($elapsed < 3) {
                $validator->errors()->add('form', 'Bitte warten Sie einen kurzen Moment und versuchen Sie es erneut.');
            }
            if ($elapsed > 7200) {
                $validator->errors()->add('form', 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.');
            }
        });
    }
}

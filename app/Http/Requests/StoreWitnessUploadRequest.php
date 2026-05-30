<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreWitnessUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = max(1024, (int) config('witness.max_file_kb', 153600));
        $maxFiles = max(1, (int) config('witness.max_files_per_submit', 20));

        return [
            'submitter_name' => ['required', 'string', 'max:191'],
            'submitter_email' => ['required', 'email', 'max:191'],
            'submitter_phone' => ['nullable', 'string', 'max:64'],
            'consent_terms' => ['accepted'],
            'consent_rights' => ['accepted'],
            'credit_anonymous' => ['sometimes', 'boolean'],
            'witness_suggested_title' => ['nullable', 'string', 'max:255'],
            'media' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'media.*' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/webm'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $media = $this->file('media');
        if ($media instanceof UploadedFile) {
            $this->files->set('media', [$media]);
        }

        $this->merge([
            'credit_anonymous' => $this->boolean('credit_anonymous'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $started = (int) session('witness_form_started_at', 0);
            $min = max(0, (int) config('witness.min_seconds_before_submit', 4));
            if ($min > 0 && ($started <= 0 || (time() - $started) < $min)) {
                $v->errors()->add('form', 'Bitte lesen Sie die Hinweise und versuchen Sie es in wenigen Sekunden erneut.');
            }
        });
    }
}

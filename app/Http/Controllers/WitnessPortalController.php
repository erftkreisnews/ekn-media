<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWitnessUploadRequest;
use App\Models\NewsItemWitnessLink;
use App\Models\NewsItemWitnessSubmission;
use App\Support\WitnessPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WitnessPortalController extends Controller
{
    public function show(Request $request, string $token): View
    {
        App::setLocale(config('witness.locale', 'de'));
        $link = $this->resolveLink($token);
        $link->loadMissing('newsItem');
        session(['witness_form_started_at' => time()]);

        $newsItem = $link->newsItem;
        $title = Lang::get('witness.page_title', [], 'de');

        return view('witness.upload', [
            'link' => $link,
            'newsItem' => $newsItem,
            'pageTitle' => $title,
            'legalText' => Lang::get('witness.legal_text', [], 'de'),
            'legalVersion' => WitnessPortal::legalVersion(),
            'legalChecksum' => WitnessPortal::legalBodyChecksum(),
            'canUpload' => $link->acceptsUploads(),
        ]);
    }

    public function store(StoreWitnessUploadRequest $request, string $token): RedirectResponse
    {
        App::setLocale(config('witness.locale', 'de'));
        $link = $this->resolveLink($token);
        $link->loadMissing('newsItem');
        if (! $link->acceptsUploads()) {
            throw new NotFoundHttpException;
        }

        $files = $request->file('media', []);
        if (! is_array($files)) {
            $files = [];
        }
        $files = array_values(array_filter($files, fn ($f) => $f && $f->isValid()));
        if ($files === []) {
            return back()->withInput()->withErrors(['media' => 'Bitte mindestens eine gültige Datei auswählen.']);
        }

        $remaining = max(0, max(1, (int) $link->max_uploads) - $link->submissions()->count());
        if (count($files) > $remaining) {
            return back()->withInput()->withErrors([
                'media' => $remaining === 1
                    ? 'Mit diesem Link ist nur noch ein Upload möglich. Bitte nur eine Datei auswählen oder die Redaktion kontaktieren.'
                    : 'Mit diesem Link sind nur noch '.$remaining.' Uploads möglich. Bitte weniger Dateien auswählen oder die Redaktion kontaktieren.',
            ]);
        }

        $disk = (string) config('witness.disk', 'local');

        try {
            DB::transaction(function () use ($request, $link, $files, $disk): void {
                foreach ($files as $file) {
                    $ext = $this->resolveWitnessUploadExtension($file);

                    $submission = NewsItemWitnessSubmission::query()->create([
                        'news_item_witness_link_id' => $link->id,
                        'news_item_id' => $link->news_item_id,
                        'submitter_name' => $request->validated('submitter_name'),
                        'submitter_email' => $request->validated('submitter_email'),
                        'submitter_phone' => $request->validated('submitter_phone'),
                        'consent_terms' => true,
                        'consent_rights' => true,
                        'credit_anonymous' => $request->boolean('credit_anonymous'),
                        'witness_suggested_title' => $request->validated('witness_suggested_title') ?: null,
                        'consent_text_version' => WitnessPortal::legalVersion(),
                        'consent_body_hash' => WitnessPortal::legalBodyChecksum(),
                        'stored_disk' => $disk,
                        'stored_path' => null,
                        'original_filename' => $file->getClientOriginalName() ?: 'upload.'.$ext,
                        'mime' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                        'status' => NewsItemWitnessSubmission::STATUS_PENDING,
                    ]);

                    $dir = 'witness_submissions/'.$submission->id;
                    $path = $file->storeAs($dir, 'original.'.$ext, ['disk' => $disk]);
                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('witness_store_failed');
                    }

                    $submission->update(['stored_path' => $path]);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['media' => 'Upload fehlgeschlagen. Bitte versuchen Sie es erneut oder kontaktieren Sie die Redaktion.']);
        }

        session()->forget('witness_form_started_at');

        return redirect()
            ->route(WitnessPortal::routeNameForShow($request), ['token' => $token])
            ->with('status', Lang::get('witness.success', [], 'de'));
    }

    private function resolveLink(string $token): NewsItemWitnessLink
    {
        $hash = NewsItemWitnessLink::hashToken($token);
        $link = NewsItemWitnessLink::query()
            ->with('newsItem')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $link) {
            throw new NotFoundHttpException;
        }

        return $link;
    }

    private function resolveWitnessUploadExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || strlen($ext) > 12 || ! preg_match('/^[a-z0-9]+$/', $ext)) {
            $ext = match ($file->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'video/mp4' => 'mp4',
                'video/quicktime' => 'mov',
                'audio/mpeg' => 'mp3',
                'audio/mp4', 'audio/x-m4a' => 'm4a',
                'audio/wav', 'audio/x-wav' => 'wav',
                'audio/webm' => 'webm',
                default => 'bin',
            };
        }

        return $ext;
    }
}

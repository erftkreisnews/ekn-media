<?php

namespace App\Http\Controllers;

use App\Models\MediaPublicationFinding;
use App\Models\PublicationFindingAuthorityDownload;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicationFindingAuthorityController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $finding = $this->resolveFinding($token);
        $passwordRequired = (string) ($finding->authority_access_password ?? '') !== '';
        $passwordOk = ! $passwordRequired || $request->session()->get($this->sessionKey($token)) === true;

        return view('publication-finding-authority.show', [
            'finding' => $finding,
            'passwordRequired' => $passwordRequired,
            'passwordOk' => $passwordOk,
            'downloadUrl' => $passwordOk
                ? \URL::temporarySignedRoute(
                    'publication-finding.authority.download',
                    now()->addMinutes((int) config('publication_evidence.authority_download_url_minutes', 60)),
                    ['token' => $token]
                )
                : null,
        ]);
    }

    public function verifyPassword(Request $request, string $token): RedirectResponse
    {
        $finding = $this->resolveFinding($token);
        $password = (string) $request->input('password', '');

        if ($password === '' || ! Hash::check($password, (string) $finding->authority_access_password)) {
            return back()->with('error', 'Zugangscode ist ungültig.');
        }

        $request->session()->put($this->sessionKey($token), true);

        return redirect()->route('publication-finding.authority.show', ['token' => $token]);
    }

    public function download(Request $request, string $token, MediaStorage $mediaStorage): StreamedResponse
    {
        $finding = $this->resolveFinding($token);

        if ((string) ($finding->authority_access_password ?? '') !== ''
            && $request->session()->get($this->sessionKey($token)) !== true) {
            abort(403, 'Zugangscode erforderlich.');
        }

        $path = (string) ($finding->evidence_dossier_path ?? '');
        if ($path === '' || ! $mediaStorage->exists($path)) {
            abort(404, 'Beweismittelmappe ist noch nicht verfügbar.');
        }

        $this->recordDownload($finding, $request);

        $filename = sprintf(
            'Beweismittelmappe-Fundstelle-%d-%s.zip',
            $finding->id,
            now()->format('Ymd')
        );

        return $mediaStorage->activeDisk()->download($path, $filename);
    }

    private function resolveFinding(string $token): MediaPublicationFinding
    {
        $finding = MediaPublicationFinding::query()
            ->where('authority_access_token', $token)
            ->first();

        if ($finding === null) {
            abort(404, 'Zugang nicht gefunden.');
        }

        if ($finding->authority_access_revoked_at !== null) {
            abort(410, 'Dieser Behördenzugang wurde widerrufen.');
        }

        if ($finding->authority_access_expires_at !== null
            && $finding->authority_access_expires_at->isPast()) {
            abort(410, 'Dieser Behördenzugang ist abgelaufen.');
        }

        return $finding;
    }

    private function recordDownload(MediaPublicationFinding $finding, Request $request): void
    {
        PublicationFindingAuthorityDownload::query()->create([
            'media_publication_finding_id' => $finding->id,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'ua_hash' => hash_hmac('sha256', (string) $request->userAgent(), (string) config('app.key')),
            'recipient_label' => $finding->authorityRecipientDisplay(),
        ]);
    }

    private function sessionKey(string $token): string
    {
        return 'publication_finding_authority_password_ok_'.$token;
    }
}

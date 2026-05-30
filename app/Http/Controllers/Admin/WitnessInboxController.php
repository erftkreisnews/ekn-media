<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Models\NewsItemWitnessLink;
use App\Models\NewsItemWitnessSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WitnessInboxController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $newsChoices = NewsItem::query()
            ->select(['id', 'title', 'author_id', 'created_at'])
            ->when(
                ! $user?->hasRole('admin'),
                fn ($q) => $q->where('author_id', $user?->id)
            )
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $globalLinks = NewsItemWitnessLink::query()
            ->whereNull('news_item_id')
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $unassignedSubmissions = NewsItemWitnessSubmission::query()
            ->whereNull('news_item_id')
            ->with('link')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.witness.inbox', [
            'newsChoices' => $newsChoices,
            'globalLinks' => $globalLinks,
            'unassignedSubmissions' => $unassignedSubmissions,
        ]);
    }

    public function storeLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:191'],
            'expires_in_days' => ['nullable', 'integer', 'in:7,14,30,90'],
            'max_uploads' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $plain = Str::random(48);

        NewsItemWitnessLink::query()->create([
            'news_item_id' => null,
            'created_by_user_id' => $request->user()?->id,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'label' => $data['label'] ?? null,
            'expires_at' => isset($data['expires_in_days'])
                ? now()->addDays((int) $data['expires_in_days'])
                : null,
            'max_uploads' => isset($data['max_uploads']) ? (int) $data['max_uploads'] : 50,
        ]);

        return redirect()
            ->route('admin.witness.inbox')
            ->with([
                'status' => 'Allgemeiner Zeugen-Link erzeugt. Die vollständige URL wird unten einmalig angezeigt.',
                'witness_plain_token' => $plain,
            ]);
    }

    public function destroyLink(NewsItemWitnessLink $link): RedirectResponse
    {
        abort_unless($link->news_item_id === null, 404);

        $link->update(['revoked_at' => now()]);

        return redirect()
            ->route('admin.witness.inbox')
            ->with('status', 'Allgemeiner Upload-Link wurde zurückgezogen.');
    }

    public function preview(NewsItemWitnessSubmission $submission): Response
    {
        abort_unless($submission->news_item_id === null, 404);

        $path = $submission->stored_path;
        $diskName = $submission->stored_disk;
        if (! is_string($path) || $path === '' || ! is_string($diskName) || $diskName === '') {
            abort(404);
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            abort(404);
        }

        $name = basename((string) ($submission->original_filename ?: $path)) ?: 'datei';

        return $disk->response($path, $name, [
            'Content-Type' => $submission->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addcslashes($name, '"\\').'"',
        ]);
    }

    public function download(NewsItemWitnessSubmission $submission): StreamedResponse
    {
        abort_unless($submission->news_item_id === null, 404);

        $path = $submission->stored_path;
        $diskName = $submission->stored_disk;
        if (! is_string($path) || $path === '' || ! is_string($diskName) || $diskName === '') {
            abort(404);
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            abort(404);
        }

        $name = $submission->original_filename ?: basename($path);

        return $disk->download($path, $name);
    }

    public function assign(Request $request, NewsItemWitnessSubmission $submission): RedirectResponse
    {
        abort_unless($submission->news_item_id === null, 404);

        $data = $request->validate([
            'news_item_id' => ['required', 'integer', 'exists:news_items,id'],
        ]);

        $newsItem = NewsItem::query()->findOrFail((int) $data['news_item_id']);
        $this->abortIfCannotManageNewsItem($newsItem);

        $submission->update([
            'news_item_id' => $newsItem->id,
        ]);

        return redirect()
            ->route('admin.news.edit', [
                'newsItem' => $newsItem,
                'tab' => 'witness',
            ])
            ->with('status', 'Einreichung wurde der Meldung zugeordnet. Sie kann jetzt im Zeugen-Tab übernommen oder abgelehnt werden.');
    }

    public function reject(NewsItemWitnessSubmission $submission): RedirectResponse
    {
        abort_unless($submission->news_item_id === null, 404);

        if ($submission->status !== NewsItemWitnessSubmission::STATUS_PENDING) {
            return redirect()
                ->route('admin.witness.inbox')
                ->with('error', 'Nur offene Einreichungen können abgelehnt werden.');
        }

        if ($submission->news_item_media_id !== null) {
            return redirect()
                ->route('admin.witness.inbox')
                ->with('error', 'Bereits übernommen.');
        }

        $submission->deleteStoredFile();
        $submission->update([
            'status' => NewsItemWitnessSubmission::STATUS_REJECTED,
            'stored_path' => null,
        ]);

        return redirect()
            ->route('admin.witness.inbox')
            ->with('status', 'Einreichung abgelehnt; die Datei wurde vom Zwischenspeicher entfernt.');
    }

    private function abortIfCannotManageNewsItem(NewsItem $newsItem): void
    {
        $user = auth()->user();
        if ($user && $user->hasRole('admin')) {
            return;
        }

        if ((int) $newsItem->author_id !== (int) auth()->id()) {
            abort(403, 'Sie dürfen nur eigene Beiträge bearbeiten.');
        }
    }
}

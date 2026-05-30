<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteWitnessSubmissionRequest;
use App\Models\NewsItem;
use App\Models\NewsItemWitnessLink;
use App\Models\NewsItemWitnessSubmission;
use App\Services\Witness\PromoteWitnessSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsWitnessLinkController extends Controller
{
    public function store(Request $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:191'],
            'expires_in_days' => ['nullable', 'integer', 'in:7,14,30,90'],
            'max_uploads' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $plain = Str::random(48);

        NewsItemWitnessLink::query()->create([
            'news_item_id' => $newsItem->id,
            'created_by_user_id' => $request->user()?->id,
            'token_hash' => NewsItemWitnessLink::hashToken($plain),
            'label' => $data['label'] ?? null,
            'expires_at' => isset($data['expires_in_days'])
                ? now()->addDays((int) $data['expires_in_days'])
                : null,
            'max_uploads' => isset($data['max_uploads']) ? (int) $data['max_uploads'] : 50,
        ]);

        return redirect()
            ->route('admin.news.edit', [
                'newsItem' => $newsItem,
                'tab' => 'witness',
            ])
            ->with([
                'status' => 'Zeugen-Link erzeugt. Die vollständige URL wird im Zeugen-Tab nur einmal angezeigt – bitte sofort kopieren.',
                'witness_plain_token' => $plain,
            ]);
    }

    public function destroy(Request $request, NewsItem $newsItem, NewsItemWitnessLink $link): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        abort_unless($link->news_item_id === $newsItem->id, 404);
        $link->update(['revoked_at' => now()]);

        return redirect()
            ->route('admin.news.edit', $newsItem)
            ->with('status', 'Upload-Link wurde zurückgezogen.');
    }

    public function preview(NewsItem $newsItem, NewsItemWitnessSubmission $submission): Response
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        abort_unless($submission->news_item_id === $newsItem->id, 404);

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

    public function download(NewsItem $newsItem, NewsItemWitnessSubmission $submission): StreamedResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        abort_unless($submission->news_item_id === $newsItem->id, 404);

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

    public function promote(
        PromoteWitnessSubmissionRequest $request,
        NewsItem $newsItem,
        NewsItemWitnessSubmission $submission,
        PromoteWitnessSubmissionService $promoteWitnessSubmissionService
    ): RedirectResponse {
        $this->abortIfCannotManageNewsItem($newsItem);

        abort_unless($submission->news_item_id === $newsItem->id, 404);

        try {
            $media = $promoteWitnessSubmissionService->promote($submission, $request->validated());

            return redirect()
                ->route('admin.news.media.edit', [$newsItem, $media])
                ->with('status', 'Zeugen-Material wurde in die Meldung übernommen und auf den Medien-Speicher (z. B. S3) übertragen. Bitte Bild/Untertitel prüfen.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', $e->getMessage());
        }
    }

    public function reject(NewsItem $newsItem, NewsItemWitnessSubmission $submission): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        abort_unless($submission->news_item_id === $newsItem->id, 404);

        if ($submission->status !== NewsItemWitnessSubmission::STATUS_PENDING) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Nur offene Einreichungen können abgelehnt werden.');
        }

        if ($submission->news_item_media_id !== null) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Bereits übernommen.');
        }

        $submission->deleteStoredFile();
        $submission->update([
            'status' => NewsItemWitnessSubmission::STATUS_REJECTED,
            'stored_path' => null,
        ]);

        return redirect()
            ->route('admin.news.edit', $newsItem)
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRunItem;
use App\Models\NewsItemMedia;
use App\Services\MediaStorage;
use App\Support\AdminMediaLibraryScope;
use App\Support\MediaKeywordNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    /**
     * Zentrale Video-Mediathek: Kacheln mit Vorschau, Detail-Overlay (wie Bild-Mediathek).
     */
    public function index(Request $request): View|StreamedResponse
    {
        $query = $this->videoLibraryQuery($request);

        if ($request->boolean('export')) {
            return $this->exportVideoLibraryCsv(clone $query);
        }

        $videos = $query->orderByDesc('id')->paginate(36)->withQueryString();

        $newsStatuses = [
            '' => 'Alle Meldungs-Status',
            'draft' => 'Entwurf',
            'review' => 'Review',
            'published' => 'ready',
            'archived' => 'Archiviert',
        ];

        $filterActive = $request->filled('news_status')
            || trim((string) $request->get('q', '')) !== '';

        $brandFilterLabel = AdminMediaLibraryScope::selectedBrandLabel($request);
        $totalVideosInDatabase = NewsItemMedia::query()
            ->where('type', 'video')
            ->whereHas('newsItem')
            ->count();

        return view('admin.video.index', compact(
            'videos',
            'newsStatuses',
            'filterActive',
            'brandFilterLabel',
            'totalVideosInDatabase',
        ));
    }

    public function show(Request $request, int $media): JsonResponse
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'video')
            ->findOrFail($media);

        $this->abortIfCannotAccessVideo($request, $medium);

        return response()->json($this->videoDetailPayload($medium));
    }

    public function update(Request $request, int $media): JsonResponse
    {
        $medium = NewsItemMedia::query()
            ->with('newsItem')
            ->where('type', 'video')
            ->findOrFail($media);

        $this->abortIfCannotAccessVideo($request, $medium);

        $validated = $request->validate([
            'image_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1800'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'media_keywords' => ['sometimes', 'nullable', 'string', 'max:512'],
            'photographer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata_location' => ['sometimes', 'nullable', 'string', 'max:512'],
            'metadata_recorded_at' => ['sometimes', 'nullable', 'date'],
            'versand' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $update = [];

        foreach (['image_title', 'caption', 'description', 'photographer'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = $validated[$field];
                $update[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }

        if (array_key_exists('media_keywords', $validated)) {
            $update['media_keywords'] = MediaKeywordNormalizer::normalizeCommaSeparatedString($validated['media_keywords']);
        }

        if (array_key_exists('metadata_location', $validated)) {
            $loc = $validated['metadata_location'];
            $update['metadata_location'] = is_string($loc) && trim($loc) !== '' ? trim($loc) : null;
        }

        if (array_key_exists('metadata_recorded_at', $validated)) {
            $recorded = $validated['metadata_recorded_at'];
            if ($recorded === null || $recorded === '') {
                $update['metadata_recorded_at'] = null;
            } elseif ($recorded instanceof \DateTimeInterface) {
                $update['metadata_recorded_at'] = Carbon::parse($recorded)->toDateString();
            } elseif (is_string($recorded) && trim($recorded) !== '') {
                try {
                    $update['metadata_recorded_at'] = Carbon::parse($recorded)->toDateString();
                } catch (\Throwable) {
                    $update['metadata_recorded_at'] = null;
                }
            }
        }

        foreach (['versand', 'is_visible'] as $boolField) {
            if ($request->has($boolField)) {
                $update[$boolField] = $request->boolean($boolField);
            }
        }

        if ($update !== []) {
            $medium->update($update);
            $medium->refresh();
            $medium->load('newsItem');
        }

        return response()->json([
            'ok' => true,
            'detail' => $this->videoDetailPayload($medium),
        ]);
    }

    /**
     * @return Builder<NewsItemMedia>
     */
    private function videoLibraryQuery(Request $request): Builder
    {
        $q = NewsItemMedia::query()
            ->where('type', 'video')
            ->whereHas('newsItem')
            ->with(['newsItem']);

        AdminMediaLibraryScope::applyAuthorScope($q, $request);
        AdminMediaLibraryScope::applyBrandScope($q, $request, AdminMediaLibraryScope::selectedBrandId($request));

        if ($request->filled('news_status')) {
            $q->whereHas('newsItem', function (Builder $b) use ($request): void {
                $b->where('status', $request->string('news_status'));
            });
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search): void {
                $b->where('original_name', 'like', '%'.$search.'%')
                    ->orWhere('image_title', 'like', '%'.$search.'%')
                    ->orWhere('caption', 'like', '%'.$search.'%')
                    ->orWhereHas('newsItem', function (Builder $n) use ($search): void {
                        $n->where('title', 'like', '%'.$search.'%');
                    });
                if (ctype_digit($search)) {
                    $b->orWhere('id', (int) $search)
                        ->orWhere('news_item_id', (int) $search);
                }
            });
        }

        return $q;
    }

    /**
     * @param  Builder<NewsItemMedia>  $query
     */
    private function exportVideoLibraryCsv(Builder $query): StreamedResponse
    {
        $filename = 'video-mediathek-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'media_id',
                'news_id',
                'news_title',
                'news_status',
                'original_name',
                'duration_s',
                'updated_at',
            ]);

            $query->orderBy('id')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $m) {
                    /** @var NewsItemMedia $m */
                    fputcsv($out, [
                        $m->id,
                        $m->news_item_id,
                        $m->newsItem?->title,
                        $m->newsItem?->status,
                        $m->original_name,
                        $m->duration_s,
                        $m->updated_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function videoDetailPayload(NewsItemMedia $medium): array
    {
        $newsItem = $medium->newsItem;
        $keywords = collect(explode(',', (string) ($medium->media_keywords ?? '')))
            ->map(fn (string $k) => trim($k))
            ->filter()
            ->values()
            ->all();

        $fileSizeKb = $medium->file_size_kb;
        $fileSizeLabel = $fileSizeKb >= 1024
            ? number_format($fileSizeKb / 1024, 2, ',', '.').' MB'
            : number_format($fileSizeKb, 0, ',', '.').' KB';

        $path = (string) ($medium->path ?? $medium->original_path ?? '');
        $format = '—';
        if ($path !== '') {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $format = $ext !== '' ? strtoupper($ext) : '—';
        }

        $durationLabel = null;
        if ($medium->duration_s) {
            $totalSeconds = (int) round((float) $medium->duration_s);
            $durationLabel = sprintf('%d:%02d min', intdiv($totalSeconds, 60), $totalSeconds % 60);
        }

        $playbackUrl = $this->resolvePlaybackUrl($medium);
        $posterUrl = $medium->preview_url;

        return [
            'id' => $medium->id,
            'original_name' => $medium->original_name,
            'image_title' => $medium->image_title,
            'headline' => $medium->image_title ?: $medium->caption,
            'caption' => $medium->caption,
            'description' => $medium->description,
            'photographer' => $medium->photographer ?: $medium->credit,
            'media_keywords' => $medium->media_keywords,
            'keywords' => $keywords,
            'metadata_location' => $medium->metadata_location,
            'metadata_recorded_at' => $medium->metadata_recorded_at?->format('Y-m-d'),
            'metadata_recorded_at_label' => $medium->metadata_recorded_at?->format('d.m.Y'),
            'duration_label' => $durationLabel,
            'created_at_label' => $medium->created_at?->format('d.m.Y, H:i').' Uhr',
            'updated_at_label' => $medium->updated_at?->format('d.m.Y, H:i').' Uhr',
            'preview_url' => $posterUrl,
            'poster_url' => $posterUrl,
            'playback_url' => $playbackUrl,
            'file_size_label' => $fileSizeLabel,
            'format' => $format,
            'flags' => $this->videoProcessingFlags($medium),
            'status' => [
                'versand' => (bool) $medium->versand,
                'is_visible' => (bool) $medium->is_visible,
                'ai_status' => $medium->ai_status,
            ],
            'news_item' => $newsItem ? [
                'id' => $newsItem->id,
                'title' => $newsItem->title,
                'status' => $newsItem->status,
                'location' => Schema::hasColumn('news_items', 'location_chip_label')
                    ? $newsItem->location_chip_label
                    : null,
                'edit_url' => route('admin.news.edit', $newsItem),
            ] : null,
            'urls' => [
                'edit' => $newsItem ? route('admin.news.media.edit', [$newsItem, $medium->id]) : null,
                'update' => route('admin.video.update', $medium->id),
                'quick_send' => $newsItem ? route('admin.news.media.quick-send', [$newsItem, $medium->id]) : null,
                'publication_finding' => route('admin.backoffice.publication-findings.create', [
                    'news_item_media_id' => $medium->id,
                    'news_item_id' => $newsItem?->id,
                ]),
            ],
        ];
    }

    private function resolvePlaybackUrl(NewsItemMedia $medium): ?string
    {
        $newsItem = $medium->newsItem;
        if ($newsItem === null) {
            return null;
        }

        $routePlayback = route('admin.news.media.playback', [$newsItem, $medium->id]);
        $rel = $medium->resolveDeliveryDownloadRelativePath();
        if ($rel && config('media_storage.prefer_presigned_streaming', true)) {
            $presigned = app(MediaStorage::class)->temporaryPlaybackUrlForPath($rel, now()->addMinutes(120));
            if (is_string($presigned) && $presigned !== '') {
                return $presigned;
            }
        }

        return $routePlayback;
    }

    /**
     * @return array<string, bool>
     */
    private function videoProcessingFlags(NewsItemMedia $medium): array
    {
        $hasFtp = Schema::hasTable('delivery_run_items')
            && DeliveryRunItem::query()->where('news_item_media_id', $medium->id)->exists();

        $aiDone = in_array((string) ($medium->ai_status ?? ''), ['done', 'completed', 'success'], true);
        $hasMeta = filled($medium->image_title) || filled($medium->caption) || filled($medium->media_keywords);

        return [
            'processed' => filled($medium->path),
            'ai_processed' => $aiDone,
            'metadata_written' => $hasMeta,
            'ftp_sent' => $hasFtp,
            'public' => (bool) $medium->is_visible && $medium->public_path !== null,
        ];
    }

    private function abortIfCannotAccessVideo(Request $request, NewsItemMedia $medium): void
    {
        $user = $request->user();
        if (AdminMediaLibraryScope::canBrowseAllMedia($user)) {
            return;
        }

        $authorId = (int) ($medium->newsItem?->author_id ?? 0);
        if ($authorId > 0 && $authorId === (int) $user?->id) {
            return;
        }

        abort(403, 'Sie dürfen dieses Video nicht bearbeiten.');
    }
}

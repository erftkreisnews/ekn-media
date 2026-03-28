<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsItemRequest;
use App\Http\Requests\UpdateNewsItemRequest;
use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\GenerateImageMetadata;
use App\Jobs\GenerateNewsMediaPreview;
use App\Jobs\GenerateVideoPoster;
use App\Jobs\ProcessMediaRedaction;
use App\Models\NewsItem;
use App\Models\NewsItemMedia;
use App\Services\ImageMetadataReader;
use App\Services\ImageMetadataWriter;
use App\Services\MediaQualityCheck;
use App\Services\MediaStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsItemController extends Controller
{
    public function index()
    {
        $newsItems = NewsItem::query()
            ->with(['author', 'images', 'videos'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('admin.news.index', compact('newsItems'));
    }

    public function create()
    {
        $statuses = $this->statuses();

        return view('admin.news.create', compact('statuses'));
    }

    public function store(StoreNewsItemRequest $request)
    {
        $data = $request->safe()->except(['images', 'videos', 'audios']);
        $data['author_id'] = auth()->id();

        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $newsItem = NewsItem::create($data);

        $this->processMediaUploads($newsItem, $request);

        return redirect()
            ->route('admin.news.edit', $newsItem)
            ->with('status', 'Die Nachricht wurde erstellt.');
    }

    public function edit(NewsItem $newsItem)
    {
        $statuses = $this->statuses();

        return view('admin.news.edit', compact('newsItem', 'statuses'));
    }

    /**
     * Streamt Video/Audio für die Admin-Bearbeitung über dieselbe Origin wie die App.
     * Direkte S3-/Object-Storage-URLs in &lt;video&gt;/&lt;audio&gt; scheitern oft (CORS, Range, private Buckets).
     */
    public function playbackMedia(Request $request, NewsItem $newsItem, int $mediaId): StreamedResponse|BinaryFileResponse
    {
        $medium = $newsItem->media()->where('id', $mediaId)->firstOrFail();
        if (! in_array($medium->type, ['video', 'audio'], true)) {
            abort(404);
        }
        $path = $medium->path;
        if ($path === null || $path === '') {
            abort(404);
        }

        $mediaStorage = app(MediaStorage::class);
        $disk = $mediaStorage->activeDisk();
        if (! $disk->exists($path) && $mediaStorage->fallbackDiskName() !== $mediaStorage->activeDiskName()) {
            $disk = $mediaStorage->fallbackDisk();
        }
        if (! $disk->exists($path)) {
            abort(404);
        }

        $filename = $medium->original_name ?: basename($path);

        if ($request->boolean('download')) {
            return $disk->download($path, $filename);
        }

        return $disk->response($path, $filename, [
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function update(UpdateNewsItemRequest $request, NewsItem $newsItem)
    {
        $data = $request->safe()->except(['images', 'videos', 'audios', 'delete_media', 'media', 'teaser_media_id', 'publish_and_save', 'no_wdr_job']);
        if ($request->has('publish_and_save')) {
            $data['status'] = 'published';
            $data['published_at'] = $data['published_at'] ?? now();
        }
        $data['is_wdr_job'] = ! $request->boolean('no_wdr_job');
        $newsItem->update($data);

        if ($ids = $request->validated('delete_media')) {
            $media = $newsItem->media()->whereIn('id', $ids)->get();
            foreach ($media as $m) {
                $m->delete();
            }
        }

        if ($mediaData = $request->validated('media')) {
            foreach ($mediaData as $id => $attrs) {
                $update = [
                    'is_visible' => isset($attrs['is_visible']) ? (string) $attrs['is_visible'] !== '0' && (string) $attrs['is_visible'] !== 'false' : true,
                    'versand' => ! empty($attrs['versand']),
                ];
                if (array_key_exists('caption', $attrs)) {
                    $update['caption'] = $attrs['caption'] ?? null;
                }
                $newsItem->media()->where('id', $id)->update($update);
                $medium = $newsItem->media()->find($id);
                if ($medium && $medium->isImage() && $medium->path) {
                    $resolved = app(MediaStorage::class)->resolveReadableLocalPath($medium->path);
                    $fullPath = $resolved['path'] ?? null;
                    if (is_string($fullPath) && is_file($fullPath)) {
                        \App\Services\ImageMetadataWriter::write($fullPath, [
                            'image_title' => $medium->image_title,
                            'photographer' => $medium->photographer,
                            'caption' => $medium->caption,
                            'credit' => config('newsdesk.iptc_credit', 'Erftkreis News'),
                            'copyright' => config('newsdesk.iptc_copyright', '© Erftkreis News. Alle Rechte vorbehalten.'),
                        ]);
                    }
                    app(MediaStorage::class)->cleanupResolvedPath($resolved);
                }
            }
        }

        // Pro Beitrag genau ein Teaser-Bild: alle zurücksetzen, dann den gewählten setzen
        $newsItem->images()->update(['is_teaser' => false]);
        $teaserId = $request->validated('teaser_media_id');
        if ($teaserId && $newsItem->media()->where('id', $teaserId)->where('type', 'image')->exists()) {
            $newsItem->media()->where('id', $teaserId)->update(['is_teaser' => true]);
        } elseif ($newsItem->images()->exists() && ! $teaserId) {
            // Falls kein Teaser gewählt: erstes Bild als Teaser setzen
            $newsItem->images()->orderBy('sort_order')->first()?->update(['is_teaser' => true]);
        }

        $this->processMediaUploads($newsItem, $request);

        $message = $request->has('publish_and_save')
            ? 'Die Nachricht wurde gespeichert und veröffentlicht.'
            : 'Die Nachricht wurde aktualisiert.';

        return redirect()
            ->route('admin.news.edit', $newsItem)
            ->with('status', $message);
    }

    public function destroy(NewsItem $newsItem)
    {
        $newsItem->delete();

        return redirect()
            ->route('admin.news.index')
            ->with('status', 'Die Nachricht wurde gelöscht.');
    }

    public function destroyMedia(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        $type = (string) $medium->type;
        $medium->delete();

        $tab = match ($type) {
            'image' => 'bilder',
            'video' => 'videos',
            'audio' => 'audios',
            default => 'nachricht',
        };

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab='.$tab)
            ->with('status', 'Medium wurde gelöscht.');
    }

    public function unlinkMedia(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        $type = (string) $medium->type;
        $medium->delete();

        $tab = match ($type) {
            'image' => 'bilder',
            'video' => 'videos',
            'audio' => 'audios',
            default => 'nachricht',
        };

        return redirect()
            ->to(route('admin.news.edit', $newsItem).'?tab='.$tab)
            ->with('status', 'Verknüpfung wurde entfernt.');
    }

    public function editMedia(NewsItem $newsItem, int $mediaId)
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        $metadataFromFile = [];
        if ($medium->isImage()) {
            $resolved = app(MediaStorage::class)->resolveReadableLocalPath($medium->path);
            $path = $resolved['path'] ?? null;
            $metadataFromFile = is_string($path) ? ImageMetadataReader::read($path) : [];
            app(MediaStorage::class)->cleanupResolvedPath($resolved);
        }

        $galleryImages = $newsItem->images->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->url,
            'display_name' => $m->display_name,
        ])->values();
        $galleryIndex = $galleryImages->search(fn ($m) => $m['id'] === $medium->id);
        if ($galleryIndex === false) {
            $galleryIndex = 0;
        }

        $imagesOrdered = $newsItem->images->values();
        $currentPos = $imagesOrdered->search(fn ($m) => $m->id === $medium->id);
        $prevMedia = $currentPos > 0 ? $imagesOrdered->get($currentPos - 1) : null;
        $nextMedia = $currentPos !== false && $currentPos < $imagesOrdered->count() - 1 ? $imagesOrdered->get($currentPos + 1) : null;

        return view('admin.news.media.edit', compact('newsItem', 'medium', 'metadataFromFile', 'galleryImages', 'galleryIndex', 'prevMedia', 'nextMedia'));
    }

    public function updateMedia(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        $validated = $request->validate([
            'image_title' => ['nullable', 'string', 'max:255'],
            'photographer' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1800'],
            'media_keywords' => ['nullable', 'string', 'max:512'],
            'description' => ['nullable', 'string', 'max:65535'],
            'is_visible' => ['sometimes'],
            'versand' => ['sometimes'],
        ]);
        $update = [
            'image_title' => $validated['image_title'] ?? null,
            'photographer' => isset($validated['photographer']) && trim((string) $validated['photographer']) !== '' ? trim($validated['photographer']) : null,
            'caption' => $validated['caption'] ?? null,
            'media_keywords' => $validated['media_keywords'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_visible' => $request->boolean('is_visible'),
            'versand' => $request->boolean('versand'),
        ];
        $medium->update($update);

        if ($medium->isImage() && ! empty($medium->path)) {
            app(MediaQualityCheck::class)->runAndSave($medium);
            try {
                $resolved = app(MediaStorage::class)->resolveReadableLocalPath($medium->path);
                $fullPath = $resolved['path'] ?? null;
                if (is_string($fullPath) && is_file($fullPath)) {
                    ImageMetadataWriter::write($fullPath, [
                        'image_title' => $medium->image_title,
                        'photographer' => $medium->photographer,
                        'caption' => $medium->caption,
                        'credit' => config('newsdesk.iptc_credit', 'Erftkreis News'),
                        'copyright' => config('newsdesk.iptc_copyright', '© Erftkreis News. Alle Rechte vorbehalten.'),
                    ]);
                }
                app(MediaStorage::class)->cleanupResolvedPath($resolved);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $redirectAfter = $request->input('redirect_after');
        if ($redirectAfter && is_string($redirectAfter) && trim($redirectAfter) !== '') {
            $url = trim($redirectAfter);
            $allowedHost = parse_url(config('app.url', ''), PHP_URL_HOST);
            $redirectHost = parse_url($url, PHP_URL_HOST);
            if ($redirectHost === $allowedHost || $redirectHost === null) {
                return redirect($url);
            }
        }

        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', 'Metadaten wurden gespeichert.');
    }

    public function mediaAiStatus(NewsItem $newsItem, int $mediaId): JsonResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);

        return response()->json([
            'ai_status' => $medium->ai_status ?? null,
            'ai_last_error' => $medium->ai_last_error ?? $medium->ai_error ?? null,
            'ai_finished_at' => $medium->ai_finished_at?->toIso8601String(),
        ]);
    }

    public function requestMediaAi(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        if ($medium->type !== 'image') {
            return redirect()
                ->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'KI-Vorschlag ist nur für Bilder möglich.');
        }

        try {
            $medium->markAiQueued();
            GenerateImageMetadata::dispatch($medium);
        } catch (QueryException $e) {
            Log::warning('requestMediaAi: AI columns missing, continue without AI', [
                'media_id' => $medium->id,
                'path' => $medium->path,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', 'KI-Analyse wurde gestartet. Bitte die Seite in Kürze neu laden.');
    }

    /**
     * Einstieg „Unkenntlich machen“ → einheitlich zur Anonymisierungs-Karte auf der Bearbeitungsseite.
     * Es gibt nur noch ein System: Redaction (Kennzeichen + Gesichter + manuelle Boxen).
     */
    public function showUnkenntlichEditor(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->withFragment('redaction')
            ->with('status', 'Nutzen Sie die Karte „Bereiche unkenntlich machen“ für Kennzeichen, Gesichter und weitere Bereiche.');
    }

    /**
     * Legacy: POST vom alten „Unkenntlich“-Formular.
     * Nutzt jetzt die Redaction-Pipeline (Original bleibt erhalten, redigierte Version wird erzeugt).
     */
    public function applyUnkenntlich(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        $regions = $request->input('regions');
        if (is_string($regions)) {
            $regions = json_decode($regions, true) ?: [];
        }
        $request->merge(['regions' => $regions]);
        $request->validate([
            'regions' => ['required', 'array', 'min:1'],
            'regions.*.x' => ['required', 'numeric', 'min:0', 'max:100'],
            'regions.*.y' => ['required', 'numeric', 'min:0', 'max:100'],
            'regions.*.w' => ['required', 'numeric', 'min:1', 'max:100'],
            'regions.*.h' => ['required', 'numeric', 'min:1', 'max:100'],
            'mode' => ['nullable', 'string', 'in:blur,pixelate'],
        ]);
        $mode = $request->input('mode', 'blur');
        $redactionMethod = $mode === 'pixelate' ? 'black_box' : 'blur';

        $resolved = app(MediaStorage::class)->resolveReadableLocalPath($medium->path);
        $fullPath = $resolved['path'] ?? null;
        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->withFragment('redaction')
                ->with('status', 'Bilddatei nicht lesbar. Bitte Anonymisierung auf der Bearbeitungsseite nutzen.');
        }
        $width = 0;
        $height = 0;
        $info = @getimagesize($fullPath);
        if ($info && isset($info[0], $info[1])) {
            $width = (int) $info[0];
            $height = (int) $info[1];
        }
        if ($width < 2 || $height < 2) {
            app(MediaStorage::class)->cleanupResolvedPath($resolved);

            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->withFragment('redaction')
                ->with('status', 'Bildabmessungen konnten nicht gelesen werden. Bitte Anonymisierung auf der Bearbeitungsseite nutzen.');
        }

        $boxes = [];
        foreach ($regions as $r) {
            $x1 = (float) ($r['x'] / 100) * $width;
            $y1 = (float) ($r['y'] / 100) * $height;
            $x2 = (float) (($r['x'] + $r['w']) / 100) * $width;
            $y2 = (float) (($r['y'] + $r['h']) / 100) * $height;
            if ($x2 > $x1 && $y2 > $y1) {
                $boxes[] = [round($x1, 2), round($y1, 2), round($x2, 2), round($y2, 2)];
            }
        }
        if ($boxes === []) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->withFragment('redaction')
                ->with('status', 'Keine gültigen Bereiche. Bitte auf der Bearbeitungsseite Boxen setzen und „Redaction neu rendern“.');
        }

        $medium->update([
            'redaction_boxes' => $boxes,
            'redaction_method' => $redactionMethod,
            'redaction_status' => NewsItemMedia::REDACTION_PENDING,
            'is_unkentlich' => true,
        ]);
        ProcessMediaRedaction::dispatch($medium);
        app(MediaStorage::class)->cleanupResolvedPath($resolved);

        return redirect()
            ->route('admin.news.media.edit', [$newsItem, $medium])
            ->withFragment('redaction')
            ->with('status', 'Anonymisierung in die Warteschlange gestellt. Seite in Kürze neu laden. Original bleibt erhalten; ausgeliefert wird die redigierte Version.');
    }

    public function toggleUnkenntlich(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        $medium->update(['is_unkentlich' => false]);

        return redirect()
            ->route('admin.news.edit', $newsItem)
            ->with('status', 'Markierung „Unkenntlich“ entfernt.');
    }

    public function runRedaction(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        $medium->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
        ProcessMediaRedaction::dispatch($medium);

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', 'Auto-Redaction wurde in die Warteschlange gestellt. Seite in Kürze neu laden.');
    }

    /**
     * Redaction-Job sofort ausführen (ohne Queue). Kann bis zu 2 Min. dauern.
     */
    public function runRedactionNow(NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        set_time_limit(150);
        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }
        $medium->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
        ProcessMediaRedaction::dispatchSync($medium);
        $medium->refresh();
        $status = $medium->redaction_status === NewsItemMedia::REDACTION_DONE
            ? 'Redaction abgeschlossen.'
            : 'Redaction beendet. Status und ggf. Fehlergrund oben prüfen.';

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', $status);
    }

    public function updateRedaction(Request $request, NewsItem $newsItem, int $mediaId): RedirectResponse
    {
        $medium = $newsItem->media()->findOrFail($mediaId);
        if (! $medium->isImage()) {
            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', 'Nur bei Bildern möglich.');
        }

        // Keine Anonymisierung (z. B. Feuerwehr, Polizei): nur Status umschalten
        if ($request->has('redaction_disabled')) {
            $disabled = $request->boolean('redaction_disabled');
            $medium->update(['redaction_status' => $disabled ? NewsItemMedia::REDACTION_DISABLED : NewsItemMedia::REDACTION_PENDING]);

            return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
                ->with('status', $disabled
                    ? 'Kennzeichen-Anonymisierung deaktiviert. Bild wird unverändert (Original) ausgeliefert.'
                    : 'Kennzeichen-Anonymisierung wieder aktiviert. Bei Bedarf „Redaction jetzt ausführen“ klicken.');
        }

        $boxes = $request->input('boxes');
        if (is_string($boxes)) {
            $boxes = json_decode($boxes, true) ?: [];
        }
        $request->merge(['boxes' => $boxes]);
        $request->validate([
            'boxes' => ['nullable', 'array'],
            'boxes.*' => ['array'],
            'boxes.*.0' => ['numeric', 'min:0'],
            'boxes.*.1' => ['numeric', 'min:0'],
            'boxes.*.2' => ['numeric', 'min:0'],
            'boxes.*.3' => ['numeric', 'min:0'],
            'method' => ['nullable', 'string', 'in:blur,black_box'],
        ]);
        $method = $request->input('method') ?: $medium->redaction_method ?: config('redaction.default_method', 'blur');
        $normalized = [];
        foreach ($boxes ?? [] as $b) {
            if (is_array($b) && isset($b[0], $b[1], $b[2], $b[3])) {
                $normalized[] = [(float) $b[0], (float) $b[1], (float) $b[2], (float) $b[3]];
            }
        }
        $medium->update([
            'redaction_boxes' => $normalized,
            'redaction_method' => $method,
        ]);
        $runAfter = $request->boolean('run_after');
        if ($runAfter) {
            $medium->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
            ProcessMediaRedaction::dispatch($medium);
        }

        return redirect()->route('admin.news.media.edit', [$newsItem, $medium])
            ->with('status', $runAfter ? 'Boxen gespeichert und Redaction gestartet.' : 'Boxen und Methode gespeichert.');
    }

    /**
     * Autor-Credit der Meldung auf alle Bild-Medien (photographer) übernehmen.
     * Aktualisiert DB und schreibt Credit in die Bilddateien (Original, ggf. redigierte Version).
     */
    public function applyAuthorCredit(NewsItem $newsItem): RedirectResponse
    {
        $credit = trim((string) ($newsItem->author_credit ?? ''));
        if ($credit === '') {
            return redirect()->route('admin.news.edit', $newsItem)
                ->with('error', 'Kein Autor-Credit an der Meldung hinterlegt. Bitte zuerst bei der Meldung unter „Autor / Credit“ eintragen und Meldung speichern.');
        }
        $newsItem->images()->update(['photographer' => $credit]);

        foreach ($newsItem->images as $medium) {
            if (empty($medium->path)) {
                continue;
            }
            try {
                $pathToWrite = $medium->public_path ?: $medium->path;
                $resolved = app(MediaStorage::class)->resolveReadableLocalPath($pathToWrite);
                $fullPath = $resolved['path'] ?? null;
                if (is_string($fullPath) && is_file($fullPath)) {
                    ImageMetadataWriter::write($fullPath, [
                        'image_title' => $medium->image_title,
                        'photographer' => $credit,
                        'caption' => $medium->caption,
                        'credit' => config('newsdesk.iptc_credit', 'Erftkreis News'),
                        'copyright' => config('newsdesk.iptc_copyright', '© Erftkreis News. Alle Rechte vorbehalten.'),
                    ]);
                }
                app(MediaStorage::class)->cleanupResolvedPath($resolved);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.news.edit', $newsItem)
            ->with('status', 'Autor-Credit wurde auf alle '.$newsItem->images->count().' Bilder übernommen (Datenbank und Datei-Metadaten).');
    }

    protected function statuses(): array
    {
        return [
            'draft' => 'Entwurf',
            'review' => 'Review',
            'published' => 'ready',
            'archived' => 'Archiviert',
        ];
    }

    /**
     * Speichert hochgeladene Medien. Dateien werden unter einer 6-stelligen Nummer (Media-ID)
     * abgelegt (z. B. 000042.jpg), damit die Speicherung einheitlich ist. Der Original-Dateiname
     * bleibt in original_name für Anzeige und Download erhalten.
     */
    protected function processMediaUploads(NewsItem $newsItem, Request $request): void
    {
        $sortOrder = $newsItem->media()->max('sort_order') ?? 0;
        $mediaStorage = app(MediaStorage::class);

        foreach (['images' => 'image', 'videos' => 'video', 'audios' => 'audio'] as $key => $type) {
            $files = $request->file($key);
            if (! $files) {
                continue;
            }
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if (! $file->isValid()) {
                    continue;
                }
                $sortOrder++;
                $originalName = $file->getClientOriginalName();
                $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
                if (! preg_match('/^[a-z0-9]+$/', $ext)) {
                    $ext = 'bin';
                }

                // Zuerst Datensatz anlegen, um die ID für den 6-stelligen Dateinamen zu haben
                $media = $newsItem->media()->create([
                    'type' => $type,
                    'path' => 'news-media/.pending',
                    'original_name' => $originalName,
                    'sort_order' => $sortOrder,
                ]);

                $path = $mediaStorage->generateMediaPath(
                    $newsItem,
                    $media,
                    $file,
                    $type === 'image' ? 'gallery' : $type
                );
                $storedPath = $mediaStorage->storeUploadedFileAs($file, dirname($path), basename($path));

                $media->update(['path' => $storedPath]);
                if ($type === 'image') {
                    $media->update(['redaction_status' => NewsItemMedia::REDACTION_PENDING]);
                    app(MediaQualityCheck::class)->runAndSave($media);
                    $previewPath = $mediaStorage->generateDerivedMediaPath($newsItem, $media, $file->getClientOriginalName().'.webp', 'preview');
                    $media->update(['preview_path' => $previewPath]);
                    $watermarkPath = public_path('images/erftkreis-news-logo.png');
                    if (is_file($watermarkPath)) {
                        // Preview/Thumb sofort erzeugen, damit Versand und Portalseite direkt funktionieren.
                        GenerateNewsMediaPreview::dispatchSync($mediaStorage->activeDiskName(), $storedPath, $previewPath, $watermarkPath);
                    }
                    try {
                        $media->markAiQueued();
                        GenerateImageMetadata::dispatch($media);
                    } catch (QueryException $e) {
                        Log::warning('processMediaUploads: AI columns missing, continue without AI', [
                            'media_id' => $media->id,
                            'path' => $media->path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    if ($media->redaction_status !== NewsItemMedia::REDACTION_DISABLED) {
                        ProcessMediaRedaction::dispatch($media);
                    }
                }
                if ($type === 'video') {
                    ExtractVideoMetadata::dispatch($media);
                    GenerateVideoPoster::dispatch($media);
                } elseif ($type === 'audio') {
                    ExtractAudioMetadata::dispatch($media);
                }
            }
        }
    }
}

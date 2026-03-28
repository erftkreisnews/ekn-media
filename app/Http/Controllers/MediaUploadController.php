<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMediaPipeline;
use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'news_item_id' => ['required', 'integer', 'exists:news_items,id'],
            'type' => ['nullable', 'string', Rule::in(['image', 'video', 'audio'])],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];
        $newsItemId = (int) $validated['news_item_id'];
        $type = $validated['type'] ?? $this->guessTypeFromMime($file->getMimeType());

        if (! in_array($type, ['image', 'video', 'audio'], true)) {
            return response()->json([
                'message' => 'Ungültiger oder nicht unterstützter Medientyp.',
            ], 422);
        }

        $newsItem = NewsItem::find($newsItemId);
        if (! $newsItem) {
            return response()->json([
                'message' => 'Nachricht nicht gefunden.',
            ], 404);
        }

        $originalName = $file->getClientOriginalName();
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || ! preg_match('/^[a-z0-9]+$/', $ext)) {
            $ext = 'bin';
        }

        $sortOrder = (int) MediaAsset::where('news_item_id', $newsItemId)->max('sort_order') ?: 0;
        $sortOrder++;

        $media = MediaAsset::create([
            'news_item_id' => $newsItemId,
            'type' => $type,
            'path' => 'news-media/.pending',
            'original_name' => $originalName,
            'sort_order' => $sortOrder,
        ]);

        $mediaStorage = app(MediaStorage::class);
        $path = $mediaStorage->generateMediaPath(
            $newsItem,
            $media,
            $file,
            $type === 'image' ? 'gallery' : $type
        );
        $storedPath = $mediaStorage->storeUploadedFileAs($file, dirname($path), basename($path));

        $media->update(['path' => $storedPath]);

        ProcessMediaPipeline::dispatch($media);

        return response()->json([
            'success' => true,
            'media_asset_id' => $media->id,
            'processing_status' => 'queued',
        ], 201);
    }

    private function guessTypeFromMime(?string $mime): ?string
    {
        if (! is_string($mime) || $mime === '') {
            return null;
        }

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        return null;
    }
}

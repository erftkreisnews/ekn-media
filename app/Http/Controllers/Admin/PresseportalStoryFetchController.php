<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Services\Presseportal\PresseportalStoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PresseportalStoryFetchController extends Controller
{
    public function __invoke(Request $request, NewsItem $newsItem, PresseportalStoryService $presseportal): JsonResponse
    {
        if (! Schema::hasTable('news_item_updates')) {
            return response()->json(['success' => false, 'message' => 'Updates sind nicht migriert.'], 503);
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $data = $presseportal->fetchByUrl($validated['url']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $data['title'],
                'body' => $data['body'],
                'happened_at' => $data['published_at']?->format('Y-m-d\TH:i'),
                'source_type' => $data['source_type'],
                'source_label' => $data['source_label'],
                'presseportal_url' => $validated['url'],
                'presseportal_story_id' => $data['story_id'],
                'presseportal_office_id' => $data['office_id'],
            ],
        ]);
    }
}

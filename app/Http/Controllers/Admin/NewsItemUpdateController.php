<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsItemUpdateRequest;
use App\Http\Requests\UpdateNewsItemUpdateRequest;
use App\Models\NewsItem;
use App\Models\NewsItemUpdate;
use App\Models\PresseportalOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NewsItemUpdateController extends Controller
{
    public function edit(NewsItem $newsItem, NewsItemUpdate $update): View|RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_updates')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Updates-Tabelle fehlt. Bitte zuerst Migrationen ausführen (php artisan migrate).');
        }
        if ((int) $update->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        $presseportalKeySet = ! empty(config('presseportal.api_key'));
        $presseportalWhitelistCount = 0;
        if (Schema::hasTable('presseportal_offices')) {
            $presseportalWhitelistCount = PresseportalOffice::query()->active()->count();
        }

        return view('admin.news.updates.edit', [
            'newsItem' => $newsItem,
            'update' => $update,
            'updateTypeOptions' => NewsItemUpdate::updateTypeOptions(),
            'updateSourceOptions' => NewsItemUpdate::sourceTypeOptions(),
            'presseportalKeySet' => $presseportalKeySet,
            'presseportalWhitelistCount' => $presseportalWhitelistCount,
        ]);
    }

    public function store(StoreNewsItemUpdateRequest $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_updates')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Updates sind noch nicht migriert. Bitte zuerst php artisan migrate ausführen.');
        }
        $data = $request->validated();

        $newsItem->updates()->create([
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'presseportal_url' => $data['presseportal_url'] ?? null,
            'presseportal_story_id' => $data['presseportal_story_id'] ?? null,
            'presseportal_office_id' => $data['presseportal_office_id'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_label' => $data['source_label'] ?? null,
            'happened_at' => $data['happened_at'] ?? null,
            'show_in_mail' => $request->boolean('show_in_mail', true),
            'show_in_article' => $request->boolean('show_in_article', false),
            'is_active' => $request->boolean('is_active', true),
            'statement_id' => $data['statement_id'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Update wurde gespeichert.');
    }

    public function update(UpdateNewsItemUpdateRequest $request, NewsItem $newsItem, NewsItemUpdate $update): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_updates')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Updates-Tabelle fehlt. Bitte zuerst Migrationen ausführen (php artisan migrate).');
        }
        if ((int) $update->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        $data = $request->validated();
        $update->update([
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'presseportal_url' => $data['presseportal_url'] ?? null,
            'presseportal_story_id' => $data['presseportal_story_id'] ?? null,
            'presseportal_office_id' => $data['presseportal_office_id'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_label' => $data['source_label'] ?? null,
            'happened_at' => $data['happened_at'] ?? null,
            'show_in_mail' => $request->boolean('show_in_mail', true),
            'show_in_article' => $request->boolean('show_in_article', false),
            'is_active' => $request->boolean('is_active', true),
            'statement_id' => $data['statement_id'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Update wurde aktualisiert.');
    }

    public function destroy(NewsItem $newsItem, NewsItemUpdate $update): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_updates')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Updates-Tabelle fehlt. Bitte zuerst Migrationen ausführen (php artisan migrate).');
        }
        if ((int) $update->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        $update->delete();

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Update wurde gelöscht.');
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

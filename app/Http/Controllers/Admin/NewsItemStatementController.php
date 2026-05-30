<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsItemStatementRequest;
use App\Http\Requests\UpdateNewsItemStatementRequest;
use App\Models\NewsItem;
use App\Models\NewsItemStatement;
use App\Models\NewsItemUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NewsItemStatementController extends Controller
{
    public function edit(NewsItem $newsItem, NewsItemStatement $statement): View|RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_statements')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Statements-Tabelle fehlt. Bitte zuerst Migrationen ausführen (php artisan migrate).');
        }
        if ((int) $statement->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        return view('admin.news.statements.edit', [
            'newsItem' => $newsItem,
            'statement' => $statement,
            'statementSourceOptions' => NewsItemStatement::sourceTypeOptions(),
            'statementTypeOptions' => NewsItemStatement::statementTypeOptions(),
        ]);
    }

    public function store(StoreNewsItemStatementRequest $request, NewsItem $newsItem): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_statements') || ! Schema::hasTable('news_item_updates')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Statements/Updates sind noch nicht migriert. Bitte zuerst php artisan migrate ausführen.');
        }
        $data = $request->validated();

        $statement = $newsItem->statements()->create([
            'source_type' => $data['source_type'],
            'source_label' => $data['source_label'] ?? null,
            'statement_type' => $data['statement_type'],
            'transcript' => $data['transcript'],
            'summary' => $data['summary'] ?? null,
            'received_at' => $data['received_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_publishable' => $request->boolean('is_publishable', true),
            'show_in_mail' => $request->boolean('show_in_mail', true),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if ($request->boolean('create_update')) {
            $body = trim((string) ($statement->summary ?: $statement->transcript));
            $body = $body !== '' ? $body : 'Nachträgliches Statement eingegangen.';

            $newsItem->updates()->create([
                'type' => NewsItemUpdate::TYPE_STATEMENT,
                'title' => 'Statement-Update',
                'body' => $body,
                'source_type' => $statement->source_type,
                'source_label' => $statement->source_label,
                'happened_at' => $statement->received_at,
                'show_in_mail' => $statement->show_in_mail,
                'show_in_article' => false,
                'is_active' => true,
                'statement_id' => $statement->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'O-Ton/Statement wurde gespeichert.');
    }

    public function update(UpdateNewsItemStatementRequest $request, NewsItem $newsItem, NewsItemStatement $statement): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_statements')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Statements-Tabelle fehlt. Bitte zuerst Migrationen ausführen (php artisan migrate).');
        }
        if ((int) $statement->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        $data = $request->validated();
        $statement->update([
            'source_type' => $data['source_type'],
            'source_label' => $data['source_label'] ?? null,
            'statement_type' => $data['statement_type'],
            'transcript' => $data['transcript'],
            'summary' => $data['summary'] ?? null,
            'received_at' => $data['received_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_publishable' => $request->boolean('is_publishable', true),
            'show_in_mail' => $request->boolean('show_in_mail', true),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Statement wurde aktualisiert.');
    }

    public function destroy(NewsItem $newsItem, NewsItemStatement $statement): RedirectResponse
    {
        $this->abortIfCannotManageNewsItem($newsItem);

        // PATCH: add statements and updates support for news items
        if (! Schema::hasTable('news_item_statements')) {
            return redirect()
                ->route('admin.news.edit', $newsItem)
                ->with('error', 'Statements-Tabelle fehlt. Bitte zuerst Migrationen ausführen (php artisan migrate).');
        }
        if ((int) $statement->news_item_id !== (int) $newsItem->id) {
            abort(404);
        }

        $statement->delete();

        return redirect()
            ->route('admin.news.edit', ['newsItem' => $newsItem, 'tab' => 'ot'])
            ->with('status', 'Statement wurde gelöscht.');
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

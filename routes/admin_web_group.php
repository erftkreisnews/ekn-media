<?php

use App\Http\Controllers\Admin\AudioController;
use App\Http\Controllers\Admin\BackofficeController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BrandContextController;
use App\Http\Controllers\Admin\CustomerContactController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerProductController;
use App\Http\Controllers\Admin\DeliveryController as AdminDeliveryController;
use App\Http\Controllers\Admin\DeliveryDestinationController;
use App\Http\Controllers\Admin\EventPlanningController;
use App\Http\Controllers\Admin\EventSuggestionController;
use App\Http\Controllers\Admin\HouseSquadController;
use App\Http\Controllers\Admin\ImageController;
use App\Http\Controllers\Admin\KoelnimageFotoController;
use App\Http\Controllers\Admin\NewsBlocktextParseController;
use App\Http\Controllers\Admin\NewsDeliveryController;
use App\Http\Controllers\Admin\NewsItemController;
use App\Http\Controllers\Admin\NewsItemStatementController;
use App\Http\Controllers\Admin\NewsItemUpdateController;
use App\Http\Controllers\Admin\NewsWebTextAiController;
use App\Http\Controllers\Admin\NewsWitnessLinkController;
use App\Http\Controllers\Admin\NominatimController;
use App\Http\Controllers\Admin\PlannedEventController;
use App\Http\Controllers\Admin\PresseportalOfficeController;
use App\Http\Controllers\Admin\PresseportalStoryFetchController;
use App\Http\Controllers\Admin\PublicationFindingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsageReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\VideoIngestController;
use App\Http\Controllers\Admin\WitnessInboxController;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::post('/brand-context', [BrandContextController::class, 'update'])->name('brand-context.update');

Route::middleware(['permission:'.App\Support\AdminPermissions::NEWS])->group(function (): void {
    Route::get('/news', [NewsItemController::class, 'index'])->name('news.index');
    Route::get('/news/create', [NewsItemController::class, 'create'])->name('news.create');
    Route::get('/news/create/erftkreis', [NewsItemController::class, 'createErftkreis'])->name('news.create.erftkreis');
    Route::get('/news/create/koelnimage', [NewsItemController::class, 'createKoelnimage'])->name('news.create.koelnimage');
    Route::get('/koelnimage/foto', [KoelnimageFotoController::class, 'create'])->name('koelnimage.foto.create');
    Route::post('/koelnimage/foto', [KoelnimageFotoController::class, 'store'])->name('koelnimage.foto.store');
    Route::get('/news/{newsItem}/foto', [KoelnimageFotoController::class, 'upload'])->name('koelnimage.foto.upload');
    Route::post('/news/{newsItem}/foto/images', [KoelnimageFotoController::class, 'storeImage'])
        ->name('koelnimage.foto.images.store')
        ->middleware('throttle:120,1');
    Route::get('/news/create-images', [NewsItemController::class, 'createImages'])->name('news.create-images');
    Route::get('/geocoding/search', [NominatimController::class, 'search'])
        ->name('geocoding.search')
        ->middleware('throttle:120,1');
    Route::get('/geocoding/reverse', [NominatimController::class, 'reverse'])
        ->name('geocoding.reverse')
        ->middleware('throttle:120,1');
    Route::post('/news/ai/polish-web-text', [NewsWebTextAiController::class, 'polish'])
        ->name('news.ai.polish-web-text')
        ->middleware('throttle:40,1');
    Route::post('/news/parse-blocktext', NewsBlocktextParseController::class)
        ->name('news.parse-blocktext')
        ->middleware('throttle:60,1');
    Route::post('/news', [NewsItemController::class, 'store'])->name('news.store');
    Route::get('/news/{newsItem}', fn (App\Models\NewsItem $newsItem) => redirect()->route('admin.news.edit', $newsItem))->name('news.show');
    Route::get('/news/{newsItem}/create-update', [NewsItemController::class, 'createUpdate'])->name('news.create-update');
    Route::get('/news/{newsItem}/edit', [NewsItemController::class, 'edit'])->name('news.edit');
    Route::patch('/news/{newsItem}', [NewsItemController::class, 'update'])->name('news.update');
    Route::delete('/news/{newsItem}', [NewsItemController::class, 'destroy'])->name('news.destroy');
    Route::get('/news/{newsItem}/media/{mediaId}/playback', [NewsItemController::class, 'playbackMedia'])->name('news.media.playback');
    Route::get('/news/{newsItem}/media/{mediaId}/edit', [NewsItemController::class, 'editMedia'])->name('news.media.edit');
    Route::get('/news/{newsItem}/media/{mediaId}/image-editor', [NewsItemController::class, 'editMediaImage'])->name('news.media.image-editor');
    Route::patch('/news/{newsItem}/media/{mediaId}', [NewsItemController::class, 'updateMedia'])->name('news.media.update');
    Route::post('/news/{newsItem}/media/bulk-delete', [NewsItemController::class, 'bulkDestroyImages'])->name('news.media.bulk-destroy');
    Route::post('/news/{newsItem}/media/bulk-planned-event-ai', [NewsItemController::class, 'bulkRequestPlannedEventImageAi'])->name('news.media.bulk-planned-event-ai');
    Route::post('/news/{newsItem}/media/bulk-assign-event-motiv', [NewsItemController::class, 'bulkAssignEventMotivToCaptions'])->name('news.media.bulk-assign-event-motiv');
    Route::delete('/news/{newsItem}/media/{mediaId}', [NewsItemController::class, 'destroyMedia'])->name('news.media.destroy');
    Route::post('/news/{newsItem}/media/{mediaId}/quick-send', [NewsDeliveryController::class, 'quickMediaSend'])
        ->name('news.media.quick-send')
        ->middleware('throttle:20,1');
    Route::post('/news/{newsItem}/media/{mediaId}/unlink', [NewsItemController::class, 'unlinkMedia'])->name('news.media.unlink');
    Route::get('/news/{newsItem}/media/{mediaId}/unkentlich', [NewsItemController::class, 'showUnkenntlichEditor'])->name('news.media.unkentlich');
    Route::post('/news/{newsItem}/media/{mediaId}/unkentlich', [NewsItemController::class, 'applyUnkenntlich'])->name('news.media.unkentlich.apply');
    Route::post('/news/{newsItem}/media/{mediaId}/unkentlich-aufheben', [NewsItemController::class, 'toggleUnkenntlich'])->name('news.media.unkentlich.aufheben');
    Route::match(['patch', 'post'], '/news/{newsItem}/media/{mediaId}/redaction', [NewsItemController::class, 'updateRedaction'])->name('news.media.redaction.update');
    Route::post('/news/{newsItem}/media/{mediaId}/redaction/run', [NewsItemController::class, 'runRedaction'])->name('news.media.redaction.run');
    Route::post('/news/{newsItem}/media/{mediaId}/redaction/run-now', [NewsItemController::class, 'runRedactionNow'])->name('news.media.redaction.run-now');
    Route::post('/news/{newsItem}/media/{mediaId}/stills/run', [NewsItemController::class, 'runVideoStills'])->name('news.media.stills.run');
    Route::get('/news/{newsItem}/media/{mediaId}/ai-status', [NewsItemController::class, 'mediaAiStatus'])->name('news.media.ai-status');
    Route::post('/news/{newsItem}/media/{mediaId}/request-ai', [NewsItemController::class, 'requestMediaAi'])->name('news.media.request-ai');
    Route::post('/news/{newsItem}/apply-author-credit', [NewsItemController::class, 'applyAuthorCredit'])->name('news.apply-author-credit');
    Route::post('/news/{newsItem}/statements', [NewsItemStatementController::class, 'store'])->name('news.statements.store');
    Route::post('/news/{newsItem}/updates', [NewsItemUpdateController::class, 'store'])->name('news.updates.store');
    Route::post('/news/{newsItem}/presseportal/fetch-story', PresseportalStoryFetchController::class)->name('news.presseportal.fetch-story');
    Route::get('/news/{newsItem}/statements/{statement}/edit', [NewsItemStatementController::class, 'edit'])->name('news.statements.edit');
    Route::patch('/news/{newsItem}/statements/{statement}', [NewsItemStatementController::class, 'update'])->name('news.statements.update');
    Route::delete('/news/{newsItem}/statements/{statement}', [NewsItemStatementController::class, 'destroy'])->name('news.statements.destroy');
    Route::get('/news/{newsItem}/updates/{update}/edit', [NewsItemUpdateController::class, 'edit'])->name('news.updates.edit');
    Route::patch('/news/{newsItem}/updates/{update}', [NewsItemUpdateController::class, 'update'])->name('news.updates.update');
    Route::delete('/news/{newsItem}/updates/{update}', [NewsItemUpdateController::class, 'destroy'])->name('news.updates.destroy');
    Route::post('/news/{newsItem}/witness-links', [NewsWitnessLinkController::class, 'store'])->name('news.witness-links.store');
    Route::delete('/news/{newsItem}/witness-links/{link}', [NewsWitnessLinkController::class, 'destroy'])->name('news.witness-links.destroy');
    Route::get('/news/{newsItem}/witness-submissions/{submission}/preview', [NewsWitnessLinkController::class, 'preview'])->name('news.witness-submissions.preview')->middleware('throttle:120,1');
    Route::get('/news/{newsItem}/witness-submissions/{submission}/download', [NewsWitnessLinkController::class, 'download'])->name('news.witness-submissions.download')->middleware('throttle:60,1');
    Route::post('/news/{newsItem}/witness-submissions/{submission}/promote', [NewsWitnessLinkController::class, 'promote'])->name('news.witness-submissions.promote');
    Route::post('/news/{newsItem}/witness-submissions/{submission}/reject', [NewsWitnessLinkController::class, 'reject'])->name('news.witness-submissions.reject');
    Route::get('/witness/inbox', [WitnessInboxController::class, 'index'])->name('witness.inbox');
    Route::post('/witness/global-links', [WitnessInboxController::class, 'storeLink'])->name('witness.global-links.store');
    Route::delete('/witness/global-links/{link}', [WitnessInboxController::class, 'destroyLink'])->name('witness.global-links.destroy');
    Route::get('/witness/submissions/{submission}/preview', [WitnessInboxController::class, 'preview'])->name('witness.submissions.preview')->middleware('throttle:120,1');
    Route::get('/witness/submissions/{submission}/download', [WitnessInboxController::class, 'download'])->name('witness.submissions.download')->middleware('throttle:60,1');
    Route::post('/witness/submissions/{submission}/assign', [WitnessInboxController::class, 'assign'])->name('witness.submissions.assign');
    Route::post('/witness/submissions/{submission}/reject', [WitnessInboxController::class, 'reject'])->name('witness.submissions.reject');
    Route::get('/news/{newsItem}/send', [NewsDeliveryController::class, 'prepareSend'])->name('news.send');
    Route::post('/news/{newsItem}/send', [NewsDeliveryController::class, 'send'])->name('news.send.post');
    Route::post('/news/{newsItem}/send-ftp', [NewsDeliveryController::class, 'queueFtp'])->name('news.send-ftp');
    Route::get('/news/{newsItem}/send-summary', [NewsDeliveryController::class, 'summary'])->name('news.send-summary');
    Route::post('/news/{newsItem}/send-summary', [NewsDeliveryController::class, 'applySummary'])->name('news.send-summary.apply');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::DELIVERIES])->group(function (): void {
    Route::get('/deliveries', [AdminDeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('/deliveries/{delivery}/activity', [AdminDeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{delivery}/revoke', [AdminDeliveryController::class, 'revoke'])->name('deliveries.revoke');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::MEDIA])->group(function (): void {
    Route::get('/video', [VideoController::class, 'index'])->name('video.index');
    Route::get('/video/{media}', [VideoController::class, 'show'])->name('video.show')->whereNumber('media');
    Route::patch('/video/{media}', [VideoController::class, 'update'])->name('video.update')->whereNumber('media');
    Route::get('/images', [ImageController::class, 'index'])->name('images.index');
    Route::get('/images/{media}', [ImageController::class, 'show'])->name('images.show')->whereNumber('media');
    Route::get('/images/{media}/editor-source', [ImageController::class, 'editorSource'])->name('images.editor-source')->whereNumber('media');
    Route::patch('/images/{media}', [ImageController::class, 'update'])->name('images.update')->whereNumber('media');
    Route::post('/images/{media}/editor-save', [ImageController::class, 'editorSave'])->name('images.editor-save')->whereNumber('media');
    Route::delete('/images/{media}', [ImageController::class, 'destroy'])->name('images.destroy')->whereNumber('media');
    Route::match(['get', 'post'], '/images/quick-send/bulk', [ImageController::class, 'quickSendBulkForm'])->name('images.quick-send.bulk');
    Route::post('/images/quick-send/bulk/send', [ImageController::class, 'quickSendBulk'])->name('images.quick-send.bulk.send')->middleware('throttle:20,1');
    Route::get('/images/{media}/quick-send', [ImageController::class, 'quickSendForm'])->name('images.quick-send');
    Route::post('/images/{media}/quick-send', [ImageController::class, 'quickSend'])->name('images.quick-send.post')->middleware('throttle:20,1');
    Route::get('/audio', [AudioController::class, 'index'])->name('audio.index');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::INGEST])->group(function (): void {
    Route::get('/ingest', [VideoIngestController::class, 'index'])->name('ingest.index');
    Route::post('/ingest/bulk', [VideoIngestController::class, 'bulk'])->name('ingest.bulk');
    Route::post('/ingest/emergency-purge', [VideoIngestController::class, 'emergencyPurge'])
        ->name('ingest.emergency-purge')
        ->middleware('throttle:3,1');
    Route::get('/ingest/eintraege', [VideoIngestController::class, 'index'])->name('ingest.entries');
    Route::get('/ingest/statistik', [VideoIngestController::class, 'statistics'])->name('ingest.statistics');
    Route::get('/ingest/render-jobs', [VideoIngestController::class, 'renderJobs'])->name('ingest.render-jobs');
    Route::get('/ingest/render-jobs/status', [VideoIngestController::class, 'renderJobsStatus'])->name('ingest.render-jobs.status');
    Route::get('/ingest/news/{newsItem}', [VideoIngestController::class, 'newsWorkspace'])->name('ingest.news-workspace');
    Route::post('/ingest/news/{newsItem}/render', [VideoIngestController::class, 'queueRender'])->name('ingest.news-render');
    Route::post('/ingest/news/{newsItem}/render-jobs/{job}/cancel', [VideoIngestController::class, 'cancelRenderJob'])->name('ingest.news-render-cancel');
    Route::get('/ingest/files/{ingestFile}/playback', [VideoIngestController::class, 'playback'])->name('ingest.playback');
    Route::get('/ingest/files/{ingestFile}/thumbnail', [VideoIngestController::class, 'thumbnail'])->name('ingest.thumbnail');
    Route::get('/ingest/files/{ingestFile}/preview-playback', [VideoIngestController::class, 'previewPlayback'])->name('ingest.preview-playback');
    Route::post('/ingest/files/{ingestFile}/queue-preview', [VideoIngestController::class, 'queuePreview'])->name('ingest.queue-preview');
    Route::post('/ingest/files/{ingestFile}/assign', [VideoIngestController::class, 'assign'])->name('ingest.assign');
    Route::post('/ingest/files/{ingestFile}/finalize-video', [VideoIngestController::class, 'finalizeAssignedVideo'])
        ->name('ingest.finalize-video');
    Route::post('/ingest/files/{ingestFile}/direct-marketing', [VideoIngestController::class, 'directMarketingFromImage'])
        ->name('ingest.direct-marketing')
        ->middleware('throttle:20,1');
    Route::delete('/ingest/files/{ingestFile}', [VideoIngestController::class, 'destroy'])->name('ingest.destroy');
    Route::get('/ingest/files/{ingestFile}', [VideoIngestController::class, 'show'])->name('ingest.show');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::SETTINGS])->group(function (): void {
    Route::get('/event-planning', [EventPlanningController::class, 'index'])->name('event-planning.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/seo', [SettingsController::class, 'seo'])->name('settings.seo');
    Route::get('/settings/seo/google-connect', [SettingsController::class, 'googleConnect'])->name('settings.seo.google-connect');
    Route::get('/settings/seo/google-callback', [SettingsController::class, 'googleCallback'])->name('settings.seo.google-callback');
    Route::post('/settings/seo/google-disconnect', [SettingsController::class, 'googleDisconnect'])->name('settings.seo.google-disconnect');
    Route::get('/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
    Route::post('/settings/backup/run', [SettingsController::class, 'backupRun'])->name('settings.backup.run');
    Route::get('/settings/event-suggestions', [EventSuggestionController::class, 'index'])->name('settings.event-suggestions.index');
    Route::get('/settings/event-suggestions/{eventSuggestion}/import', [EventSuggestionController::class, 'import'])
        ->name('settings.event-suggestions.import');
    Route::post('/settings/event-suggestions/{eventSuggestion}/dismiss', [EventSuggestionController::class, 'dismiss'])
        ->name('settings.event-suggestions.dismiss');

    Route::get('/settings/planned-events/global', [SettingsController::class, 'eventPlanning'])->name('settings.planned-events.global');
    Route::post('/settings/planned-events/global', [SettingsController::class, 'eventPlanningSave'])->name('settings.planned-events.global.save');
    Route::get('/settings/planned-events/create', [PlannedEventController::class, 'create'])->name('settings.planned-events.create');
    Route::post('/settings/planned-events/draft', [PlannedEventController::class, 'storeDraft'])->name('settings.planned-events.draft');
    Route::post('/settings/planned-events/draft/clear', [PlannedEventController::class, 'clearDraft'])->name('settings.planned-events.draft.clear');
    Route::post('/settings/planned-events', [PlannedEventController::class, 'store'])->name('settings.planned-events.store');
    Route::get('/settings/house-squad', [HouseSquadController::class, 'edit'])->name('settings.house-squad.edit');
    Route::put('/settings/house-squad', [HouseSquadController::class, 'update'])->name('settings.house-squad.update');
    Route::get('/settings/planned-events', [PlannedEventController::class, 'index'])->name('settings.planned-events.index');
    Route::get('/settings/planned-events/{plannedEvent}/edit', [PlannedEventController::class, 'edit'])->name('settings.planned-events.edit');
    Route::get('/settings/planned-events/{plannedEvent}/teams', [PlannedEventController::class, 'teamsOverview'])->name('settings.planned-events.teams');
    Route::post('/settings/planned-events/{plannedEvent}/teams/sync-24h', [PlannedEventController::class, 'sync24hParticipants'])->name('settings.planned-events.teams.sync-24h');
    Route::get('/settings/planned-events/{plannedEvent}/teams/{team}/reference', [PlannedEventController::class, 'downloadTeamReference'])->name('settings.planned-events.teams.reference');
    Route::get('/settings/planned-events/{plannedEvent}/schedule', [PlannedEventController::class, 'downloadSchedule'])->name('settings.planned-events.schedule');
    Route::put('/settings/planned-events/{plannedEvent}', [PlannedEventController::class, 'update'])->name('settings.planned-events.update');
    Route::delete('/settings/planned-events/{plannedEvent}', [PlannedEventController::class, 'destroy'])->name('settings.planned-events.destroy');
    Route::get('/settings/ai', [SettingsController::class, 'ai'])->name('settings.ai');
    Route::post('/settings/ai/test', [SettingsController::class, 'aiTest'])->name('settings.ai.test');
    Route::post('/settings/ai/news-web-text-prompt', [SettingsController::class, 'aiSaveNewsWebTextPrompt'])->name('settings.ai.news-web-text-prompt');
    Route::get('/settings/jobs', [SettingsController::class, 'jobs'])->name('settings.jobs');
    Route::post('/settings/jobs/run', [SettingsController::class, 'jobsRun'])->name('settings.jobs.run');
    Route::post('/settings/jobs/failed/retry-all', [SettingsController::class, 'jobsFailedRetryAll'])->name('settings.jobs.failed.retry-all');
    Route::post('/settings/jobs/failed/retry-one', [SettingsController::class, 'jobsFailedRetryOne'])->name('settings.jobs.failed.retry-one');
    Route::post('/settings/jobs/failed/flush', [SettingsController::class, 'jobsFailedFlush'])->name('settings.jobs.failed.flush');
    Route::get('/settings/media', [SettingsController::class, 'media'])->name('settings.media');
    Route::get('/settings/usage-tariffs', [SettingsController::class, 'usageTariffs'])->name('settings.usage-tariffs');
    Route::post('/settings/usage-tariffs', [SettingsController::class, 'usageTariffsSave'])->name('settings.usage-tariffs.save');
    Route::get('/settings/news-delete-audit', [SettingsController::class, 'newsDeleteAudit'])->name('settings.news-delete-audit');
    Route::get('/settings/presseportal', [PresseportalOfficeController::class, 'index'])->name('settings.presseportal');
    Route::post('/settings/presseportal/offices', [PresseportalOfficeController::class, 'store'])->name('settings.presseportal.offices.store');
    Route::patch('/settings/presseportal/offices/{office}', [PresseportalOfficeController::class, 'update'])->name('settings.presseportal.offices.update');
    Route::delete('/settings/presseportal/offices/{office}', [PresseportalOfficeController::class, 'destroy'])->name('settings.presseportal.offices.destroy');
    Route::post('/settings/presseportal/test', [SettingsController::class, 'presseportalTest'])->name('settings.presseportal.test');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::BACKOFFICE.'|'.App\Support\AdminPermissions::USERS])->group(function (): void {
    Route::get('/backoffice', [BackofficeController::class, 'index'])->name('backoffice.index');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::USERS])->group(function (): void {
    // /users/create vor /users/{user}, sonst wird „create“ als User-ID interpretiert
    Route::get('/backoffice/users/create', [UserController::class, 'create'])->name('backoffice.users.create');
    Route::post('/backoffice/users', [UserController::class, 'store'])->name('backoffice.users.store');
    Route::get('/backoffice/users', [UserController::class, 'index'])->name('backoffice.users.index');
    Route::get('/backoffice/users/{user}/edit', [UserController::class, 'edit'])->name('backoffice.users.edit');
    Route::post('/backoffice/users/{user}/welcome-mail', [UserController::class, 'resendWelcomeMail'])->name('backoffice.users.welcome-mail');
    Route::put('/backoffice/users/{user}', [UserController::class, 'update'])->name('backoffice.users.update');
    Route::delete('/backoffice/users/{user}', [UserController::class, 'destroy'])->name('backoffice.users.destroy');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::BACKOFFICE])->group(function (): void {
    Route::get('/backoffice/billing', [BillingController::class, 'index'])->name('backoffice.billing.index');
    Route::get('/backoffice/billing/create/{product}', [BillingController::class, 'create'])->name('backoffice.billing.create');
    Route::post('/backoffice/billing', [BillingController::class, 'store'])->name('backoffice.billing.store');
    Route::get('/backoffice/billing/{invoice}', [BillingController::class, 'show'])->name('backoffice.billing.show');
    Route::post('/backoffice/billing/{invoice}/wdr-checklist', [BillingController::class, 'updateWdrInvoiceChecklist'])->name('backoffice.billing.wdr-checklist.update');
    Route::post('/backoffice/billing/{invoice}/incoming-documents', [BillingController::class, 'storeIncomingDocument'])->name('backoffice.billing.incoming-documents.store');
    Route::get('/backoffice/billing/{invoice}/incoming-documents/{document}/download', [BillingController::class, 'downloadIncomingDocument'])->name('backoffice.billing.incoming-documents.download');
    Route::delete('/backoffice/billing/{invoice}/incoming-documents/{document}', [BillingController::class, 'destroyIncomingDocument'])->name('backoffice.billing.incoming-documents.destroy');
    Route::get('/backoffice/billing/{invoice}/pdf', [BillingController::class, 'downloadPdf'])->name('backoffice.billing.pdf');
    Route::get('/backoffice/billing/{invoice}/zugferd', [BillingController::class, 'downloadZugferd'])->name('backoffice.billing.zugferd');
    Route::get('/backoffice/billing/{invoice}/lexware-file', [BillingController::class, 'downloadLexwareFile'])->name('backoffice.billing.lexware-file');
    Route::post('/backoffice/billing/{invoice}/send', [BillingController::class, 'sendInvoice'])->name('backoffice.billing.send');
    Route::post('/backoffice/billing/{invoice}/ready-for-lexware', [BillingController::class, 'markReadyForLexware'])->name('backoffice.billing.ready-for-lexware');
    Route::get('/backoffice/billing/{invoice}/ready-for-lexware', fn (\App\Models\Invoice $invoice) => redirect()->route('admin.backoffice.billing.show', $invoice)->with('error', 'Bitte „Finale Lexware-Rechnung erstellen“ über den Button auf der Rechnungsseite ausführen.'))->name('backoffice.billing.ready-for-lexware.get');
    Route::post('/backoffice/billing/{invoice}/release', [BillingController::class, 'releaseDraft'])->name('backoffice.billing.release');
    Route::post('/backoffice/billing/{invoice}/payment', [BillingController::class, 'recordPayment'])->name('backoffice.billing.payment');
    Route::post('/backoffice/billing/{invoice}/lexware-payment-sync', [BillingController::class, 'syncLexwarePayment'])->name('backoffice.billing.lexware-payment-sync');

    Route::get('/backoffice/usage', [UsageReportController::class, 'index'])->name('backoffice.usage.index');
    Route::get('/backoffice/usage/create', [UsageReportController::class, 'create'])->name('backoffice.usage.create');
    Route::post('/backoffice/usage', [UsageReportController::class, 'store'])->name('backoffice.usage.store');
    Route::get('/backoffice/usage/{usageRecord}/edit', [UsageReportController::class, 'edit'])->name('backoffice.usage.edit');
    Route::put('/backoffice/usage/{usageRecord}', [UsageReportController::class, 'update'])->name('backoffice.usage.update');
    Route::delete('/backoffice/usage/{usageRecord}', [UsageReportController::class, 'destroy'])->name('backoffice.usage.destroy');

    Route::get('/backoffice/publication-findings', [PublicationFindingController::class, 'index'])->name('backoffice.publication-findings.index');
    Route::get('/backoffice/publication-findings/news-images', [PublicationFindingController::class, 'newsImages'])->name('backoffice.publication-findings.news-images');
    Route::get('/backoffice/publication-findings/create', [PublicationFindingController::class, 'create'])->name('backoffice.publication-findings.create');
    Route::post('/backoffice/publication-findings', [PublicationFindingController::class, 'store'])->name('backoffice.publication-findings.store');
    Route::post('/backoffice/publication-findings/scan', [PublicationFindingController::class, 'triggerScan'])->name('backoffice.publication-findings.scan');
    Route::get('/backoffice/publication-findings/{publicationFinding}/edit', [PublicationFindingController::class, 'edit'])->name('backoffice.publication-findings.edit');
    Route::put('/backoffice/publication-findings/{publicationFinding}', [PublicationFindingController::class, 'update'])->name('backoffice.publication-findings.update');
    Route::delete('/backoffice/publication-findings/{publicationFinding}', [PublicationFindingController::class, 'destroy'])->name('backoffice.publication-findings.destroy');
    Route::post('/backoffice/publication-findings/{publicationFinding}/confirm', [PublicationFindingController::class, 'confirm'])->name('backoffice.publication-findings.confirm');
    Route::post('/backoffice/publication-findings/{publicationFinding}/evidence-dossier', [PublicationFindingController::class, 'buildEvidenceDossier'])->name('backoffice.publication-findings.evidence-dossier.build');
    Route::get('/backoffice/publication-findings/{publicationFinding}/evidence-dossier', [PublicationFindingController::class, 'downloadEvidenceDossier'])->name('backoffice.publication-findings.evidence-dossier.download');
    Route::post('/backoffice/publication-findings/{publicationFinding}/manual-evidence', [PublicationFindingController::class, 'storeManualEvidence'])->name('backoffice.publication-findings.manual-evidence.store');
    Route::get('/backoffice/publication-findings/{publicationFinding}/manual-evidence/{evidenceType}', [PublicationFindingController::class, 'showManualEvidence'])->name('backoffice.publication-findings.manual-evidence.show');
    Route::delete('/backoffice/publication-findings/{publicationFinding}/manual-evidence/{evidenceType}', [PublicationFindingController::class, 'destroyManualEvidence'])->name('backoffice.publication-findings.manual-evidence.destroy');
    Route::post('/backoffice/publication-findings/{publicationFinding}/authority-access', [PublicationFindingController::class, 'createAuthorityAccess'])->name('backoffice.publication-findings.authority-access.create');
    Route::put('/backoffice/publication-findings/{publicationFinding}/authority-access', [PublicationFindingController::class, 'updateAuthorityAccessMeta'])->name('backoffice.publication-findings.authority-access.update');
    Route::post('/backoffice/publication-findings/{publicationFinding}/authority-access/revoke', [PublicationFindingController::class, 'revokeAuthorityAccess'])->name('backoffice.publication-findings.authority-access.revoke');
});

Route::middleware(['permission:'.App\Support\AdminPermissions::CUSTOMERS, 'role:admin'])->group(function (): void {
    Route::get('/products', function () {
        $orgId = request()->query('organization');
        if ($orgId !== null && $orgId !== '' && ctype_digit((string) $orgId)) {
            return redirect()->to('/admin/customers/'.(int) $orgId.'/products');
        }

        return redirect()->route('admin.customers.index');
    })->name('products.redirect');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/products', fn () => redirect()->route('admin.customers.index'))->name('customers.products.redirect-empty');
    Route::get('/customers/products/create', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//products', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//products/create', fn () => redirect()->route('admin.customers.index'));

    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show')->whereNumber('customer');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit')->whereNumber('customer');
    Route::post('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->whereNumber('customer');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('customers.destroy')
        ->whereNumber('customer')
        ->middleware('permission:'.App\Support\AdminPermissions::CUSTOMERS_DELETE);

    Route::get('/customers/{customer}/products', [CustomerProductController::class, 'index'])->name('customers.products.index')->whereNumber('customer');
    Route::get('/customers/{customer}/products/create', [CustomerProductController::class, 'create'])->name('customers.products.create')->whereNumber('customer');
    Route::post('/customers/{customer}/products', [CustomerProductController::class, 'store'])->name('customers.products.store')->whereNumber('customer');
    Route::get('/customers/{customer}/products/{product}/edit', [CustomerProductController::class, 'edit'])->name('customers.products.edit')->whereNumber('customer');
    Route::get('/customers/{customer}/products/{product}', function (Organization $customer, Product $product) {
        if ((int) $product->organization_id !== (int) $customer->id) {
            abort(404);
        }

        return redirect()->route('admin.customers.products.edit', [$customer, $product]);
    })->name('customers.products.show')->whereNumber('customer');
    Route::match(['post', 'put'], '/customers/{customer}/products/{product}', [CustomerProductController::class, 'update'])
        ->name('customers.products.update')
        ->whereNumber('customer');
    Route::post('/customers/{customer}/products/{product}/import-lexware', [CustomerProductController::class, 'importFromLexware'])->name('customers.products.import-lexware')->whereNumber('customer');
    Route::delete('/customers/{customer}/products/{product}', [CustomerProductController::class, 'destroy'])
        ->name('customers.products.destroy')
        ->whereNumber('customer')
        ->middleware('permission:'.App\Support\AdminPermissions::CUSTOMERS_DELETE);

    Route::get('/products/{product}/destinations', [DeliveryDestinationController::class, 'index'])->name('destinations.index');
    Route::get('/products/{product}/destinations/create', [DeliveryDestinationController::class, 'create'])->name('destinations.create');
    Route::post('/products/{product}/destinations', [DeliveryDestinationController::class, 'store'])->name('destinations.store');
    Route::get('/destinations/{destination}/edit', [DeliveryDestinationController::class, 'editByDestination'])->name('destinations.edit');
    Route::get('/destinations/{destination}', fn (\App\Models\DeliveryDestination $destination) => redirect()->route('admin.destinations.edit', $destination))->name('destinations.show');
    Route::put('/destinations/{destination}', [DeliveryDestinationController::class, 'update'])->name('destinations.update');
    Route::delete('/destinations/{destination}', [DeliveryDestinationController::class, 'destroy'])
        ->name('destinations.destroy')
        ->middleware('permission:'.App\Support\AdminPermissions::CUSTOMERS_DELETE);
    Route::post('/destinations/{destination}/test', [DeliveryDestinationController::class, 'test'])->name('destinations.test');
    Route::post('/destinations/{destination}/upload', [DeliveryDestinationController::class, 'upload'])->name('destinations.upload');

    Route::get('/customers/{customer}/destinations', [DeliveryDestinationController::class, 'indexForCustomer'])->name('customers.destinations.index')->whereNumber('customer');
    Route::get('/customers/{customer}/destinations/create', [DeliveryDestinationController::class, 'createForCustomer'])->name('customers.destinations.create')->whereNumber('customer');
    Route::post('/customers/{customer}/destinations', [DeliveryDestinationController::class, 'storeForCustomer'])->name('customers.destinations.store')->whereNumber('customer');
    Route::get('/customers/{customer}/destinations/{destination}/edit', [DeliveryDestinationController::class, 'editForCustomer'])->name('customers.destinations.edit')->whereNumber('customer');
    Route::put('/customers/{customer}/destinations/{destination}', [DeliveryDestinationController::class, 'updateForCustomer'])->name('customers.destinations.update')->whereNumber('customer');
    Route::delete('/customers/{customer}/destinations/{destination}', [DeliveryDestinationController::class, 'destroyForCustomer'])
        ->name('customers.destinations.destroy')
        ->whereNumber('customer')
        ->middleware('permission:'.App\Support\AdminPermissions::CUSTOMERS_DELETE);

    Route::get('/customers/contacts', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers/contacts/create', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//contacts', fn () => redirect()->route('admin.customers.index'));
    Route::get('/customers//contacts/create', fn () => redirect()->route('admin.customers.index'));

    Route::get('/customers/{customer}/contacts', [CustomerContactController::class, 'index'])->name('customers.contacts.index')->whereNumber('customer');
    Route::get('/customers/{customer}/contacts/create', [CustomerContactController::class, 'create'])->name('customers.contacts.create')->whereNumber('customer');
    Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store'])->name('customers.contacts.store')->whereNumber('customer');
    Route::get('/customers/{customer}/contacts/{contact}/edit', [CustomerContactController::class, 'edit'])->name('customers.contacts.edit')->whereNumber('customer');
    Route::get('/customers/{customer}/contacts/{contact}', fn (Organization $customer, \App\Models\Contact $contact) => redirect()->route('admin.customers.contacts.edit', [$customer, $contact]))->name('customers.contacts.show')->whereNumber('customer');
    Route::post('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])->name('customers.contacts.update')->whereNumber('customer');
    Route::put('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])->whereNumber('customer');
    Route::delete('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])
        ->name('customers.contacts.destroy')
        ->whereNumber('customer')
        ->middleware('permission:'.App\Support\AdminPermissions::CUSTOMERS_DELETE);
});

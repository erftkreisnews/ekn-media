<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveNewsWebTextAiPromptRequest;
use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\GenerateImageMetadata;
use App\Models\NewsItemDeleteAudit;
use App\Models\NewsItemMedia;
use App\Models\SiteSetting;
use App\Services\GoogleSearchConsoleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class SettingsController extends Controller
{
    private const AI_STATUS_CACHE_KEY = 'media_ai_connection_status';

    private const AI_LAST_OK_CACHE_KEY = 'media_ai_last_ok_at';

    private const AI_LAST_ERROR_CACHE_KEY = 'media_ai_last_error';

    private const AI_CACHE_TTL_MINUTES = 5;

    private const AI_LAST_TTL_MINUTES = 60 * 24;

    public function __construct(
        private GoogleSearchConsoleService $gsc
    ) {}

    public function index(): View
    {
        return view('admin.settings.index');
    }

    public function usageTariffs(): View
    {
        $this->ensureAdminRole();

        $tariffs = [
            'wdr_newsroom_video_price_per_minute' => $this->getPositiveDecimalSetting(
                SiteSetting::WDR_NEWSROOM_VIDEO_PRICE_PER_MINUTE,
                448.60
            ),
            'wdr_newsroom_image_first_price' => $this->getPositiveDecimalSetting(
                SiteSetting::WDR_NEWSROOM_IMAGE_FIRST_PRICE,
                42.37
            ),
            'wdr_newsroom_image_additional_price' => $this->getPositiveDecimalSetting(
                SiteSetting::WDR_NEWSROOM_IMAGE_ADDITIONAL_PRICE,
                28.25
            ),
            'wdr_newsroom_image_from_five_price' => $this->getPositiveDecimalSetting(
                SiteSetting::WDR_NEWSROOM_IMAGE_FROM_FIVE_PRICE,
                28.25
            ),
            'wdr_newsroom_audio_price_per_minute' => $this->getPositiveDecimalSetting(
                SiteSetting::WDR_NEWSROOM_AUDIO_PRICE_PER_MINUTE,
                0.00
            ),
        ];

        return view('admin.settings.usage-tariffs', compact('tariffs'));
    }

    public function usageTariffsSave(Request $request): RedirectResponse
    {
        $this->ensureAdminRole();

        $validated = $request->validate([
            'wdr_newsroom_video_price_per_minute' => ['required', 'numeric', 'min:0'],
            'wdr_newsroom_image_first_price' => ['required', 'numeric', 'min:0'],
            'wdr_newsroom_image_additional_price' => ['required', 'numeric', 'min:0'],
            'wdr_newsroom_image_from_five_price' => ['required', 'numeric', 'min:0'],
            'wdr_newsroom_audio_price_per_minute' => ['required', 'numeric', 'min:0'],
        ]);

        SiteSetting::put(
            SiteSetting::WDR_NEWSROOM_VIDEO_PRICE_PER_MINUTE,
            number_format((float) $validated['wdr_newsroom_video_price_per_minute'], 2, '.', '')
        );
        SiteSetting::put(
            SiteSetting::WDR_NEWSROOM_IMAGE_FIRST_PRICE,
            number_format((float) $validated['wdr_newsroom_image_first_price'], 2, '.', '')
        );
        SiteSetting::put(
            SiteSetting::WDR_NEWSROOM_IMAGE_ADDITIONAL_PRICE,
            number_format((float) $validated['wdr_newsroom_image_additional_price'], 2, '.', '')
        );
        SiteSetting::put(
            SiteSetting::WDR_NEWSROOM_IMAGE_FROM_FIVE_PRICE,
            number_format((float) $validated['wdr_newsroom_image_from_five_price'], 2, '.', '')
        );
        SiteSetting::put(
            SiteSetting::WDR_NEWSROOM_AUDIO_PRICE_PER_MINUTE,
            number_format((float) $validated['wdr_newsroom_audio_price_per_minute'], 2, '.', '')
        );

        return redirect()
            ->route('admin.settings.usage-tariffs')
            ->with('status', 'WDR-Tarife wurden gespeichert.');
    }

    public function eventPlanning(): View
    {
        $eventPlanningEnabled = SiteSetting::get(SiteSetting::MEDIA_AI_EVENT_PLANNING_ENABLED) === '1';
        $eventPlanningContext = (string) (SiteSetting::get(SiteSetting::MEDIA_AI_EVENT_PLANNING_CONTEXT) ?? '');

        return view('admin.settings.event-planning', compact('eventPlanningEnabled', 'eventPlanningContext'));
    }

    public function eventPlanningSave(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_planning_context' => ['nullable', 'string', 'max:60000'],
        ]);

        SiteSetting::put(
            SiteSetting::MEDIA_AI_EVENT_PLANNING_ENABLED,
            $request->boolean('event_planning_enabled') ? '1' : '0'
        );

        $text = trim((string) ($validated['event_planning_context'] ?? ''));
        SiteSetting::put(SiteSetting::MEDIA_AI_EVENT_PLANNING_CONTEXT, $text);

        return redirect()
            ->route('admin.settings.planned-events.global')
            ->with('status', 'Zusatz-Vorgaben gespeichert.');
    }

    // PATCH: add news delete audit settings tab
    public function newsDeleteAudit(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = NewsItemDeleteAudit::query()
            ->with('user')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('slug', 'like', '%'.$q.'%')
                    ->orWhere('news_item_id', is_numeric($q) ? (int) $q : -1);
            });
        }

        $audits = $query->paginate(30)->withQueryString();

        return view('admin.settings.news-delete-audit', compact('audits', 'q'));
    }

    public function seo(Request $request): View|RedirectResponse
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $searchConsoleUrl = 'https://search.google.com/search-console';
        $robotsUrl = $baseUrl.'/robots.txt';
        $sitemapUrl = $baseUrl.'/sitemap.xml';
        $sitemapNewsUrl = $baseUrl.'/sitemap-news.xml';

        $gscConnected = $this->gsc->isConnected();
        $gscPerformance = $gscConnected ? $this->gsc->getPerformanceSummary() : null;

        if ($request->has('refresh') && $gscConnected) {
            $this->gsc->clearPerformanceCache();
            $gscPerformance = $this->gsc->getPerformanceSummary();
        }

        return view('admin.settings.seo', [
            'baseUrl' => $baseUrl,
            'searchConsoleUrl' => $searchConsoleUrl,
            'robotsUrl' => $robotsUrl,
            'sitemapUrl' => $sitemapUrl,
            'sitemapNewsUrl' => $sitemapNewsUrl,
            'gscConnected' => $gscConnected,
            'gscPerformance' => $gscPerformance,
        ]);
    }

    /**
     * Redirect zu Google OAuth (nur Search-Console-Leseberechtigung).
     */
    public function googleConnect(): RedirectResponse
    {
        $this->ensureGoogleConfig();

        return Socialite::driver('google')
            ->scopes(config('services.google.scopes', ['https://www.googleapis.com/auth/webmasters.readonly']))
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Callback nach Google OAuth – Refresh-Token speichern.
     */
    public function googleCallback(Request $request): RedirectResponse
    {
        try {
            $user = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.seo')
                ->with('error', 'Verbindung mit Google konnte nicht hergestellt werden. Bitte erneut versuchen.');
        }

        $refreshToken = $user->refreshToken;
        if (! $refreshToken) {
            return redirect()->route('admin.settings.seo')
                ->with('error', 'Google hat keinen Refresh-Token zurückgegeben. Bitte bei der Anmeldung „Zugriff gewähren“ bestätigen und erneut verbinden.');
        }

        $this->gsc->storeRefreshToken($refreshToken);

        return redirect()->route('admin.settings.seo')
            ->with('status', 'Google Search Console wurde erfolgreich verbunden. Die Suchperformance-Daten werden in Kürze angezeigt.');
    }

    /**
     * Verbindung mit Google Search Console trennen.
     */
    public function googleDisconnect(): RedirectResponse
    {
        $this->gsc->disconnect();

        return redirect()->route('admin.settings.seo')
            ->with('status', 'Die Verbindung mit Google Search Console wurde getrennt.');
    }

    private function ensureGoogleConfig(): void
    {
        if (empty(config('services.google.client_id')) || empty(config('services.google.client_secret'))) {
            throw new \RuntimeException(
                'Google Search Console ist nicht konfiguriert. Bitte GOOGLE_CLIENT_ID und GOOGLE_CLIENT_SECRET in der .env setzen.'
            );
        }
    }

    /**
     * Backup-Übersicht (Spatie Laravel Backup).
     */
    public function backup(): View
    {
        $backupDisks = (array) config('backup.backup.destination.disks', ['backups']);
        $backupDiskName = (string) ($backupDisks[0] ?? 'backups');
        $disk = Storage::disk($backupDiskName);
        $files = $disk->allFiles();
        $backups = collect($files)
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
            ->map(function (string $path) use ($disk) {
                $size = $disk->exists($path) ? $disk->size($path) : 0;
                $lastModified = $disk->exists($path) ? $disk->lastModified($path) : null;

                return [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => $size,
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'date' => $lastModified ? date('d.m.Y H:i', $lastModified) : null,
                    'timestamp' => $lastModified ?: 0,
                ];
            })
            ->sortByDesc('timestamp')
            ->values();

        $settings = [
            'backup_disk' => $backupDiskName,
            'local_path' => (string) data_get(config('filesystems.disks'), $backupDiskName.'.root', storage_path('app/backups')),
            'temp_path' => config('backup.backup.temporary_directory') ?: storage_path('app/backup-temp'),
            'dropbox_configured' => ! empty(config('filesystems.disks.dropbox.authorization_token')),
            'dropbox_path_prefix' => config('filesystems.disks.dropbox.path_prefix', '') ?: '–',
            'run_at' => config('backup.schedule.run_at', '02:00'),
            'clean_at' => config('backup.schedule.clean_at', '03:00'),
            'retention' => [
                'daily' => config('backup.cleanup.default_strategy.keep_all_backups_for_days', 7),
                'weekly' => config('backup.cleanup.default_strategy.keep_weekly_backups_for_weeks', 4),
                'monthly' => config('backup.cleanup.default_strategy.keep_monthly_backups_for_months', 3),
            ],
        ];

        return view('admin.settings.backup', compact('backups', 'settings'));
    }

    /**
     * Backup jetzt ausführen (POST).
     */
    public function backupRun(Request $request): RedirectResponse
    {
        $request->validate(['only_db' => 'sometimes|boolean']);

        try {
            $commandArgs = ['--disable-notifications' => true];
            if ($request->boolean('only_db')) {
                $commandArgs['--only-db'] = true;
            }

            $exitCode = Artisan::call('backup:run', $commandArgs);
            if ($exitCode !== 0) {
                $error = trim((string) Artisan::output());
                $message = 'Backup fehlgeschlagen (Exit-Code '.$exitCode.').';
                if ($error !== '') {
                    $message .= ' '.\Illuminate\Support\Str::limit($error, 500);
                }

                return redirect()->route('admin.settings.backup')->with('error', $message);
            }

            return redirect()->route('admin.settings.backup')->with('status', 'Backup wurde erfolgreich erstellt.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.backup')->with('error', 'Backup fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * KI / ChatGPT: Status und Konfiguration.
     */
    public function ai(): View
    {
        $apiKey = config('media_ai.api_key');
        $keySet = ! empty($apiKey);
        $cachedStatus = Cache::get(self::AI_STATUS_CACHE_KEY);
        $lastOkAt = Cache::get(self::AI_LAST_OK_CACHE_KEY);
        $lastError = Cache::get(self::AI_LAST_ERROR_CACHE_KEY);
        $statusOk = $keySet && ($cachedStatus === 'ok' || $lastOkAt !== null);
        $config = [
            'vision_model' => config('media_ai.vision_model'),
            'auto_analyze' => config('media_ai.auto_analyze'),
            'rate_limit_per_minute' => config('media_ai.rate_limit_per_minute'),
            'timeout' => config('media_ai.timeout'),
            'retries' => config('media_ai.retries'),
        ];

        $newsWebTextSystemPrompt = '';
        if (Schema::hasTable('settings')) {
            $newsWebTextSystemPrompt = (string) (SiteSetting::get(SiteSetting::NEWS_WEB_TEXT_AI_SYSTEM_PROMPT) ?? '');
        }

        return view('admin.settings.ai', [
            'keySet' => $keySet,
            'statusOk' => $statusOk,
            'lastOkAt' => $lastOkAt,
            'lastError' => $lastError,
            'config' => $config,
            'newsWebTextSystemPrompt' => $newsWebTextSystemPrompt,
            'newsAiTextModel' => config('news_ai.text_model'),
            'newsAiDefaultPromptPreview' => Str::limit((string) config('news_ai.default_system_prompt'), 400),
        ]);
    }

    public function aiSaveNewsWebTextPrompt(SaveNewsWebTextAiPromptRequest $request): RedirectResponse
    {
        if (! Schema::hasTable('settings')) {
            return redirect()->route('admin.settings.ai')
                ->with('ai_prompt_error', 'Die Datenbank-Tabelle „settings“ fehlt. Migration ausführen.');
        }

        $prompt = $request->validated()['news_web_text_ai_system_prompt'] ?? null;
        $prompt = is_string($prompt) ? trim($prompt) : '';

        if ($prompt === '') {
            SiteSetting::query()->where('key', SiteSetting::NEWS_WEB_TEXT_AI_SYSTEM_PROMPT)->delete();
        } else {
            SiteSetting::put(SiteSetting::NEWS_WEB_TEXT_AI_SYSTEM_PROMPT, $prompt);
        }

        return redirect()->route('admin.settings.ai')
            ->with('ai_prompt_saved', 'Leit-Prompt für Web-Text wurde gespeichert. Leeres Feld = Standard aus Konfiguration.');
    }

    /**
     * OpenAI-Verbindung testen (POST). CSRF erforderlich.
     */
    public function aiTest(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $apiKey = config('media_ai.api_key');
        if (empty($apiKey)) {
            Cache::put(self::AI_STATUS_CACHE_KEY, 'failed', self::AI_CACHE_TTL_MINUTES * 60);
            Cache::put(self::AI_LAST_ERROR_CACHE_KEY, 'OPENAI_API_KEY ist nicht gesetzt.', self::AI_LAST_TTL_MINUTES * 60);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Key fehlt. Bitte OPENAI_API_KEY in .env setzen.']);
            }

            return redirect()->route('admin.settings.ai')->with('ai_test_error', 'Key fehlt.');
        }

        $headers = ['Authorization' => 'Bearer '.$apiKey];
        if ($org = config('media_ai.organization')) {
            $headers['OpenAI-Organization'] = $org;
        }
        $timeout = (int) config('media_ai.timeout', 30);
        $body = [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 5,
            'messages' => [['role' => 'user', 'content' => 'OK']],
        ];

        try {
            $response = Http::withHeaders($headers)->timeout($timeout)->post('https://api.openai.com/v1/chat/completions', $body);
            if ($response->successful()) {
                Cache::put(self::AI_STATUS_CACHE_KEY, 'ok', self::AI_CACHE_TTL_MINUTES * 60);
                Cache::put(self::AI_LAST_OK_CACHE_KEY, now()->toIso8601String(), self::AI_LAST_TTL_MINUTES * 60);
                Cache::forget(self::AI_LAST_ERROR_CACHE_KEY);
                Log::info('MediaAi: connection test ok', ['media_ai' => true]);
                if ($request->wantsJson()) {
                    return response()->json(['success' => true, 'message' => 'Verbindung erfolgreich.']);
                }

                return redirect()->route('admin.settings.ai')->with('ai_test_ok', 'Verbindung erfolgreich.');
            }
            $errMsg = 'API Fehler: '.$response->status().'. '.\Illuminate\Support\Str::limit($response->body(), 200);
            Cache::put(self::AI_STATUS_CACHE_KEY, 'failed', self::AI_CACHE_TTL_MINUTES * 60);
            Cache::put(self::AI_LAST_ERROR_CACHE_KEY, $errMsg, self::AI_LAST_TTL_MINUTES * 60);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg]);
            }

            return redirect()->route('admin.settings.ai')->with('ai_test_error', $errMsg);
        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            Cache::put(self::AI_STATUS_CACHE_KEY, 'failed', self::AI_CACHE_TTL_MINUTES * 60);
            Cache::put(self::AI_LAST_ERROR_CACHE_KEY, $errMsg, self::AI_LAST_TTL_MINUTES * 60);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg]);
            }

            return redirect()->route('admin.settings.ai')->with('ai_test_error', $errMsg);
        }
    }

    /**
     * Medien-Einstellungen (Bildgrößen, Limits – nur Anzeige).
     */
    public function media(): View
    {
        return view('admin.settings.media', [
            'masterLongEdge' => config('media.image_master_long_edge_max', 6048),
            'masterShortEdge' => config('media.image_master_short_edge_max', 4024),
            'uploadMaxKb' => config('media.image_upload_max_kb', 20480),
            'minLongEdge' => 3500,
        ]);
    }

    /** Max. Alter der Heartbeats in Sekunden, ab dem die Ampel auf Rot geht. */
    private const HEARTBEAT_MAX_AGE_SECONDS = 120;

    /**
     * Jobs / Warteschlange – Ampel Cron/Queue-Worker, manuell KI, Video, Audio neu anstoßen.
     */
    public function jobs(): View
    {
        $countAiPending = NewsItemMedia::where('type', 'image')
            ->where(function ($q) {
                $q->whereNull('ai_status')->orWhere('ai_status', 'error');
            })
            ->count();
        $countVideo = NewsItemMedia::where('type', 'video')->count();
        $countAudio = NewsItemMedia::where('type', 'audio')->count();

        $cronLastAt = Cache::get('cron_heartbeat_at');
        $queueWorkerLastAt = Cache::get('queue_worker_heartbeat_at');
        $now = now();
        $maxAge = self::HEARTBEAT_MAX_AGE_SECONDS;

        $cronOk = $cronLastAt && Carbon::parse($cronLastAt)->diffInSeconds($now) <= $maxAge;
        $queueWorkerHeartbeatOk = $queueWorkerLastAt && Carbon::parse($queueWorkerLastAt)->diffInSeconds($now) <= $maxAge;
        $queueWorkerBusy = $this->queueWorkerIsBusyWithLongJob();
        $queueWorkerOk = $queueWorkerHeartbeatOk || $queueWorkerBusy;

        return view('admin.settings.jobs', [
            'countAiPending' => $countAiPending,
            'countVideo' => $countVideo,
            'countAudio' => $countAudio,
            'cronOk' => $cronOk,
            'queueWorkerOk' => $queueWorkerOk,
            'queueWorkerBusy' => $queueWorkerBusy,
            'queueWorkerHeartbeatOk' => $queueWorkerHeartbeatOk,
            'cronLastAt' => $this->jobsDisplayTimeFromHeartbeatCache($cronLastAt),
            'queueWorkerLastAt' => $this->jobsDisplayTimeFromHeartbeatCache($queueWorkerLastAt),
            'queueDb' => $this->queueDatabaseOverview(),
            'jobsDisplayTimezone' => $this->jobsDisplayTimezoneLabel(),
        ]);
    }

    /**
     * Anzeige-Zeitzone = APP_TIMEZONE (z. B. Europe/Berlin). Keine feste UTC-Annahme für DB-Zeiten,
     * sonst +1h/+2h Versatz wenn MySQL/Laravel bereits lokale Zeit liefert.
     */
    private function jobsDisplayTimezone(): string
    {
        return (string) config('app.timezone', 'Europe/Berlin');
    }

    private function jobsDisplayTimezoneLabel(): string
    {
        $tz = $this->jobsDisplayTimezone();

        return $tz !== '' ? $tz : 'Europe/Berlin';
    }

    /**
     * Heartbeat aus Cache (ISO-8601) → Anzeige in APP_TIMEZONE.
     */
    private function jobsDisplayTimeFromHeartbeatCache(mixed $cached): ?Carbon
    {
        if ($cached === null || $cached === '') {
            return null;
        }

        return Carbon::parse((string) $cached)->timezone($this->jobsDisplayTimezone());
    }

    /**
     * Worker-Prozess läuft und bearbeitet einen reservierten Job (z. B. Ingest-Render).
     * Heartbeat-Jobs auf „default“ kommen dann nicht durch — trotzdem aktiv.
     */
    private function queueWorkerIsBusyWithLongJob(): bool
    {
        if (! $this->queueWorkerProcessIsRunning()) {
            return false;
        }

        $connectionName = (string) config('queue.default', 'database');
        if ((string) config("queue.connections.{$connectionName}.driver", '') !== 'database') {
            return true;
        }

        $jobsTable = (string) config("queue.connections.{$connectionName}.table", 'jobs');
        if (! Schema::hasTable($jobsTable)) {
            return false;
        }

        return DB::table($jobsTable)->whereNotNull('reserved_at')->exists();
    }

    private function queueWorkerProcessIsRunning(): bool
    {
        $output = shell_exec("pgrep -f 'artisan queue:work' 2>/dev/null");

        return is_string($output) && trim($output) !== '';
    }

    /**
     * failed_jobs.failed_at: wie von Laravel/MySQL geliefert parsen, dann in APP_TIMEZONE anzeigen.
     */
    private function jobsDisplayTimeFromFailedAt(mixed $failedAt): ?Carbon
    {
        if ($failedAt === null || $failedAt === '') {
            return null;
        }

        return Carbon::parse((string) $failedAt)->timezone($this->jobsDisplayTimezone());
    }

    /**
     * Zähler und Stichproben aus jobs / failed_jobs (nur bei database-Queue: offene Jobs in jobs).
     *
     * @return array<string, mixed>
     */
    private function queueDatabaseOverview(): array
    {
        $connectionName = (string) config('queue.default', 'database');
        $driver = (string) config("queue.connections.{$connectionName}.driver", '');
        $usesDbPending = $driver === 'database';
        $jobsTable = (string) config("queue.connections.{$connectionName}.table", 'jobs');

        $pendingTotal = 0;
        $pendingByQueue = [];
        $pendingSample = [];

        if ($usesDbPending && Schema::hasTable($jobsTable)) {
            $pendingTotal = (int) DB::table($jobsTable)->count();
            $pendingByQueue = DB::table($jobsTable)
                ->selectRaw('queue, count(*) as c')
                ->groupBy('queue')
                ->orderBy('queue')
                ->get()
                ->map(fn ($r) => ['queue' => $r->queue, 'c' => (int) $r->c])
                ->all();
            $rows = DB::table($jobsTable)->orderBy('id')->limit(50)->get(['id', 'queue', 'payload', 'available_at']);
            foreach ($rows as $r) {
                $p = json_decode($r->payload, true);
                $name = is_array($p) ? (string) ($p['displayName'] ?? '?') : '?';
                $pendingSample[] = [
                    'id' => (int) $r->id,
                    'queue' => (string) $r->queue,
                    'name' => $name,
                    'available_at' => $r->available_at
                        ? Carbon::createFromTimestamp((int) $r->available_at)->timezone($this->jobsDisplayTimezone())
                        : null,
                ];
            }
        }

        $failedTotal = 0;
        $failedByQueue = [];
        $failedSample = [];

        if (Schema::hasTable('failed_jobs')) {
            $failedTotal = (int) DB::table('failed_jobs')->count();
            $failedByQueue = DB::table('failed_jobs')
                ->selectRaw('queue, count(*) as c')
                ->groupBy('queue')
                ->orderBy('queue')
                ->get()
                ->map(fn ($r) => ['queue' => $r->queue, 'c' => (int) $r->c])
                ->all();
            $rows = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(30)->get(['uuid', 'queue', 'connection', 'payload', 'exception', 'failed_at']);
            foreach ($rows as $r) {
                $p = json_decode($r->payload, true);
                $name = is_array($p) ? (string) ($p['displayName'] ?? '?') : '?';
                $ex = (string) $r->exception;
                $exShort = Str::limit(preg_replace('/\s+/', ' ', strip_tags($ex)), 280);

                $failedSample[] = [
                    'uuid' => (string) $r->uuid,
                    'queue' => (string) $r->queue,
                    'connection' => (string) $r->connection,
                    'name' => $name,
                    'failed_at' => $this->jobsDisplayTimeFromFailedAt($r->failed_at),
                    'exception_preview' => $exShort,
                ];
            }
        }

        return [
            'connectionName' => $connectionName,
            'driver' => $driver,
            'usesDbPending' => $usesDbPending,
            'pendingTotal' => $pendingTotal,
            'pendingByQueue' => $pendingByQueue,
            'pendingSample' => $pendingSample,
            'failedTotal' => $failedTotal,
            'failedByQueue' => $failedByQueue,
            'failedSample' => $failedSample,
        ];
    }

    /**
     * Einen fehlgeschlagenen Job per UUID erneut einreihen (Artisan queue:retry mit einer UUID).
     */
    public function jobsFailedRetryOne(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'regex:/^[0-9a-fA-F-]{36}$/'],
        ]);
        $uuid = (string) $data['uuid'];

        if (! Schema::hasTable('failed_jobs')) {
            return redirect()->route('admin.settings.jobs')
                ->with('error', 'Tabelle failed_jobs ist nicht verfügbar.');
        }

        try {
            $exit = Artisan::call('queue:retry', ['id' => [$uuid]]);
            $output = trim(Artisan::output());
            if ($exit !== 0) {
                return redirect()->route('admin.settings.jobs')
                    ->with('error', 'Wiederholen fehlgeschlagen (Exit-Code '.$exit.').');
            }
            if ($output !== '') {
                Log::info('jobsFailedRetryOne artisan output', ['uuid' => $uuid, 'output' => $output]);
            }

            return redirect()->route('admin.settings.jobs')
                ->with('status', 'Der Job wurde erneut in die Warteschlange gestellt.');
        } catch (\Throwable $e) {
            Log::error('jobsFailedRetryOne', ['uuid' => $uuid, 'message' => $e->getMessage()]);

            return redirect()->route('admin.settings.jobs')
                ->with('error', 'Wiederholen fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * Alle fehlgeschlagenen Jobs erneut einreihen (entspricht queue:retry all).
     */
    public function jobsFailedRetryAll(): RedirectResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return redirect()->route('admin.settings.jobs')
                ->with('error', 'Tabelle failed_jobs ist nicht verfügbar.');
        }

        try {
            $exit = Artisan::call('queue:retry', ['id' => ['all']]);
            $output = trim(Artisan::output());
            if ($exit !== 0) {
                return redirect()->route('admin.settings.jobs')
                    ->with('error', 'Wiederholen fehlgeschlagen (Exit-Code '.$exit.').');
            }
            if ($output !== '') {
                Log::info('jobsFailedRetryAll artisan output', ['output' => $output]);
            }

            return redirect()->route('admin.settings.jobs')
                ->with('status', 'Alle fehlgeschlagenen Jobs wurden erneut in die Warteschlange gestellt.');
        } catch (\Throwable $e) {
            Log::error('jobsFailedRetryAll', ['message' => $e->getMessage()]);

            return redirect()->route('admin.settings.jobs')
                ->with('error', 'Wiederholen fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * Tabelle failed_jobs leeren (entspricht queue:flush) – keine erneute Ausführung.
     */
    public function jobsFailedFlush(): RedirectResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return redirect()->route('admin.settings.jobs')
                ->with('error', 'Tabelle failed_jobs ist nicht verfügbar.');
        }

        try {
            $exit = Artisan::call('queue:flush');
            $output = trim(Artisan::output());
            if ($exit !== 0) {
                return redirect()->route('admin.settings.jobs')
                    ->with('error', 'Bereinigen fehlgeschlagen (Exit-Code '.$exit.').');
            }
            if ($output !== '') {
                Log::info('jobsFailedFlush artisan output', ['output' => $output]);
            }

            return redirect()->route('admin.settings.jobs')
                ->with('status', 'Die Liste der fehlgeschlagenen Jobs wurde geleert.');
        } catch (\Throwable $e) {
            Log::error('jobsFailedFlush', ['message' => $e->getMessage()]);

            return redirect()->route('admin.settings.jobs')
                ->with('error', 'Bereinigen fehlgeschlagen: '.$e->getMessage());
        }
    }

    /**
     * Ausgewählte Job-Typen erneut in die Warteschlange stellen (POST).
     */
    public function jobsRun(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => 'required|in:ai,video,audio',
        ]);
        $type = $request->input('type');
        $limit = 100;

        if ($type === 'ai') {
            $media = NewsItemMedia::where('type', 'image')
                ->where(function ($q) {
                    $q->whereNull('ai_status')->orWhere('ai_status', 'error');
                })
                ->limit($limit)
                ->get();
            foreach ($media as $m) {
                GenerateImageMetadata::dispatch($m);
            }
            $message = $media->count().' Bild(er) für KI-Metadaten in die Warteschlange gestellt.';
        } elseif ($type === 'video') {
            $media = NewsItemMedia::where('type', 'video')->limit($limit)->get();
            foreach ($media as $m) {
                ExtractVideoMetadata::dispatch($m);
            }
            $message = $media->count().' Video(s) für Metadaten-Extraktion in die Warteschlange gestellt.';
        } else {
            $media = NewsItemMedia::where('type', 'audio')->limit($limit)->get();
            foreach ($media as $m) {
                ExtractAudioMetadata::dispatch($m);
            }
            $message = $media->count().' Audio(s) für Metadaten-Extraktion in die Warteschlange gestellt.';
        }

        return redirect()->route('admin.settings.jobs')->with('status', $message);
    }

    /**
     * Presseportal: API-Key gegen api.presseportal.de prüfen (Story-Endpunkt).
     */
    public function presseportalTest(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $apiKey = config('presseportal.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'PRESSEPORTAL_API_KEY fehlt in .env.']);
            }

            return redirect()->route('admin.settings.presseportal')->with('presseportal_test_error', 'API-Key fehlt.');
        }

        $base = rtrim((string) config('presseportal.base_url', 'https://api.presseportal.de/api/v2'), '/');
        $timeout = (int) config('presseportal.timeout', 20);
        $endpoint = $base.'/story/1';

        try {
            $response = Http::timeout($timeout)->acceptJson()->get($endpoint, ['api_key' => $apiKey]);
            $json = $response->json();
            if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
                $code = (string) ($json['error']['code'] ?? '');
                if ($code === '100') {
                    throw new \RuntimeException('API meldet: Key nicht übermittelt.');
                }
                if (in_array($code, ['101', '102'], true)) {
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'API-Key ungültig oder deaktiviert.']);
                    }

                    return redirect()->route('admin.settings.presseportal')->with('presseportal_test_error', 'API-Key ungültig oder deaktiviert.');
                }
                if ($code === '150') {
                    $quotaMsg = 'API-Kontingent aufgebraucht (Quota exceeded). Bitte bei news aktuell nach einem höheren Kontingent fragen.';
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $quotaMsg]);
                    }

                    return redirect()->route('admin.settings.presseportal')->with('presseportal_test_error', $quotaMsg);
                }
            }

            // Einzelabruf /story/{id} meldet oft nur „Ressource unknown“ (200), während der Import-Fallback /stories/office/… am Kontingent (150) scheitert.
            $listResponse = Http::timeout($timeout)->acceptJson()->get($base.'/stories/office/1', [
                'api_key' => $apiKey,
                'limit' => 1,
                'start' => 0,
            ]);
            $listJson = $listResponse->json();
            if (is_array($listJson) && isset($listJson['error']) && is_array($listJson['error']) && (string) ($listJson['error']['code'] ?? '') === '150') {
                $quotaMsg = 'API-Kontingent aufgebraucht (Quota exceeded). Der Abruf über „Text laden“ benötigt Listenabrufe – bitte bei news aktuell nach einem höheren Kontingent fragen.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $quotaMsg]);
                }

                return redirect()->route('admin.settings.presseportal')->with('presseportal_test_error', $quotaMsg);
            }

            Log::info('Presseportal: Verbindungstest OK', ['http' => $response->status()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'API erreichbar, Key wird akzeptiert.']);
            }

            return redirect()->route('admin.settings.presseportal')->with('presseportal_test_ok', 'API erreichbar, Key wird akzeptiert.');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }

            return redirect()->route('admin.settings.presseportal')->with('presseportal_test_error', $msg);
        }
    }

    private function ensureAdminRole(): void
    {
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403, 'Nur Admin darf diese Einstellung bearbeiten.');
        }
    }

    private function getPositiveDecimalSetting(string $key, float $default): float
    {
        $raw = SiteSetting::get($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (! is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;
        if ($value < 0) {
            return $default;
        }

        return round($value, 2);
    }
}

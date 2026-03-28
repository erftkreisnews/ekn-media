<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractAudioMetadata;
use App\Jobs\ExtractVideoMetadata;
use App\Jobs\GenerateImageMetadata;
use App\Models\NewsItemMedia;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public function index(): View|RedirectResponse
    {
        return view('admin.settings.index');
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

        return view('admin.settings.ai', [
            'keySet' => $keySet,
            'statusOk' => $statusOk,
            'lastOkAt' => $lastOkAt,
            'lastError' => $lastError,
            'config' => $config,
        ]);
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

        $cronOk = $cronLastAt && \Carbon\Carbon::parse($cronLastAt)->diffInSeconds($now) <= $maxAge;
        $queueWorkerOk = $queueWorkerLastAt && \Carbon\Carbon::parse($queueWorkerLastAt)->diffInSeconds($now) <= $maxAge;

        return view('admin.settings.jobs', [
            'countAiPending' => $countAiPending,
            'countVideo' => $countVideo,
            'countAudio' => $countAudio,
            'cronOk' => $cronOk,
            'queueWorkerOk' => $queueWorkerOk,
            'cronLastAt' => $cronLastAt ? \Carbon\Carbon::parse($cronLastAt) : null,
            'queueWorkerLastAt' => $queueWorkerLastAt ? \Carbon\Carbon::parse($queueWorkerLastAt) : null,
        ]);
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
}

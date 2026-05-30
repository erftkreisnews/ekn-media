<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $clientId = config('services.microsoft.client_id');
        if (empty($clientId) || trim($clientId) === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Microsoft-Login ist nicht konfiguriert. Bitte MICROSOFT_CLIENT_ID und MICROSOFT_CLIENT_SECRET in der .env eintragen (Azure-App-Registrierung).'),
                ]);
        }

        return Socialite::driver('microsoft')
            ->scopes(['User.Read'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        // Werte direkt aus .env (umgeht Config-Cache)
        $clientId = trim((string) (env('MICROSOFT_CLIENT_ID') ?: config('services.microsoft.client_id')));
        $clientSecret = env('MICROSOFT_CLIENT_SECRET');
        if ($clientSecret !== null && $clientSecret !== '') {
            $clientSecret = trim((string) $clientSecret);
        } else {
            $clientSecret = trim((string) config('services.microsoft.client_secret'));
        }
        $redirect = env('MICROSOFT_REDIRECT_URI') ?: config('services.microsoft.redirect');
        if (empty($redirect)) {
            $redirect = rtrim(config('app.url'), '/').'/auth/microsoft/callback';
        }

        if (empty($clientSecret)) {
            logger()->warning('Microsoft callback: MICROSOFT_CLIENT_SECRET leer.');

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Microsoft-Login: MICROSOFT_CLIENT_SECRET fehlt in der .env. In Azure unter „Zertifikate & Geheimnisse“ einen geheimen Schlüssel anlegen, Wert kopieren und in .env eintragen: MICROSOFT_CLIENT_SECRET="..." (in Anführungszeichen).'),
                ]);
        }

        // Tenant-ID setzen (bei „Nur meine Organisation“ aus Azure → Übersicht → Verzeichnis-ID (Mandant))
        $tenant = trim((string) (env('MICROSOFT_TENANT_ID') ?: config('services.microsoft.tenant', 'common')));

        // Config setzen und Socialite-Driver neu laden, damit die Werte im Token-Request ankommen
        Config::set('services.microsoft', array_merge(config('services.microsoft', []), [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect' => $redirect,
            'tenant' => $tenant ?: 'common',
        ]));
        Socialite::forgetDrivers();

        try {
            $msUser = Socialite::driver('microsoft')->user();
        } catch (\Throwable $e) {
            logger()->error('Microsoft login callback failed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $message = __('Die Anmeldung mit Microsoft ist fehlgeschlagen. Bitte versuche es erneut.');
            if (str_contains($e->getMessage(), 'client_id') || str_contains($e->getMessage(), 'AADSTS900144')) {
                $message = __('Microsoft-Login: Client-Secret fehlt oder ist falsch. Bitte MICROSOFT_CLIENT_SECRET in der .env prüfen (Azure → App-Registrierung → Zertifikate & Geheimnisse).');
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $message,
                ]);
        }

        $email = Str::lower($msUser->getEmail() ?? '');

        if ($email === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Von Microsoft wurde keine E-Mail-Adresse zurückgegeben.'),
                ]);
        }

        $allowedDomains = config('auth_ms.allowed_domains', []);
        $domain = Str::after($email, '@');

        if (! in_array($domain, $allowedDomains, true)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('Diese E-Mail-Domain ist für den Login nicht freigeschaltet.'),
                ]);
        }

        $name = $msUser->getName() ?: $msUser->getNickname() ?: $email;

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name]
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Gleiche Ziel-Logik wie beim normalen Login
        if ($user->can(AdminPermissions::ACCESS)) {
            return redirect()->intended(route('admin.news.index', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

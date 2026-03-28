<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Klassischen Passwort-Login standardmäßig deaktivieren.
        // Optional können einzelne E-Mail-Adressen per config/auth_local.php freigeschaltet werden.
        $allowed = false;

        if (config('auth_local.enabled')) {
            $allowedEmails = config('auth_local.allowed_emails', []);
            $email = Str::lower($request->input('email', ''));

            if ($email !== '' && in_array($email, $allowedEmails, true)) {
                $allowed = true;
            }
        }

        if (! $allowed) {
            return back()
                ->withErrors([
                    'email' => __('Die Anmeldung mit E-Mail und Passwort ist deaktiviert. Bitte nutze den Microsoft-Login.'),
                ])
                ->onlyInput('email');
        }

        $request->authenticate();

        $request->session()->regenerate();

        // Admins landen direkt im Admin-Dashboard (Nachrichten-Übersicht)
        if ($request->user()->can('access_admin')) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

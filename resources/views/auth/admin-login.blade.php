<x-admin-login-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input
                id="email"
                class="block mt-1 w-full min-h-[44px] px-3 py-2"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Passwort')" />
            <x-text-input
                id="password"
                class="block mt-1 w-full min-h-[44px] px-3 py-2"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center gap-2 min-h-[44px] cursor-pointer">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-gray-300 text-[#092E48] shadow-sm focus:ring-[#092E48] w-4 h-4"
                    name="remember"
                >
                <span class="text-sm text-gray-600">{{ __('Angemeldet bleiben') }}</span>
            </label>
        </div>

        <div class="mt-6">
            <button
                type="submit"
                class="w-full inline-flex items-center justify-center min-h-[44px] px-4 py-2.5 bg-[#092E48] hover:bg-[#0a3a5c] focus:bg-[#0a3a5c] text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-[#092E48] focus:ring-offset-2 transition"
            >
                {{ __('Einloggen') }}
            </button>
        </div>

        @if (Route::has('password.request'))
            <p class="mt-4 text-center">
                <a
                    href="{{ route('password.request') }}"
                    class="text-sm text-[#092E48] hover:underline underline-offset-2"
                >
                    {{ __('Passwort vergessen?') }}
                </a>
            </p>
        @endif
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200">
        <p class="text-sm text-gray-500 text-center mb-3">
            {{ __('Oder mit Microsoft 365 anmelden') }}
        </p>
        <a
            href="{{ route('auth.microsoft.redirect') }}"
            class="inline-flex w-full min-h-[44px] items-center justify-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48] transition"
        >
            <svg class="w-5 h-5 mr-2 shrink-0" viewBox="0 0 23 23" aria-hidden="true">
                <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                <rect x="13" y="1" width="9" height="9" fill="#7fba00"/>
                <rect x="1" y="13" width="9" height="9" fill="#00a4ef"/>
                <rect x="13" y="13" width="9" height="9" fill="#ffb900"/>
            </svg>
            <span>{{ __('Mit Microsoft anmelden') }}</span>
        </a>
    </div>
</x-admin-login-layout>

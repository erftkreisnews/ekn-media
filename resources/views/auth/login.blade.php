<x-guest-layout>
    <h1 class="text-xl font-semibold text-[#092E48] mb-6 break-words">{{ __('Log in') }}</h1>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full min-h-[44px] px-3 py-2" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full min-h-[44px] px-3 py-2"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-start gap-2 min-h-[44px] py-1 cursor-pointer">
                <input id="remember_me" type="checkbox" class="mt-0.5 shrink-0 rounded border-gray-300 text-[#092E48] shadow-sm focus:ring-[#092E48] w-4 h-4" name="remember">
                <span class="text-sm text-gray-600 leading-snug">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-stretch sm:justify-end gap-3 mt-6">
            @if (Route::has('password.request'))
                <a class="inline-flex min-h-[44px] items-center justify-center sm:justify-start underline text-sm text-[#092E48] hover:opacity-90 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48] text-center order-2 sm:order-1 px-1 py-1 -mx-1" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
            <x-primary-button class="!bg-[#092E48] hover:!bg-[#0a3a5c] focus:!ring-[#092E48] order-1 sm:order-2 sm:ms-3 min-h-[44px] w-full sm:w-auto justify-center px-4 py-2.5">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-8 border-t border-gray-200 pt-6">
        <p class="text-sm text-gray-600 mb-3 break-words">
            {{ __('Oder melde dich mit deinem Microsoft 365 Konto an:') }}
        </p>
        <a href="{{ route('auth.microsoft.redirect') }}"
           class="inline-flex w-full sm:w-auto min-h-[44px] items-center justify-center px-4 py-2.5 border border-[#092E48] text-sm font-medium rounded-md text-[#092E48] bg-white hover:bg-white hover:text-[#0a3a5c] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48] transition">
            <svg class="w-5 h-5 mr-2" viewBox="0 0 23 23" aria-hidden="true">
                <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                <rect x="13" y="1" width="9" height="9" fill="#7fba00"/>
                <rect x="1" y="13" width="9" height="9" fill="#00a4ef"/>
                <rect x="13" y="13" width="9" height="9" fill="#ffb900"/>
            </svg>
            <span>{{ __('Mit Microsoft anmelden') }}</span>
        </a>
    </div>
</x-guest-layout>

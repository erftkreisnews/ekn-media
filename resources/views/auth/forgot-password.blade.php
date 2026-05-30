<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 break-words">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full min-h-[44px] px-3 py-2" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 mt-6">
            <x-primary-button class="min-h-[44px] !bg-[#092E48] hover:!bg-[#0a3a5c] focus:!ring-[#092E48] w-full sm:w-auto justify-center px-4 py-2.5">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

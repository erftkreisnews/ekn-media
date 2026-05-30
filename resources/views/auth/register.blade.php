<x-guest-layout>
    <h1 class="text-xl font-semibold text-[#092E48] mb-6 break-words">{{ __('Register') }}</h1>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full min-h-[44px] px-3 py-2" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full min-h-[44px] px-3 py-2" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full min-h-[44px] px-3 py-2"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full min-h-[44px] px-3 py-2"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-stretch sm:justify-end gap-3 mt-6">
            <a class="inline-flex min-h-[44px] items-center justify-center sm:justify-start underline text-sm text-[#092E48] hover:opacity-90 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#092E48] text-center order-2 sm:order-1 px-1 py-1 -mx-1" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>
            <x-primary-button class="!bg-[#092E48] hover:!bg-[#0a3a5c] focus:!ring-[#092E48] order-1 sm:order-2 sm:ms-4 min-h-[44px] w-full sm:w-auto justify-center px-4 py-2.5">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

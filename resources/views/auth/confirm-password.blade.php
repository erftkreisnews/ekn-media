<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 break-words">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full min-h-[44px] px-3 py-2"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-col sm:flex-row sm:justify-end gap-3 mt-6">
            <x-primary-button class="min-h-[44px] !bg-[#092E48] hover:!bg-[#0a3a5c] focus:!ring-[#092E48] w-full sm:w-auto justify-center px-4 py-2.5">
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

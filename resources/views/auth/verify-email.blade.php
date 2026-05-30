<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 break-words">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400 break-words">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
        <form method="POST" action="{{ route('verification.send') }}" class="w-full sm:w-auto min-w-0">
            @csrf

            <div>
                <x-primary-button class="min-h-[44px] !bg-[#092E48] hover:!bg-[#0a3a5c] focus:!ring-[#092E48] w-full sm:w-auto justify-center px-4 py-2.5">
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto flex justify-center sm:justify-end">
            @csrf

            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 px-2 py-2">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>

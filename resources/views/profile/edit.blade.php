@extends('layouts.frontend')

@section('title', __('Profile') . ' – ' . config('app.name'))

@section('content')
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm p-6 sm:p-8">
            <h1 class="text-xl font-semibold text-[#092E48] mb-6">{{ __('Profile') }}</h1>
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
@endsection

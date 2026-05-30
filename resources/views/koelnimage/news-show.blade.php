@extends('layouts.koelnimage')

@section('title', $newsItem->title.' | Kölnimage')

@section('meta_description')
@php
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($newsItem->teaser ?? ''))));
@endphp
@if($plain !== '')
{{ \Illuminate\Support\Str::limit($plain, 155) }}
@else
{{ \Illuminate\Support\Str::limit($newsItem->title.' — Pressefotos Kölnimage', 155) }}
@endif
@endsection

@section('og_type', 'article')

@php
    $hero = $newsItem->teaser_image_for_public;
    $heroUrl = $hero ? ($hero->public_url ?? $hero->preview_url) : null;
    if (! $heroUrl) {
        $firstPublic = $newsItem->publicPortalImages()->first(fn ($m) => $m->public_url || $m->preview_url);
        $heroUrl = $firstPublic ? ($firstPublic->preview_url ?? $firstPublic->public_url) : null;
    }
@endphp

@section('og_image', $heroUrl ?? '')

@section('content')
    <article class="prose prose-red max-w-none">
        <nav class="not-prose mb-4 text-sm text-gray-600" aria-label="Brotkrumen">
            <a href="{{ route('koelnimage.home') }}" title="Startseite" class="text-red-700 hover:underline">Startseite</a>
            <span class="mx-1">·</span>
            <span class="text-gray-800">{{ \Illuminate\Support\Str::limit($newsItem->title, 80) }}</span>
        </nav>
        <h1 class="text-3xl font-bold text-red-900">{{ $newsItem->title }}</h1>
        @if($newsItem->published_at)
            <p class="text-sm text-gray-500">{{ $newsItem->published_at->locale(app()->getLocale())->translatedFormat('j. F Y') }}</p>
        @endif
        @if(filled($newsItem->teaser))
            <div class="mt-4 text-gray-800">{!! $newsItem->teaser !!}</div>
        @endif
        @if(filled($newsItem->body))
            <div class="mt-6 text-gray-800">{!! $newsItem->bodyHtmlForWeb() !!}</div>
        @endif
    </article>

    @include('koelnimage.partials.gallery-hub', ['newsItem' => $newsItem])

    <p class="mt-8 text-center">
        <a href="{{ route('koelnimage.galleries.index') }}" title="Zurück zu Galerien" class="text-sm font-medium text-red-700 hover:text-red-800">← Zurück zu Galerien</a>
    </p>
@endsection

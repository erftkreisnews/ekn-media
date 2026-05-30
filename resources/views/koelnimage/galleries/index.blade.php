@extends('layouts.koelnimage')

@section('title', 'Galerien | Kölnimage')

@section('meta_description', 'Öffentliche Bildergalerien zu Events und Motorsport — Pressefotos Kölnimage für Redaktionen im Rheinland.')

@section('content')
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h1 class="text-2xl font-bold text-red-800 sm:text-3xl">Galerien</h1>
        <p class="mt-2 text-sm text-gray-700">Freigegebene Serien zu Meldungen ({{ $totalPublicPhotos }} Bilder gesamt).</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($galleries as $item)
                @include('koelnimage.partials.news-card', ['item' => $item])
            @endforeach
        </div>
        @if($galleries instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="mt-8">{{ $galleries->links() }}</div>
        @endif
    </section>
@endsection

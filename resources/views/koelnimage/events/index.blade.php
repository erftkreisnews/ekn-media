@extends('layouts.koelnimage')

@section('title', 'Kölnimage Events')

@section('meta_description', 'Eventkalender Kölnimage — geplante Einsätze und Termine für Sport und Events im Rheinland. Übersicht mit Filter und Suche.')

@section('content')
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-red-700">Events</h1>
        <p class="mt-2 text-sm text-gray-600">Geplante Termine und Einsätze.</p>
        @if(method_exists($events, 'total'))
            <p class="mt-4 text-sm text-gray-700">{{ $events->total() }} Treffer</p>
        @endif
        <ul class="mt-6 space-y-3">
            @foreach($events as $event)
                <li class="rounded-lg border border-gray-100 p-3 text-sm">
                    <span class="font-medium text-gray-900">{{ $event->name }}</span>
                    @if($event->date_label)
                        <span class="text-gray-500"> — {{ $event->date_label }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if(method_exists($events, 'links'))
            <div class="mt-6">{{ $events->links() }}</div>
        @endif
    </section>
@endsection

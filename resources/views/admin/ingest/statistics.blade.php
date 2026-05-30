@extends('layouts.admin')

@section('title', 'Ingest – Statistik')

@section('content')
    <div class="space-y-6 text-left min-w-0 max-w-full w-full">
        <nav class="mb-2 text-sm">
            <a href="{{ route('admin.ingest.index') }}" class="inline-flex items-center min-h-[2.75rem] font-medium text-[#092E48] hover:underline -ml-1 px-1 rounded-lg hover:bg-gray-100">← Sichtung</a>
            <span class="mx-2 text-gray-300">/</span>
            <span class="text-gray-600">Statistik</span>
        </nav>

        <div>
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 break-words">Ingest – Kennzahlen</h1>
            <p class="mt-1 text-sm text-gray-600 max-w-3xl break-words">
                Aggregierte Zähler über alle gespeicherten Ingest-Einträge (<code class="text-xs bg-gray-100 px-1 rounded">ingest_files</code>).
                Das sind Datenbank-Historie und Medien auf dem Server – unabhängig vom Upload-Ordner (dort können Dateien nach einer Frist automatisch gelöscht werden).
            </p>
        </div>

        @include('admin.ingest.partials.kpi-grid', ['stats' => $stats])

        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm max-w-3xl">
            <p class="font-medium text-gray-900">Hinweis</p>
            <p class="mt-2">
                Für die tägliche Arbeit nutzen Sie die <a href="{{ route('admin.ingest.index') }}" class="font-medium text-[#092E48] underline hover:no-underline">Sichtung</a>
                (standardmäßig nur heutige Eingänge) oder
                <a href="{{ route('admin.ingest.entries') }}" class="font-medium text-[#092E48] underline hover:no-underline">Alle Einträge</a>.
            </p>
        </div>
    </div>
@endsection

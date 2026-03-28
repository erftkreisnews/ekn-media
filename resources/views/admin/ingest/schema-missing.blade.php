@extends('layouts.admin')

@section('title', 'Video-Ingest – Einrichtung')

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-2xl font-semibold text-gray-900">Video-Ingest noch nicht bereit</h1>
        <p class="mt-3 text-gray-700">
            Die Datenbanktabellen für den Video-Ingest fehlen. Das passiert, wenn die Migrationen auf diesem Server noch nicht gelaufen sind.
        </p>
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            <p class="font-medium">Auf dem Server (SSH) ausführen:</p>
            <pre class="mt-2 overflow-x-auto rounded bg-white/80 p-3 font-mono text-xs text-gray-900">cd {{ base_path() }}
php artisan migrate --force</pre>
            <p class="mt-3 text-amber-900/90">
                Die Migration <code class="rounded bg-amber-100 px-1">2026_03_25_120000_create_ingest_tables</code> legt u. a. die Tabelle <code class="rounded bg-amber-100 px-1">ingest_files</code> an.
            </p>
        </div>
        <p class="mt-6">
            <a href="{{ route('admin.dashboard') }}" class="text-[#092E48] hover:underline">← Zum Dashboard</a>
        </p>
    </div>
@endsection

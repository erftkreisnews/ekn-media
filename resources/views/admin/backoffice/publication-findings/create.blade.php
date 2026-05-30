@extends('layouts.admin')

@section('content')
    <div class="space-y-6 max-w-3xl mx-auto">
        
        <div>
            <a href="{{ route('admin.backoffice.publication-findings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Fundstellen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Fundstelle erfassen</h1>
            @if($media)
                <p class="mt-1 text-sm text-gray-600">Bild #{{ $media->id }} · {{ $newsItem?->title }}</p>
            @endif
            <p class="mt-2 text-sm text-gray-600">
                Bei Art „Urheberrechtsverstoß“ wird automatisch eine <strong>Beweismittelmappe</strong> erstellt (ZIP + S3-Archiv):
                Originalfotos, eigenes News-Video (falls gewählt), YouTube-Download, Metadaten und Screenshots.
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <ul class="text-sm text-red-700 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('admin.backoffice.publication-findings.store') }}" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 space-y-6">
            @csrf
            @include('admin.backoffice.publication-findings._form', [
                'finding' => $finding,
                'kindLabels' => $kindLabels,
                'organizations' => $organizations,
                'newsItem' => $newsItem,
                'newsImages' => $newsImages,
                'newsImagesTitle' => $newsImagesTitle,
                'selectedMediaIds' => $selectedMediaIds,
            ])
            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">Speichern</button>
                <a href="{{ route('admin.images.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Zur Mediathek</a>
            </div>
        </form>
    </div>
@endsection

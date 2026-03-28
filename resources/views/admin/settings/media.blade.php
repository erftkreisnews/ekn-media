@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm text-gray-600 hover:text-[#092E48] mb-2 inline-block">← Einstellungen</a>
            <h1 class="text-2xl font-semibold text-gray-900">Bilder / Upload</h1>
            <p class="mt-1 text-sm text-gray-600">
                Gültige Bildgrößen und Upload-Limits für Pressebilder. Geändert wird dies über die Konfiguration (.env / config/media.php), nicht hier.
            </p>
        </div>

        <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-sm font-semibold tracking-wide text-gray-900 uppercase">Master-Größe und Upload-Regeln</h2>
            </div>
            <div class="px-6 py-4">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 font-medium">Master-Größe (max. Abmessungen)</dt>
                        <dd class="mt-1 text-gray-900">
                            <strong>{{ $masterLongEdge }} × {{ $masterShortEdge }} px</strong> (Seitenverhältnis 3:2 bzw. 2:3). Größere Bilder werden beim Upload abgelehnt.
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 font-medium">Max. Dateigröße pro Bild</dt>
                        <dd class="mt-1 text-gray-900">
                            <strong>{{ number_format($uploadMaxKb / 1024, 1, ',', '') }} MB</strong> ({{ number_format($uploadMaxKb) }} KB)
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 font-medium">Mindestlänge (lange Kante)</dt>
                        <dd class="mt-1 text-gray-900">
                            <strong>{{ $minLongEdge }} px</strong>. Bilder mit kürzerer langer Kante werden abgelehnt (keine Hochskalierung).
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-gray-500">
                    Anpassung z. B. in .env: <code class="bg-gray-100 px-1 rounded">IMAGE_MASTER_LONG_EDGE_MAX</code>, <code class="bg-gray-100 px-1 rounded">IMAGE_MASTER_SHORT_EDGE_MAX</code>, <code class="bg-gray-100 px-1 rounded">IMAGE_LONG_EDGE_MIN</code>, <code class="bg-gray-100 px-1 rounded">IMAGE_UPLOAD_MAX_KB</code>.
                </p>
            </div>
        </div>
    </div>
@endsection

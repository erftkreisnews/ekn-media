@extends('layouts.koelnimage')

@section('title', 'Neukunde werden | Kölnimage')

@section('meta_description', 'Zugang zu Pressefotos und Bewegtbild für Redaktionen und Sender bei Kölnimage beantragen — keine Selbstregistrierung, individuelle Freischaltung.')

@section('content')
    <div class="max-w-3xl">
        <p class="text-sm text-gray-500 mb-2">
            <a href="{{ route('koelnimage.home') }}" class="inline-flex min-h-[44px] items-center font-medium text-red-800 hover:text-red-700 hover:underline py-1 -my-1">← Startseite</a>
        </p>

        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-semibold text-red-700 leading-tight mb-4">
                Neukunde werden
            </h1>
            <p class="text-gray-700 leading-relaxed mb-6">
                Kölnimage liefert Pressefotos und Videomaterial für <strong class="font-semibold text-gray-900">Redaktionen, Sender, Vereine und Veranstalter</strong> im Raum Köln, Bonn und Rheinland.
                Eine automatische Selbstregistrierung gibt es nicht — wir legen Zugänge bewusst <strong class="font-semibold text-gray-900">individuell</strong> fest, passend zu Ihrer Nutzung und Freigabe.
            </p>

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 mb-6" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-6" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-gray-50/80 p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Ablauf</h2>
                <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm sm:text-base">
                    <li>Formular ausfüllen und absenden — oder uns direkt anrufen.</li>
                    <li>Zuerst <strong class="font-medium text-gray-900">Medienhaus</strong> und <strong class="font-medium text-gray-900">Redaktion bzw. Format</strong>, dann Ihre geschäftliche E-Mail.</li>
                    <li>Nach Prüfung erhalten Sie von uns die Zugangsdaten oder den nächsten Schritt.</li>
                </ol>
            </div>

            <div class="rounded-lg border border-red-100 bg-red-50/40 p-4 mb-8">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Redaktionsdesk (24h)</div>
                <a href="tel:+4922364809488" class="inline-flex min-h-[44px] items-center text-lg font-semibold text-red-800 hover:text-red-700 hover:underline py-1">02236 4809 488</a>
            </div>

            <form method="post" action="{{ route('koelnimage.neukunde.store') }}" class="relative space-y-4 mb-8" autocomplete="off">
                @csrf

                @if ($errors->has('form'))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                        {{ $errors->first('form') }}
                    </div>
                @endif

                <div>
                    <label for="medienhaus" class="block text-sm font-medium text-gray-700 mb-1">Medienhaus <span class="text-red-600">*</span></label>
                    <p class="text-xs text-gray-500 mb-2 leading-snug">
                        Verlag, Sender oder Organisation.<br>
                        Beispiele: WDR, Rheinische Post, Vereinsname.
                    </p>
                    <input type="text" name="medienhaus" id="medienhaus" value="{{ old('medienhaus') }}" required maxlength="200"
                           class="w-full min-w-0 min-h-[44px] rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-red-700 focus:ring-red-700">
                    @error('medienhaus')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="redaktion" class="block text-sm font-medium text-gray-700 mb-1">Redaktion / Format <span class="text-red-600">*</span></label>
                    <p class="text-xs text-gray-500 mb-2 leading-snug">
                        Konkrete Redaktion, Ressort oder Produkt.<br>
                        Beispiele: Online-Lokal, TV-Magazin, Vereinskommunikation.
                    </p>
                    <input type="text" name="redaktion" id="redaktion" value="{{ old('redaktion') }}" required maxlength="200"
                           class="w-full min-w-0 min-h-[44px] rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-red-700 focus:ring-red-700">
                    @error('redaktion')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Ansprechperson (optional)</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" maxlength="120"
                           class="w-full min-w-0 min-h-[44px] rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-red-700 focus:ring-red-700">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-Mail (geschäftlich) <span class="text-red-600">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="255"
                           class="w-full min-w-0 min-h-[44px] rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-red-700 focus:ring-red-700">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefon (optional)</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" maxlength="40"
                           class="w-full min-w-0 min-h-[44px] rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-red-700 focus:ring-red-700">
                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Nachricht (optional)</label>
                    <textarea name="message" id="message" rows="4" maxlength="5000"
                              class="w-full min-w-0 min-h-[7.5rem] rounded-lg border border-gray-300 px-3 py-3 text-base text-gray-900 shadow-sm focus:border-red-700 focus:ring-red-700">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <p class="text-xs text-gray-500">
                    Mit dem Absenden willigen Sie ein, dass wir Ihre Angaben zur Bearbeitung der Anfrage verarbeiten.
                    Details in der <a href="{{ url('/datenschutz') }}" class="text-red-800 underline hover:text-red-700">Datenschutzerklärung</a>.
                </p>

                <div class="absolute left-0 top-0 h-0 w-0 overflow-hidden opacity-0 pointer-events-none" aria-hidden="true">
                    <input type="text" name="website" value="{{ old('website') }}" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit"
                        class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center px-6 py-3 bg-red-700 text-white text-sm font-medium rounded-lg hover:bg-red-600 transition">
                    Anfrage senden
                </button>
            </form>

            <p class="text-sm text-gray-600 mb-6">
                Bereits Zugang? Dann nutzen Sie den
                <a href="{{ route('login') }}" class="text-red-800 font-medium hover:underline">Kundenlogin</a>.
            </p>

            <div class="pt-4 border-t border-gray-200 text-xs text-gray-500">
                <a href="{{ url('/impressum') }}" class="text-red-800 hover:underline">Impressum</a>
                ·
                <a href="{{ url('/datenschutz') }}" class="text-red-800 hover:underline">Datenschutz</a>
            </div>
        </article>
    </div>
@endsection

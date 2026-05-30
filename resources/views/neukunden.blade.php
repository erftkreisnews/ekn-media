@extends('layouts.frontend')

@section('title', 'Kunde werden | Medienportal | ' . config('app.name'))

@section('meta_description', 'Medienportal-Zugang für Redaktionen und Sender beantragen – Kontakt zur Freischaltung als Neukunde bei Erftkreis News Media.')

@section('content')
<div class="bg-ekn-50 -mx-4 sm:-mx-6 lg:-mx-8 py-8 sm:py-10 min-w-0">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 min-w-0">
        {{-- kein overflow-hidden: sonst können absolut positionierte Kinder (Honeypot) die Darstellung stören --}}
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 min-w-0 max-w-full">
            <div class="p-6 sm:p-8 min-w-0">
                <p class="text-sm text-slate-500 mb-2">
                    <a href="{{ route('home') }}" class="inline-flex min-h-[44px] items-center text-ekn-900 font-medium hover:underline py-1 -my-1">← Medienangebote</a>
                </p>
                <h1 class="text-2xl sm:text-3xl font-semibold text-ekn-900 leading-tight mb-4 break-words">
                    Kunde werden – Zugang zum Medienportal
                </h1>
                <p class="text-slate-700 text-base leading-relaxed mb-6">
                    Das Medienportal richtet sich an <strong>Redaktionen und Sender</strong>, die Bild- und Videomaterial von Erftkreis News nutzen möchten.
                    Eine automatische Selbstregistrierung gibt es nicht – wir legen Organisationen und Zugänge bewusst <strong>individuell</strong> an.
                </p>

                @if (session('status'))
                    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 mb-6 break-words" role="status">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-6 break-words" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5 mb-6">
                    <h2 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-3">So werden Sie Neukunde</h2>
                    <ol class="list-decimal list-inside space-y-2 text-slate-700 text-sm sm:text-base">
                        <li>Formular ausfüllen und absenden (oder telefonisch kontaktieren).</li>
                        <li>Zuerst <strong>Medienhaus</strong> und <strong>Redaktion/Format</strong> angeben, dann geschäftliche E-Mail.</li>
                        <li>Nach Prüfung erhalten Sie die Zugangsdaten bzw. den nächsten Schritt von uns.</li>
                    </ol>
                </div>

                <div class="rounded-xl border border-ekn-900/15 bg-white p-4 mb-8 min-w-0">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Redaktionsdesk (24h)</div>
                    <a href="tel:+4922364809488" class="inline-flex min-h-[44px] items-center text-lg font-semibold text-ekn-900 hover:underline py-1">02236 4809 488</a>
                </div>

                <form method="post" action="{{ route('neukunden.store') }}" class="relative space-y-4 mb-8" autocomplete="off">
                    @csrf

                    @if ($errors->has('form'))
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 break-words" role="alert">
                            {{ $errors->first('form') }}
                        </div>
                    @endif

                    <div>
                        <label for="medienhaus" class="block text-sm font-medium text-slate-700 mb-1">Medienhaus <span class="text-red-600">*</span></label>
                        <p class="text-xs text-slate-500 mb-2 leading-snug">
                            Verlag, Sender oder Medienunternehmen.<br>
                            Beispiele: WDR, Rheinische Post.
                        </p>
                        <input type="text" name="medienhaus" id="medienhaus" value="{{ old('medienhaus') }}" required maxlength="200"
                               class="w-full min-w-0 min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-ekn-900 focus:ring-ekn-900">
                        @error('medienhaus')<p class="mt-1 text-sm text-red-600 break-words">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="redaktion" class="block text-sm font-medium text-slate-700 mb-1">Redaktion / Format <span class="text-red-600">*</span></label>
                        <p class="text-xs text-slate-500 mb-2 leading-snug">
                            Konkrete Redaktion, Ressort oder Produkt.<br>
                            Beispiele: Online-Lokal, TV-Magazin.
                        </p>
                        <input type="text" name="redaktion" id="redaktion" value="{{ old('redaktion') }}" required maxlength="200"
                               class="w-full min-w-0 min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-ekn-900 focus:ring-ekn-900">
                        @error('redaktion')<p class="mt-1 text-sm text-red-600 break-words">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Ansprechperson (optional)</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" maxlength="120"
                               class="w-full min-w-0 min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-ekn-900 focus:ring-ekn-900">
                        @error('name')<p class="mt-1 text-sm text-red-600 break-words">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">E-Mail (geschäftlich) <span class="text-red-600">*</span></label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="255"
                               class="w-full min-w-0 min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-ekn-900 focus:ring-ekn-900">
                        @error('email')<p class="mt-1 text-sm text-red-600 break-words">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Telefon (optional)</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone') }}" maxlength="40"
                               class="w-full min-w-0 min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-slate-900 shadow-sm focus:border-ekn-900 focus:ring-ekn-900">
                        @error('phone')<p class="mt-1 text-sm text-red-600 break-words">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-slate-700 mb-1">Nachricht (optional)</label>
                        <textarea name="message" id="message" rows="4" maxlength="5000"
                                  class="w-full min-w-0 min-h-[7.5rem] rounded-xl border border-slate-300 px-3 py-3 text-base text-slate-900 shadow-sm focus:border-ekn-900 focus:ring-ekn-900">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-sm text-red-600 break-words">{{ $message }}</p>@enderror
                    </div>

                    <p class="text-xs text-slate-500">
                        Mit dem Absenden willigen Sie ein, dass wir Ihre Angaben zur Bearbeitung der Anfrage verarbeiten.
                        Details in der <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="text-ekn-900 underline">Datenschutzerklärung</a>.
                    </p>

                    {{-- Honeypot am Ende, ohne negativen Offset (Spam-Bots); leer lassen --}}
                    <div class="absolute left-0 top-0 h-0 w-0 overflow-hidden opacity-0 pointer-events-none" aria-hidden="true">
                        <input type="text" name="website" value="{{ old('website') }}" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit"
                            class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center px-6 py-3 bg-ekn-900 text-white text-sm font-medium rounded-xl hover:bg-ekn-800 transition">
                        Anfrage senden
                    </button>
                </form>

                <p class="text-sm text-slate-600 mb-6 break-words">
                    Bereits Zugang? Dann nutzen Sie den
                    <a href="{{ route('login') }}" class="text-ekn-900 font-medium hover:underline inline">Kundenlogin</a>.
                </p>

                <div class="pt-4 border-t border-slate-200 text-xs text-slate-500">
                    Rechtliches:
                    <a href="https://www.erftkreis-news.de/impressum" target="_blank" rel="noopener noreferrer" class="text-ekn-900 hover:underline">Impressum</a>
                    ·
                    <a href="https://www.erftkreis-news.de/datenschutzerklaerung" target="_blank" rel="noopener noreferrer" class="text-ekn-900 hover:underline">Datenschutz</a>
                </div>
            </div>
        </article>
    </div>
</div>
@endsection

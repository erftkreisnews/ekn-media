@extends('layouts.admin')
@section('content')
<div class="py-6 max-w-2xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-900">Neues Versandziel</h1>
    <p class="text-sm text-gray-600">@if($product){{ $product->organization->name }} · {{ $product->name }}@else{{ $customer->name }} (Organisation)@endif</p>
    <form method="POST" action="{{ $formAction }}" class="mt-6 bg-white rounded-lg border border-gray-200 p-6 space-y-4" x-data="{ type: '{{ old('type', 'ftp') }}' }">
        @csrf
        <div><label for="type" class="block text-sm font-medium text-gray-700">Typ *</label><select name="type" id="type" x-model="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="email">E-Mail</option><option value="ftp">FTP</option><option value="ftps">FTPS</option><option value="sftp">SFTP</option></select></div>
        <div><label for="label" class="block text-sm font-medium text-gray-700">Bezeichnung *</label><input type="text" name="label" id="label" value="{{ old('label') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="z.B. WDR Lokalzeit Köln SFTP"></div>
        <div><label class="inline-flex items-center"><input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48]"><span class="ml-2 text-sm">Aktiv</span></label></div>

        <div x-show="type === 'email'">
            <label class="block text-sm font-medium text-gray-700 mt-2">E-Mail an (Adressen, eine pro Zeile)</label>
            <textarea name="config_to" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="empfaenger@example.com">{{ old('config_to', is_array(old('config_to')) ? implode("\n", old('config_to')) : '') }}</textarea>
            <label class="block text-sm font-medium text-gray-700 mt-2">CC (optional)</label>
            <textarea name="config_cc" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
            <label class="block text-sm font-medium text-gray-700 mt-2">BCC (optional)</label>
            <textarea name="config_bcc" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
        </div>

        <div x-show="type === 'ftp' || type === 'ftps' || type === 'sftp'" x-cloak>
            <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Host *</label><input type="text" name="host" value="{{ old('host') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="ftp.example.com"></div>
            <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Port</label><input type="number" name="port" value="{{ old('port', 21) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="21 (FTP/FTPS) oder 22 (SFTP)"></div>
            <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Benutzername *</label><input type="text" name="username" value="{{ old('username') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
            <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Passwort</label><input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Nur eingeben zum Setzen"><p class="text-xs text-gray-500 mt-1">Wird verschlüsselt gespeichert. Leer lassen = später setzen.</p></div>
            <div class="mt-2" x-show="type === 'sftp'">
                <label class="block text-sm font-medium text-gray-700">Private Key (PEM, optional)</label>
                <textarea name="private_key" rows="6" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm font-mono text-sm" placeholder="-----BEGIN ... KEY-----&#10;...&#10;-----END ... KEY-----"></textarea>
                <label class="block text-sm font-medium text-gray-700 mt-1">Key-Passphrase (optional)</label>
                <input type="password" name="private_key_passphrase" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Nur wenn Key geschützt">
            </div>
            <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Zielpfad (Remote)</label><input type="text" name="remote_path" value="{{ old('remote_path', '/') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="/"></div>
            <div class="mt-2" x-show="type === 'ftp' || type === 'ftps'"><label class="inline-flex items-center"><input type="checkbox" name="passive" value="1" {{ old('passive', true) ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48]"><span class="ml-2 text-sm">Passiv-Modus</span></label></div>
            <div class="mt-2"><label class="block text-sm font-medium text-gray-700">Timeout (Sekunden)</label><input type="number" name="timeout" value="{{ old('timeout', 20) }}" min="5" max="120" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
            <div class="mt-2 space-y-2">
                <label class="inline-flex items-start gap-2"><input type="checkbox" name="ekn_live_folder_format" value="1" {{ old('ekn_live_folder_format') ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48] mt-0.5"><span class="text-sm"><span class="font-medium">EKN Live-Format:</span> Unterordner <code class="text-xs bg-gray-100 px-1 rounded">DD_MM_JJJJ-Titel bis zum:</code>, Videodateiname = Speicher-/Ingest-Name (z. B. <code class="text-xs bg-gray-100 px-1 rounded">…-sendefassung-…</code>)</span></label>
                <label class="inline-flex items-start gap-2"><input type="checkbox" name="wdr_subfolder_per_item" value="1" {{ old('wdr_subfolder_per_item') ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48] mt-0.5"><span class="text-sm">WDR-Format (nur wenn EKN Live-Format aus)</span></label>
            </div>
        </div>

        @can('admin.customers.billing_sensitive')
        <div class="border-t border-gray-200 pt-4 mt-4">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Externe Identifikatoren</h2>
            <p class="text-sm text-gray-600 mb-3">z. B. dpa Autorennummer, BILD Lieferanten-ID, WDR Lieferantennummer – werden bei Versand/E-Mail/Upload genutzt.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Bezeichnung (Label)</label><input type="text" name="external_reference_label" value="{{ old('external_reference_label') }}" placeholder="z.B. dpa Autorennummer" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700">Autorennummer (author_id)</label><input type="text" name="external_author_id" value="{{ old('external_author_id') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700">Lieferanten-ID (supplier_id)</label><input type="text" name="external_supplier_id" value="{{ old('external_supplier_id') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700">Vendor-Code (vendor_code)</label><input type="text" name="external_vendor_code" value="{{ old('external_vendor_code') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
            </div>
            <div class="mt-3 space-y-2">
                <label class="inline-flex items-center"><input type="checkbox" name="include_in_email" value="1" {{ old('include_in_email', true) ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48]"><span class="ml-2 text-sm">In E-Mail-Footer ausgeben</span></label><br>
                <label class="inline-flex items-center"><input type="checkbox" name="include_in_filename" value="1" {{ old('include_in_filename') ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48]"><span class="ml-2 text-sm">In Dateinamen anhängen (z. B. Datei_AFR12345.mp4)</span></label><br>
                <label class="inline-flex items-center"><input type="checkbox" name="generate_sidecar" value="1" {{ old('generate_sidecar') ? 'checked' : '' }} class="rounded border-gray-300 text-[#092E48]"><span class="ml-2 text-sm">Sidecar-Datei (z. B. .meta.json) beim Upload mitsenden</span></label>
            </div>
        </div>
        @endcan

        <div class="flex gap-3 pt-4"><button type="submit" class="px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48]">Anlegen</button><a href="{{ $backUrl }}" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white">Abbrechen</a></div>
    </form>
</div>
@endsection

@php
    $hasBrandColumn = \Illuminate\Support\Facades\Schema::hasColumn('news_items', 'brand_id');
    $current = old('brand_id', isset($newsItem) ? $newsItem->brand_id : ($newsBrandDefault ?? null));
@endphp
@if($hasBrandColumn && isset($brands) && $brands->isNotEmpty())
    <div id="section-brand" class="space-y-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <label for="brand_id" class="block text-sm font-medium text-gray-700">
            Marke / Ziel-Portal
        </label>
        <p class="text-xs text-gray-500">
            Legt fest, unter welcher Marke die Meldung öffentlich erscheint (z.&nbsp;B. Erftkreis News oder Kölnimage). Unabhängig vom Brand-Filter in der Kopfzeile.
        </p>
        <select
            name="brand_id"
            id="brand_id"
            x-model="selectedBrandId"
            class="block w-full max-w-lg rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
        >
            <option value="">— keine Zuordnung —</option>
            @foreach($brands as $b)
                <option value="{{ $b->id }}" @selected((string) $current === (string) $b->id)>
                    {{ $b->name }} ({{ $b->key }})
                </option>
            @endforeach
        </select>
        @error('brand_id')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endif

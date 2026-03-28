@props([])

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
    <h3 class="text-sm font-semibold text-ekn-900 uppercase tracking-wide mb-3">Filter</h3>
    <form method="get" action="{{ route('home') }}" class="space-y-3">
        <div>
            <label for="portal-filter-q" class="sr-only">Suchbegriff</label>
            <input type="search" name="q" id="portal-filter-q" value="{{ request('q') }}" placeholder="Suchbegriff eingeben" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-ekn-900 focus:ring-ekn-900">
        </div>
        <button type="submit" class="w-full px-4 py-2 bg-ekn-900 text-white text-sm font-medium rounded-lg hover:bg-ekn-800 transition">Suchen</button>
    </form>
</div>

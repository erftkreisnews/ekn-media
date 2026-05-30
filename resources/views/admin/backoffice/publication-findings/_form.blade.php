@php
    $finding = $finding ?? null;
    $newsImages = $newsImages ?? [];
    $newsImagesTitle = $newsImagesTitle ?? null;
    $selectedMediaIds = array_map('intval', (array) old('news_item_media_ids', $selectedMediaIds ?? []));
    if ($selectedMediaIds === [] && $finding?->relationLoaded('mediaItems')) {
        $selectedMediaIds = $finding->mediaItems->pluck('id')->map(fn ($id) => (int) $id)->all();
    } elseif ($selectedMediaIds === [] && $finding?->news_item_media_id) {
        $selectedMediaIds = [(int) $finding->news_item_media_id];
    }
    $initialNewsId = (string) old('news_item_id', $finding?->news_item_id ?? ($newsItem?->id ?? ''));
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="md:col-span-2">
        <label for="url" class="block text-sm font-medium text-gray-700">Fund-URL *</label>
        <input type="url" name="url" id="url" value="{{ old('url', $finding?->url) }}" required
               placeholder="https://…"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-[#092E48] focus:ring-[#092E48]">
        @error('url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500">URL aus Google Lens, Google News oder manueller Recherche einfügen.</p>
    </div>
    <div>
        <label for="kind" class="block text-sm font-medium text-gray-700">Art *</label>
        <select name="kind" id="kind" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            @foreach($kindLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('kind', $finding?->kind) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('kind')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="organization_id" class="block text-sm font-medium text-gray-700">Medienhaus (bei Lizenz)</label>
        <select name="organization_id" id="organization_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">— keins / unbekannt —</option>
            @foreach($organizations as $org)
                <option value="{{ $org->id }}" @selected((string) old('organization_id', $finding?->organization_id) === (string) $org->id)>
                    {{ $org->name }}
                </option>
            @endforeach
        </select>
        @error('organization_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="found_at" class="block text-sm font-medium text-gray-700">Gefunden am</label>
        <input type="date" name="found_at" id="found_at"
               value="{{ old('found_at', optional($finding?->found_at)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
    </div>

    <div>
        <label for="news_item_id" class="block text-sm font-medium text-gray-700">Meldung (News-ID)</label>
        <input type="number" name="news_item_id" id="news_item_id" min="1"
               value="{{ $initialNewsId }}"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
        <p class="mt-1 text-xs text-gray-500">Nach Eingabe können unten mehrere Bilder dieser Meldung ausgewählt werden.</p>
    </div>

    <div class="md:col-span-2"
         x-data="publicationFindingMediaPicker({
            endpoint: @js(route('admin.backoffice.publication-findings.news-images')),
            initialNewsId: @js($initialNewsId),
            initialMediaIds: @js($selectedMediaIds),
            initialImages: @js($newsImages),
            initialTitle: @js($newsImagesTitle),
         })"
         x-init="init()">
        <label class="block text-sm font-medium text-gray-700">Bilder &amp; Videos (Media-IDs)</label>
        <p class="mt-1 text-xs text-gray-500">
            Bilder per Klick wählen. <strong>Videos der Meldung</strong> werden bei gesetzter News-ID automatisch verknüpft und in der Beweismittelmappe von S3 übernommen.
        </p>

        <p class="mt-1 text-xs text-gray-500" x-show="title" x-cloak>
            Meldung: <span class="font-medium text-gray-700" x-text="title"></span>
            <span x-show="selectedMediaIds.length > 0" x-cloak>
                · <span x-text="selectedMediaIds.length"></span> Bild(er) gewählt
            </span>
        </p>

        <p class="mt-2 text-sm text-gray-500" x-show="!newsId">Bitte zuerst eine News-ID eingeben.</p>
        <p class="mt-2 text-sm text-gray-500" x-show="newsId && loading" x-cloak>Bilder werden geladen …</p>
        <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error" x-cloak></p>
        <p class="mt-2 text-sm text-amber-700" x-show="newsId && !loading && !error && images.length === 0" x-cloak>
            Zu dieser Meldung sind keine Bilder hinterlegt.
        </p>

        <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 max-h-96 overflow-y-auto" x-show="images.length > 0" x-cloak>
            <template x-for="img in images" :key="img.id">
                <label class="relative block cursor-pointer rounded-lg border-2 bg-white p-2 transition-colors"
                       :class="isSelected(img.id) ? 'border-[#092E48] ring-2 ring-[#092E48]/20' : 'border-gray-200 hover:border-gray-300'">
                    <input type="checkbox" name="news_item_media_ids[]" class="sr-only"
                           :value="img.id"
                           :checked="isSelected(img.id)"
                           @change="toggleMedia(img.id)">
                    <img :src="img.thumb_url" :alt="img.label" class="h-24 w-full rounded object-cover bg-gray-100" loading="lazy">
                    <span class="absolute top-3 right-3 rounded px-1.5 py-0.5 text-[10px] font-medium uppercase"
                          :class="img.type === 'video' ? 'bg-violet-700 text-white' : 'bg-gray-800/80 text-white'"
                          x-text="img.type === 'video' ? 'Video' : 'Bild'"></span>
                    <span x-show="img.auto_link" x-cloak
                          class="absolute top-3 left-3 rounded px-1.5 py-0.5 text-[10px] font-medium bg-emerald-700 text-white">
                        auto
                    </span>
                    <span class="mt-2 block text-xs font-mono text-gray-600" x-text="'#' + img.id"></span>
                    <span class="block text-xs text-gray-700 line-clamp-2" x-text="img.label"></span>
                </label>
            </template>
        </div>

        <button type="button" class="mt-3 text-sm text-[#092E48] hover:underline"
                x-show="selectedMediaIds.length > 0" x-cloak
                @click="clearSelection()">
            Alle Bilder abwählen
        </button>

        @error('news_item_media_ids')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        @error('news_item_media_ids.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="page_title" class="block text-sm font-medium text-gray-700">Seitentitel / Artikelüberschrift</label>
        <input type="text" name="page_title" id="page_title" value="{{ old('page_title', $finding?->page_title) }}"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
    </div>
    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-gray-700">Notizen</label>
        <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('notes', $finding?->notes) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="confirmed" value="1" @checked(old('confirmed', $finding?->confirmed)) class="rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]">
            <span class="text-sm text-gray-700">Als geprüft / bestätigt markieren</span>
        </label>
    </div>
</div>

@push('scripts')
<script>
    function publicationFindingMediaPicker(config) {
        return {
            endpoint: config.endpoint,
            newsId: config.initialNewsId ? String(config.initialNewsId) : '',
            selectedMediaIds: (config.initialMediaIds ?? []).map((id) => String(id)),
            images: config.initialImages ?? [],
            title: config.initialTitle ?? '',
            loading: false,
            error: null,
            isSelected(id) {
                return this.selectedMediaIds.includes(String(id));
            },
            toggleMedia(id) {
                const key = String(id);
                if (this.isSelected(key)) {
                    this.selectedMediaIds = this.selectedMediaIds.filter((item) => item !== key);
                } else {
                    this.selectedMediaIds.push(key);
                }
            },
            clearSelection() {
                const videoIds = this.images
                    .filter((img) => img.type === 'video' || img.auto_link)
                    .map((img) => String(img.id));
                this.selectedMediaIds = videoIds;
            },
            autoSelectVideos() {
                for (const img of this.images) {
                    if ((img.type === 'video' || img.auto_link) && ! this.isSelected(img.id)) {
                        this.selectedMediaIds.push(String(img.id));
                    }
                }
            },
            init() {
                const newsInput = document.getElementById('news_item_id');
                if (!newsInput) {
                    return;
                }
                newsInput.addEventListener('change', () => {
                    this.newsId = newsInput.value ? String(newsInput.value) : '';
                    this.loadImages();
                });
                newsInput.addEventListener('input', () => {
                    this.newsId = newsInput.value ? String(newsInput.value) : '';
                });
                if (this.newsId && this.images.length === 0) {
                    this.loadImages();
                } else if (this.images.length > 0) {
                    this.autoSelectVideos();
                }
            },
            async loadImages() {
                const id = parseInt(this.newsId, 10);
                if (!id || id < 1) {
                    this.images = [];
                    this.title = '';
                    this.error = null;
                    this.selectedMediaIds = [];

                    return;
                }
                this.loading = true;
                this.error = null;
                try {
                    const response = await fetch(`${this.endpoint}?news_item_id=${id}`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        this.images = [];
                        this.title = '';
                        this.error = response.status === 404
                            ? 'Meldung nicht gefunden.'
                            : 'Bilder konnten nicht geladen werden.';

                        return;
                    }
                    const data = await response.json();
                    this.images = data.images ?? [];
                    this.title = data.title ?? '';
                    const available = new Set(this.images.map((img) => String(img.id)));
                    this.selectedMediaIds = this.selectedMediaIds.filter((mediaId) => available.has(mediaId));
                    this.autoSelectVideos();
                } catch {
                    this.error = 'Bilder konnten nicht geladen werden.';
                    this.images = [];
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endpush

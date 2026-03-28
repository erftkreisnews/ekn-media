{{-- Medienverwaltung: Links zu Bilder | Videos | Audios --}}
<section
    id="section-media"
    class="bg-white shadow-sm rounded-lg border border-gray-200"
>
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">
            Bilder, Videos &amp; Audios
        </h2>
    </div>

    <div class="border-b border-gray-200">
        <nav class="flex gap-0 px-6" aria-label="Medien-Tabs">
            <a
                href="{{ route('admin.video.index') }}"
                class="px-4 py-3 text-sm font-medium border-b-2 border-transparent -mb-px text-gray-600 hover:text-gray-900 hover:border-[#092E48] hover:text-[#092E48] hover:bg-blue-50/50 transition-colors"
            >
                Videos
            </a>
            <a
                href="{{ route('admin.audio.index') }}"
                class="px-4 py-3 text-sm font-medium border-b-2 border-transparent -mb-px text-gray-600 hover:text-gray-900 hover:border-[#092E48] hover:text-[#092E48] hover:bg-blue-50/50 transition-colors"
            >
                Audios
            </a>
        </nav>
    </div>

    <div class="px-6 py-6"></div>
</section>

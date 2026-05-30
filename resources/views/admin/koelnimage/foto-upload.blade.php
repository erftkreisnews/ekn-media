@extends('layouts.admin')

@section('content')
    <div
        class="space-y-6 min-w-0 max-w-4xl"
        x-data="koelnimageFotoUpload({
            uploadUrl: @js($uploadUrl),
            editUrl: @js($editUrl),
            initialCount: {{ (int) $newsItem->images_count }},
            csrf: @js(csrf_token()),
        })"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold text-gray-900 break-words">Fotos hochladen</h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $newsItem->title }}
                    <span class="text-gray-400">· ID {{ $newsItem->id }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a
                    href="{{ $editUrl }}"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-md border border-[#092E48] text-[#092E48] hover:bg-[#092E48]/5"
                >
                    Metadaten &amp; Versand
                </a>
                <a
                    href="{{ route('admin.news.index') }}"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"
                >
                    Zur Liste
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Foto-Upload</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Dateien werden sofort einzeln hochgeladen — kein Warten auf „Speichern“.
                    </p>
                </div>
                <p class="text-sm font-medium text-[#092E48]" x-text="uploadedCount + ' Foto(s) in dieser Galerie'"></p>
            </div>

            <div class="px-6 py-4 border-b border-gray-200">
                <input
                    x-ref="fileInput"
                    type="file"
                    accept="image/jpeg,.jpg,.jpeg,image/png,.png,image/webp,.webp"
                    multiple
                    class="sr-only"
                    @change="queueFiles($event.target.files); $event.target.value = ''"
                >
                <div
                    @click="$refs.fileInput.click()"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="queueFiles($event.dataTransfer.files); dragOver = false"
                    :class="dragOver ? 'border-[#092E48] bg-[#092E48]/5' : 'border-gray-300'"
                    class="border-2 border-dashed rounded-2xl p-10 text-center cursor-pointer transition-colors hover:border-[#092E48] hover:bg-gray-50/80"
                >
                    <p class="text-sm font-medium text-gray-700">Fotos hier ablegen oder klicken</p>
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG oder WEBP — Upload startet automatisch</p>
                </div>
            </div>

            <div class="px-6 py-4 space-y-3" x-show="queue.length > 0 || errors.length > 0" x-cloak>
                <template x-for="item in queue" :key="item.id">
                    <div class="flex items-center gap-3 text-sm">
                        <span
                            class="shrink-0 w-2 h-2 rounded-full"
                            :class="{
                                'bg-gray-300': item.status === 'pending',
                                'bg-amber-400 animate-pulse': item.status === 'uploading',
                                'bg-emerald-500': item.status === 'done',
                                'bg-red-500': item.status === 'error' || item.status === 'skipped',
                            }"
                        ></span>
                        <span class="flex-1 truncate text-gray-800" x-text="item.name"></span>
                        <span class="text-xs text-gray-500" x-text="item.statusLabel"></span>
                    </div>
                </template>
                <template x-for="(msg, idx) in errors" :key="'err-' + idx">
                    <p class="text-sm text-red-600" x-text="msg"></p>
                </template>
            </div>

            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-xs text-gray-500">
                Tipp: Untertitel, Schlagwörter und Versand nach dem Upload unter „Metadaten &amp; Versand“.
            </div>
        </div>
    </div>
@endsection

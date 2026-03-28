@props([])

@if(session('success'))
    <div x-data="{ open: true }" x-show="open" x-transition class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 mb-4" role="alert">
        <span class="flex-1">{{ session('success') }}</span>
        <button type="button" @click="open = false" class="shrink-0 p-1 rounded hover:bg-green-100 text-green-600" aria-label="Schließen">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
        </button>
    </div>
@endif

@if(session('error'))
    <div x-data="{ open: true }" x-show="open" x-transition class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 mb-4" role="alert">
        <span class="flex-1">{{ session('error') }}</span>
        <button type="button" @click="open = false" class="shrink-0 p-1 rounded hover:bg-red-100 text-red-600" aria-label="Schließen">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
        </button>
    </div>
@endif

@if(isset($errors) && $errors->any())
    <div x-data="{ open: true }" x-show="open" x-transition class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 mb-4" role="alert">
        <div class="flex items-start gap-3">
            <span class="font-semibold shrink-0">Bitte prüfen:</span>
            <ul class="list-none pl-0 flex-1 space-y-1">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
        <div class="flex justify-end mt-2">
            <button type="button" @click="open = false" class="text-xs px-2 py-1 rounded hover:bg-amber-100">Schließen</button>
        </div>
    </div>
@endif

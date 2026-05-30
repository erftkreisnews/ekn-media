@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold text-gray-900">News-Löschprotokoll</h1>
            <p class="text-sm text-gray-600">Audit-Trail für gelöschte News (wer, was, wann).</p>
            <p class="text-sm">
                <a href="{{ route('admin.settings.index') }}" class="text-[#092E48] hover:underline">← zurück zu Einstellungen</a>
            </p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <form method="GET" action="{{ route('admin.settings.news-delete-audit') }}" class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Suche: News-ID, Titel oder Slug"
                       class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-[#092E48] hover:bg-[#0b3858]">
                    Filtern
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Zeitpunkt</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">News-ID</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Benutzer</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($audits as $a)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ optional($a->deleted_at)->format('d.m.Y H:i:s') ?? optional($a->created_at)->format('d.m.Y H:i:s') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-xs text-gray-800">#{{ $a->news_item_id }}</td>
                            <td class="px-3 py-2 text-gray-900 max-w-[28rem]">
                                <span class="line-clamp-2" title="{{ $a->title }}">{{ $a->title ?: '—' }}</span>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $a->slug ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $a->status ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-700">
                                @if($a->user)
                                    {{ $a->user->name ?: ('User #'.$a->user->id) }}
                                @elseif($a->user_id)
                                    User #{{ $a->user_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $a->request_ip ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500">Keine Löschprotokolle vorhanden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $audits->links() }}
            </div>
        </div>
    </div>
@endsection


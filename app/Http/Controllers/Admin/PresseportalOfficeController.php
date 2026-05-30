<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresseportalOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PresseportalOfficeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! Schema::hasTable('presseportal_offices')) {
            return redirect()->route('admin.settings.index')
                ->with('error', 'Presseportal-Tabellen fehlen. Bitte Migrationen ausführen.');
        }

        $offices = PresseportalOffice::query()->orderBy('sort_order')->orderBy('name')->get();
        $apiKey = config('presseportal.api_key');
        $keySet = is_string($apiKey) && trim($apiKey) !== '';

        return view('admin.settings.presseportal', compact('offices', 'keySet'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('presseportal_offices')) {
            return redirect()->route('admin.settings.presseportal')
                ->with('error', 'Tabelle fehlt.');
        }

        $data = $request->validate([
            'office_id' => ['required', 'integer', 'min:1', 'unique:presseportal_offices,office_id'],
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        PresseportalOffice::query()->create([
            'office_id' => $data['office_id'],
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('admin.settings.presseportal')->with('status', 'Dienststelle gespeichert.');
    }

    public function update(Request $request, PresseportalOffice $office): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $office->update([
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
            'sort_order' => $data['sort_order'] ?? $office->sort_order,
            'is_active' => $request->boolean('is_active', $office->is_active),
        ]);

        return redirect()->route('admin.settings.presseportal')->with('status', 'Eintrag aktualisiert.');
    }

    public function destroy(PresseportalOffice $office): RedirectResponse
    {
        $office->delete();

        return redirect()->route('admin.settings.presseportal')->with('status', 'Dienststelle entfernt.');
    }
}

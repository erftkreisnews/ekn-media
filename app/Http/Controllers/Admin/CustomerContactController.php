<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerContactController extends Controller
{
    public function index(Organization $customer): View
    {
        $contacts = $customer->contacts()->with('product')->orderBy('name')->get();

        return view('admin.customers.contacts.index', compact('customer', 'contacts'));
    }

    public function create(Organization $customer): View
    {
        $products = $customer->products()->orderBy('name')->get();

        return view('admin.customers.contacts.create', compact('customer', 'products'));
    }

    public function store(Request $request, Organization $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'exists:products,id'],
            'use_for_invoice' => ['boolean'],
            'billing_department' => ['nullable', 'string', 'in:newsroom,studio_koeln,studio_bonn'],
            'billing_type' => ['nullable', 'string', 'in:lizenz,honorar'],
        ]);
        $validated['organization_id'] = $customer->id;
        $validated['name'] = trim((string) ($validated['name'] ?? ''));
        if ($validated['name'] === '') {
            $validated['name'] = trim((string) $validated['email']);
        }
        $validated['use_for_invoice'] = $request->boolean('use_for_invoice');
        $validated['billing_department'] = $request->input('billing_department') ?: null;
        $validated['billing_type'] = $request->input('billing_type') ?: null;
        if (! empty($validated['product_id'])) {
            $p = $customer->products()->find($validated['product_id']);
            if (! $p) {
                $validated['product_id'] = null;
            }
        }

        Contact::create($validated);

        return redirect()
            ->route('admin.customers.contacts.index', $customer)
            ->with('status', 'Kontakt wurde angelegt.');
    }

    public function edit(Organization $customer, Contact $contact): View
    {
        if ($contact->organization_id !== $customer->id) {
            abort(404);
        }
        $products = $customer->products()->orderBy('name')->get();

        return view('admin.customers.contacts.edit', compact('customer', 'contact', 'products'));
    }

    public function update(Request $request, Organization $customer, Contact $contact): RedirectResponse
    {
        if ($contact->organization_id !== $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'exists:products,id'],
            'use_for_invoice' => ['boolean'],
            'billing_department' => ['nullable', 'string', 'in:newsroom,studio_koeln,studio_bonn'],
            'billing_type' => ['nullable', 'string', 'in:lizenz,honorar'],
        ]);
        $validated['name'] = trim((string) ($validated['name'] ?? ''));
        if ($validated['name'] === '') {
            $validated['name'] = trim((string) $validated['email']);
        }
        $validated['use_for_invoice'] = $request->boolean('use_for_invoice');
        $validated['billing_department'] = $request->input('billing_department') ?: null;
        $validated['billing_type'] = $request->input('billing_type') ?: null;
        if (! empty($validated['product_id'])) {
            $p = $customer->products()->find($validated['product_id']);
            if (! $p) {
                $validated['product_id'] = null;
            }
        }

        $contact->update($validated);

        return redirect()
            ->route('admin.customers.contacts.index', $customer)
            ->with('status', 'Kontakt wurde aktualisiert.');
    }

    public function destroy(Organization $customer, Contact $contact): RedirectResponse
    {
        if ($contact->organization_id !== $customer->id) {
            abort(404);
        }

        $contact->delete();

        return redirect()
            ->route('admin.customers.contacts.index', $customer)
            ->with('status', 'Kontakt wurde gelöscht.');
    }
}

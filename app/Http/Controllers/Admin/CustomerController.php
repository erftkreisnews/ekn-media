<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::withCount(['products', 'contacts'])
            ->orderBy('name')
            ->paginate(15);

        return view('admin.customers.index', compact('organizations'));
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCustomer($request);
        Organization::create($validated);

        return redirect()->route('admin.customers.index')->with('status', 'Medienhaus wurde angelegt.');
    }

    public function show(Organization $customer): View
    {
        $organizationId = (int) $customer->getKey();

        // Abteilungen/Rechnungseinheiten: explizit nach organization_id laden
        $products = \App\Models\Product::query()
            ->where('organization_id', $organizationId)
            ->withCount('deliveryDestinations')
            ->orderBy('name')
            ->get();
        $customer->setRelation('products', $products);

        // Kontakte: explizit nach organization_id laden
        $contacts = \App\Models\Contact::query()
            ->where('organization_id', $organizationId)
            ->with('product')
            ->orderBy('name')
            ->get();
        $customer->setRelation('contacts', $contacts);

        // Versandziele: organization_id ODER product_id in (Produkte dieser Organisation)
        $productIds = $products->pluck('id')->all();
        $deliveryDestinations = \App\Models\DeliveryDestination::query()
            ->where(function ($q) use ($organizationId, $productIds) {
                $q->where('organization_id', $organizationId);
                if ($productIds !== []) {
                    $q->orWhereIn('product_id', $productIds);
                }
            })
            ->with('product')
            ->orderBy('label')
            ->get();
        $customer->setRelation('deliveryDestinations', $deliveryDestinations);

        return view('admin.customers.show', compact('customer'));
    }

    public function edit(Organization $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Organization $customer): RedirectResponse
    {
        $validated = $this->validateCustomer($request);
        $customer->update($validated);

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Medienhaus wurde aktualisiert.');
    }

    private function validateCustomer(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['boolean'],
            'external_reference_label' => ['nullable', 'string', 'max:255'],
            'external_author_id' => ['nullable', 'string', 'max:255'],
            'external_supplier_id' => ['nullable', 'string', 'max:255'],
            'external_vendor_code' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['active'] = $request->boolean('active', true);
        $validated['external_reference_label'] = $request->filled('external_reference_label') ? trim($request->input('external_reference_label')) : null;
        $validated['external_author_id'] = $request->filled('external_author_id') ? trim($request->input('external_author_id')) : null;
        $validated['external_supplier_id'] = $request->filled('external_supplier_id') ? trim($request->input('external_supplier_id')) : null;
        $validated['external_vendor_code'] = $request->filled('external_vendor_code') ? trim($request->input('external_vendor_code')) : null;

        return $validated;
    }

    public function destroy(Organization $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('admin.customers.index')->with('status', 'Organisation wurde gelöscht.');
    }
}

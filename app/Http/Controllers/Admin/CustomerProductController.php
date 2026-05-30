<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProductToLexwareJob;
use App\Models\Organization;
use App\Models\Product;
use App\Services\Lexware\LexwareContactService;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerProductController extends Controller
{
    public function index(Organization $customer): View
    {
        $products = $customer->products()->with('organization')->withCount('deliveryDestinations')->orderBy('name')->get();

        return view('admin.customers.products.index', compact('customer', 'products'));
    }

    public function create(Organization $customer): View
    {
        return view('admin.customers.products.create', compact('customer'));
    }

    public function store(Request $request, Organization $customer): RedirectResponse
    {
        $validated = $this->validateProduct($request, null);
        $validated['organization_id'] = $customer->id;
        $validated['active'] = $request->boolean('active', true);
        $product = Product::create($validated);
        if (config('lexware.api_key')) {
            SyncProductToLexwareJob::dispatch($product);
        }

        return redirect()->route('admin.customers.products.index', $customer)->with('status', 'Abteilung / Redaktion wurde angelegt.');
    }

    public function edit(Organization $customer, Product $product): View
    {
        if ($product->organization_id !== $customer->id) {
            abort(404);
        }

        return view('admin.customers.products.edit', compact('customer', 'product'));
    }

    public function update(Request $request, Organization $customer, Product $product): RedirectResponse
    {
        if ($product->organization_id !== $customer->id) {
            abort(404);
        }
        $validated = $this->validateProduct($request, $product);
        $validated['active'] = $request->boolean('active', true);
        $product->update($validated);
        if (config('lexware.api_key')) {
            SyncProductToLexwareJob::dispatch($product->fresh());
        }

        return redirect()->route('admin.customers.products.index', $customer)->with('status', 'Abteilung / Redaktion wurde aktualisiert.');
    }

    public function importFromLexware(
        Request $request,
        Organization $customer,
        Product $product,
        LexwareContactService $lexwareContactService
    ): RedirectResponse {
        abort_unless($request->user()->can(AdminPermissions::CUSTOMERS_BILLING_SENSITIVE), 403);

        if ($product->organization_id !== $customer->id) {
            abort(404);
        }

        if (! config('lexware.api_key')) {
            return redirect()
                ->route('admin.customers.products.edit', [$customer, $product])
                ->with('error', 'Lexware ist nicht konfiguriert.');
        }

        if (! $product->lexware_contact_id) {
            return redirect()
                ->route('admin.customers.products.edit', [$customer, $product])
                ->with('error', 'Fuer diese Redaktion ist noch keine Lexware Kontakt-ID hinterlegt.');
        }

        if (! $lexwareContactService->importContactData($product->fresh('organization'))) {
            return redirect()
                ->route('admin.customers.products.edit', [$customer, $product])
                ->with('error', 'Die Lexware-Daten konnten nicht geladen werden.');
        }

        return redirect()
            ->route('admin.customers.products.edit', [$customer, $product])
            ->with('status', 'Lexware-Daten wurden in die Redaktion übernommen.');
    }

    public function destroy(Organization $customer, Product $product): RedirectResponse
    {
        if ($product->organization_id !== $customer->id) {
            abort(404);
        }
        $product->delete();

        return redirect()->route('admin.customers.products.index', $customer)->with('status', 'Abteilung / Redaktion wurde gelöscht.');
    }

    private function validateProduct(Request $request, ?Product $existing): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);

        $billingFields = [
            'buyer_reference',
            'billing_name',
            'billing_company',
            'billing_street',
            'billing_postal_code',
            'billing_city',
            'billing_country',
            'billing_email_primary',
            'billing_email_secondary',
            'billing_notes',
        ];

        if ($request->user()->can(AdminPermissions::CUSTOMERS_BILLING_SENSITIVE)) {
            $request->validate([
                'buyer_reference' => ['nullable', 'string', 'max:255'],
                'billing_name' => ['nullable', 'string', 'max:255'],
                'billing_company' => ['nullable', 'string', 'max:255'],
                'billing_street' => ['nullable', 'string', 'max:255'],
                'billing_postal_code' => ['nullable', 'string', 'max:20'],
                'billing_city' => ['nullable', 'string', 'max:255'],
                'billing_country' => ['nullable', 'string', 'max:100'],
                'billing_email_primary' => ['nullable', 'email'],
                'billing_email_secondary' => ['nullable', 'email'],
                'billing_notes' => ['nullable', 'string', 'max:5000'],
            ]);
            foreach ($billingFields as $field) {
                $validated[$field] = $request->filled($field) ? trim((string) $request->input($field)) : null;
            }
        } elseif ($existing) {
            foreach ($billingFields as $field) {
                $validated[$field] = $existing->{$field};
            }
        } else {
            foreach ($billingFields as $field) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }
}

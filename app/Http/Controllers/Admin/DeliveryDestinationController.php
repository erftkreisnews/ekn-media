<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\UploadMediaToDestinationJob;
use App\Models\DeliveryDestination;
use App\Models\DeliveryRun;
use App\Models\Organization;
use App\Models\Product;
use App\Services\DeliveryDestinationConnectionTester;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryDestinationController extends Controller
{
    // ---------- Product-scoped destinations ----------

    public function index(Product $product): View
    {
        $product->load('organization');
        $destinations = $product->deliveryDestinations()->with('organization')->orderBy('label')->get();

        return view('admin.destinations.index', [
            'product' => $product,
            'customer' => null,
            'destinations' => $destinations,
            'backRoute' => 'admin.customers.show',
            'backParams' => [$product->organization],
            'itemRouteParams' => [], // edit/update/destroy haben nur {destination}
            'createRoute' => 'admin.destinations.create',
            'createParams' => [$product],
            'editRoute' => 'admin.destinations.edit',
            'destroyRoute' => 'admin.destinations.destroy',
            'indexLabel' => $product->organization->name.' · '.$product->name,
        ]);
    }

    public function create(Product $product): View
    {
        $product->load('organization');

        return view('admin.destinations.create', [
            'product' => $product,
            'customer' => null,
            'destination' => null,
            'formAction' => route('admin.destinations.store', $product),
            'formMethod' => 'POST',
            'backUrl' => route('admin.destinations.index', $product),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateDestination($request);
        $validated['product_id'] = $product->id;
        $validated['organization_id'] = $product->organization_id;

        $dest = DeliveryDestination::create($validated);
        $this->storeCredentialsFromRequest($dest, $request, $validated['type']);
        $this->storeWdrSubfolderConfig($dest, $request, $validated['type']);

        return redirect()->route('admin.destinations.index', $product)->with('status', 'Versandziel wurde angelegt.');
    }

    /**
     * Bearbeiten über direkte URL /admin/destinations/{id}/edit (nur {destination} in der Route).
     * Leitet bei Organisation-Versandzielen auf customers.destinations.edit um.
     */
    public function editByDestination(DeliveryDestination $destination): View|RedirectResponse
    {
        $destination->load(['product.organization', 'organization']);

        if ($destination->product_id) {
            $product = $destination->product;

            return view('admin.destinations.edit', [
                'product' => $product,
                'customer' => null,
                'destination' => $destination,
                'formAction' => route('admin.destinations.update', [$destination]),
                'formMethod' => 'PUT',
                'backUrl' => route('admin.destinations.index', $product),
            ]);
        }

        return redirect()
            ->route('admin.customers.destinations.edit', [$destination->organization_id, $destination])
            ->with('info', 'Versandziel auf Organisationsebene – Du wurdest zur Bearbeiten-Seite weitergeleitet.');
    }

    public function edit(Product $product, DeliveryDestination $destination): View|RedirectResponse
    {
        if ($destination->product_id != $product->id) {
            if ($destination->product_id) {
                return redirect()
                    ->route('admin.destinations.edit', [$destination])
                    ->with('error', 'Das Versandziel gehört zu einer anderen Rechnungseinheit. Du wurdest zur passenden Bearbeiten-Seite weitergeleitet.');
            }

            return redirect()
                ->route('admin.customers.destinations.edit', [$destination->organization_id, $destination])
                ->with('error', 'Das Versandziel gehört zur Organisationsebene. Du wurdest zur passenden Bearbeiten-Seite weitergeleitet.');
        }
        $destination->load('product.organization');

        return view('admin.destinations.edit', [
            'product' => $product,
            'customer' => null,
            'destination' => $destination,
            'formAction' => route('admin.destinations.update', [$destination]),
            'formMethod' => 'PUT',
            'backUrl' => route('admin.destinations.index', $product),
        ]);
    }

    public function update(Request $request, DeliveryDestination $destination): RedirectResponse
    {
        if ($destination->product_id) {
            $product = $destination->product;
        } else {
            return redirect()
                ->route('admin.customers.destinations.edit', [$destination->organization_id, $destination])
                ->with('error', 'Versandziel auf Organisationsebene – bitte dort bearbeiten.');
        }

        $validated = $this->validateDestination($request);
        $destination->fill($validated);
        $destination->save();
        $this->storeCredentialsFromRequest($destination, $request, $validated['type']);
        $this->storeWdrSubfolderConfig($destination, $request, $validated['type']);

        return redirect()->route('admin.destinations.index', $product)->with('status', 'Versandziel wurde aktualisiert.');
    }

    public function destroy(DeliveryDestination $destination): RedirectResponse
    {
        if ($destination->product_id) {
            $product = $destination->product;
            $destination->delete();

            return redirect()->route('admin.destinations.index', $product)->with('status', 'Versandziel wurde gelöscht.');
        }

        $destination->delete();

        return redirect()->route('admin.customers.destinations.index', $destination->organization_id)
            ->with('status', 'Versandziel wurde gelöscht.');
    }

    // ---------- Customer (Organization) scoped destinations ----------

    public function indexForCustomer(Organization $customer): View
    {
        $destinations = $customer->deliveryDestinations()->with('organization')->orderBy('label')->get();

        return view('admin.destinations.index', [
            'product' => null,
            'customer' => $customer,
            'destinations' => $destinations,
            'backRoute' => 'admin.customers.show',
            'backParams' => [$customer],
            'itemRouteParams' => [$customer],
            'createRoute' => 'admin.customers.destinations.create',
            'createParams' => [$customer],
            'editRoute' => 'admin.customers.destinations.edit',
            'destroyRoute' => 'admin.customers.destinations.destroy',
            'indexLabel' => $customer->name.' (Versandziele Organisation)',
        ]);
    }

    public function createForCustomer(Organization $customer): View
    {
        return view('admin.destinations.create', [
            'product' => null,
            'customer' => $customer,
            'destination' => null,
            'formAction' => route('admin.customers.destinations.store', $customer),
            'formMethod' => 'POST',
            'backUrl' => route('admin.customers.destinations.index', $customer),
        ]);
    }

    public function storeForCustomer(Request $request, Organization $customer): RedirectResponse
    {
        $validated = $this->validateDestination($request);
        $validated['organization_id'] = $customer->id;
        $validated['product_id'] = null;

        $dest = DeliveryDestination::create($validated);
        $this->storeCredentialsFromRequest($dest, $request, $validated['type']);
        $this->storeWdrSubfolderConfig($dest, $request, $validated['type']);

        return redirect()->route('admin.customers.destinations.index', $customer)->with('status', 'Versandziel wurde angelegt.');
    }

    public function editForCustomer(Organization $customer, DeliveryDestination $destination): View
    {
        if ($destination->organization_id != $customer->id) {
            abort(404);
        }
        $destination->load('organization');

        return view('admin.destinations.edit', [
            'product' => null,
            'customer' => $customer,
            'destination' => $destination,
            'formAction' => route('admin.customers.destinations.update', [$customer, $destination]),
            'formMethod' => 'PUT',
            'backUrl' => route('admin.customers.destinations.index', $customer),
        ]);
    }

    public function updateForCustomer(Request $request, Organization $customer, DeliveryDestination $destination): RedirectResponse
    {
        if ($destination->organization_id != $customer->id) {
            abort(404);
        }
        $validated = $this->validateDestination($request);
        $destination->fill($validated);
        $destination->save();
        $this->storeCredentialsFromRequest($destination, $request, $validated['type']);
        $this->storeWdrSubfolderConfig($destination, $request, $validated['type']);

        return redirect()->route('admin.customers.destinations.index', $customer)->with('status', 'Versandziel wurde aktualisiert.');
    }

    public function destroyForCustomer(Organization $customer, DeliveryDestination $destination): RedirectResponse
    {
        if ($destination->organization_id != $customer->id) {
            abort(404);
        }
        $destination->delete();

        return redirect()->route('admin.customers.destinations.index', $customer)->with('status', 'Versandziel wurde gelöscht.');
    }

    // ---------- Test & Upload (global by destination id) ----------

    public function test(DeliveryDestination $destination): RedirectResponse
    {
        $tester = app(DeliveryDestinationConnectionTester::class);
        $result = $tester->test($destination);

        if ($result['success']) {
            return redirect()->back()->with('status', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function upload(Request $request, DeliveryDestination $destination): RedirectResponse
    {
        $mediaIdsInput = $request->input('media_ids');
        if (is_array($mediaIdsInput)) {
            $mediaIds = array_values(array_unique(array_map('intval', array_filter($mediaIdsInput))));
        } else {
            $mediaIds = array_values(array_unique(array_map('intval', array_filter(preg_split('/[\s,]+/', (string) $mediaIdsInput)))));
        }
        $request->merge(['media_ids' => $mediaIds]);
        $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'exists:news_item_media,id'],
        ]);

        $mediaIds = array_values(array_unique($mediaIds));
        if (empty($mediaIds)) {
            return redirect()->back()->with('error', 'Keine gültigen Medien ausgewählt.');
        }

        $run = DeliveryRun::create([
            'delivery_destination_id' => $destination->id,
            'status' => 'queued',
        ]);

        foreach ($mediaIds as $mediaId) {
            $media = \App\Models\NewsItemMedia::find($mediaId);
            $run->items()->create([
                'news_item_media_id' => $mediaId,
                'filename' => $media ? basename($media->path) : 'unknown',
                'status' => 'pending',
            ]);
        }

        UploadMediaToDestinationJob::dispatch($destination->id, $mediaIds, (int) $run->id);

        return redirect()->back()->with('status', 'Upload wurde in die Warteschlange gestellt (Run #'.$run->id.').');
    }

    private function validateDestination(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:email,ftp,ftps,sftp'],
            'label' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'remote_path' => ['nullable', 'string', 'max:512'],
            'passive' => ['boolean'],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $validated['active'] = $request->boolean('active', true);
        $validated['passive'] = $request->boolean('passive', true);
        $validated['timeout'] = isset($validated['timeout']) ? (int) $validated['timeout'] : 20;

        if ($request->user()?->can(AdminPermissions::CUSTOMERS_BILLING_SENSITIVE)) {
            $validated['external_reference_label'] = $request->input('external_reference_label') ? trim($request->input('external_reference_label')) : null;
            $validated['external_author_id'] = $request->input('external_author_id') ? trim($request->input('external_author_id')) : null;
            $validated['external_supplier_id'] = $request->input('external_supplier_id') ? trim($request->input('external_supplier_id')) : null;
            $validated['external_vendor_code'] = $request->input('external_vendor_code') ? trim($request->input('external_vendor_code')) : null;
            $validated['include_in_email'] = $request->boolean('include_in_email', true);
            $validated['include_in_filename'] = $request->boolean('include_in_filename', false);
            $validated['generate_sidecar'] = $request->boolean('generate_sidecar', false);
        }

        if (in_array($validated['type'], ['ftp', 'ftps', 'sftp'], true)) {
            $request->validate([
                'host' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255'],
            ]);
            $validated['host'] = $request->input('host');
            $validated['username'] = $request->input('username');
            $validated['port'] = $request->filled('port') ? (int) $request->input('port') : null;
            $validated['remote_path'] = $request->input('remote_path') ?: null;
        }

        if ($validated['type'] === 'email') {
            $to = $request->input('config_to');
            $to = is_array($to) ? $to : array_filter(array_map('trim', explode("\n", (string) $to)));
            $cc = $request->input('config_cc');
            $cc = is_array($cc) ? $cc : array_filter(array_map('trim', explode("\n", (string) $cc)));
            $bcc = $request->input('config_bcc');
            $bcc = is_array($bcc) ? $bcc : array_filter(array_map('trim', explode("\n", (string) $bcc)));
            $validated['config_json'] = ['to' => array_values($to), 'cc' => array_values($cc), 'bcc' => array_values($bcc)];
        }

        return $validated;
    }

    private function storeCredentialsFromRequest(DeliveryDestination $dest, Request $request, string $type): void
    {
        if (! in_array($type, ['ftp', 'ftps', 'sftp'], true)) {
            return;
        }
        if ($request->filled('password')) {
            $dest->setEncryptedPassword($request->input('password'));
        }
        if ($request->filled('private_key')) {
            $dest->setPrivateKey($request->input('private_key'));
        } else {
            $dest->private_key_encrypted = null;
            $dest->save();
        }
        if ($request->filled('private_key_passphrase')) {
            $dest->setPrivateKeyPassphrase($request->input('private_key_passphrase'));
        } else {
            $dest->private_key_passphrase_encrypted = null;
            $dest->save();
        }
    }

    private function storeWdrSubfolderConfig(DeliveryDestination $dest, Request $request, string $type): void
    {
        if (! in_array($type, ['ftp', 'ftps', 'sftp'], true)) {
            return;
        }
        $config = $dest->config_json ?? [];
        $config['wdr_subfolder_per_item'] = $request->boolean('wdr_subfolder_per_item');
        $config['ekn_live_folder_format'] = $request->boolean('ekn_live_folder_format');
        $dest->config_json = $config;
        $dest->save();
    }
}

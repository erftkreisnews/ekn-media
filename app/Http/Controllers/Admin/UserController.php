<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserWelcomeMail;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();

        return view('admin.backoffice.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();
        $deliveryOrganizations = Organization::query()
            ->whereHas('deliveryDestinations', function ($query): void {
                $query->where('active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.backoffice.users.create', [
            'roles' => $roles,
            'deliveryOrganizations' => $deliveryOrganizations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $canPersistDeliveryRestriction = Schema::hasColumn('users', 'allowed_delivery_organization_ids');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', 'max:255'],
        ];
        if ($canPersistDeliveryRestriction) {
            $rules['allowed_delivery_organization_ids'] = ['array'];
            $rules['allowed_delivery_organization_ids.*'] = ['integer', 'exists:organizations,id'];
        }

        $validated = $request->validate($rules);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];
        if ($canPersistDeliveryRestriction) {
            $data['allowed_delivery_organization_ids'] = $validated['allowed_delivery_organization_ids'] ?? [];
        }

        $user = User::create($data);

        $user->syncRoles($validated['roles'] ?? []);
        $this->sendWelcomeMail($user);

        return redirect()
            ->route('admin.backoffice.users.index')
            ->with('status', 'Benutzer wurde angelegt. Willkommen-Mail mit Passwort-Reset-Hinweis wurde versendet.');
    }

    public function resendWelcomeMail(User $user): RedirectResponse
    {
        $this->sendWelcomeMail($user);

        return redirect()
            ->route('admin.backoffice.users.index')
            ->with('status', 'Willkommen-Mail wurde erneut versendet.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();
        $deliveryOrganizations = Organization::query()
            ->whereHas('deliveryDestinations', function ($query): void {
                $query->where('active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.backoffice.users.edit', [
            'user' => $user,
            'roles' => $roles,
            'deliveryOrganizations' => $deliveryOrganizations,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $canPersistDeliveryRestriction = Schema::hasColumn('users', 'allowed_delivery_organization_ids');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'roles' => ['array'],
            'roles.*' => ['string', 'max:255'],
        ];
        if ($canPersistDeliveryRestriction) {
            $rules['allowed_delivery_organization_ids'] = ['array'];
            $rules['allowed_delivery_organization_ids.*'] = ['integer', 'exists:organizations,id'];
        }

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        }

        $validated = $request->validate($rules);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];
        if ($canPersistDeliveryRestriction) {
            $data['allowed_delivery_organization_ids'] = $validated['allowed_delivery_organization_ids'] ?? [];
        }
        if (! empty($validated['password'] ?? null)) {
            $data['password'] = $validated['password'];
        }
        $user->update($data);

        $roles = $validated['roles'] ?? [];
        $user->syncRoles($roles);

        return redirect()
            ->route('admin.backoffice.users.index')
            ->with('status', 'Benutzer wurde aktualisiert.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return redirect()
                ->route('admin.backoffice.users.index')
                ->with('status', 'Der aktuell angemeldete Benutzer kann nicht gelöscht werden.');
        }

        $user->delete();

        return redirect()
            ->route('admin.backoffice.users.index')
            ->with('status', 'Benutzer wurde gelöscht.');
    }

    private function sendWelcomeMail(User $user): void
    {
        $resetToken = PasswordBroker::broker()->createToken($user);
        $resetUrl = route('password.reset', [
            'token' => $resetToken,
            'email' => $user->email,
        ]);

        Mail::to($user->email)->send(new UserWelcomeMail($user, $resetUrl));
    }
}

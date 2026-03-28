<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.backoffice.users.edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['string', 'max:255'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

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
}

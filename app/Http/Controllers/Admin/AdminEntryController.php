<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminEntryController extends Controller
{
    public function show(Request $request, DashboardController $dashboard): View|RedirectResponse
    {
        if (! auth()->check()) {
            return view('auth.admin-login');
        }

        if (! auth()->user()->can(AdminPermissions::ACCESS)) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Sie haben keinen Zugriff auf den Admin-Bereich.');
        }

        return $dashboard->index($request);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BackofficeController extends Controller
{
    /**
     * Einstiegsseite für den Backoffice-Bereich.
     */
    public function index(): View
    {
        return view('admin.backoffice.index');
    }
}

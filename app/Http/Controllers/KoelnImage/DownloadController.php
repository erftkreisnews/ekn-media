<?php

namespace App\Http\Controllers\KoelnImage;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DownloadController extends Controller
{
    public function index(): View
    {
        return view('koelnimage.downloads.index');
    }
}

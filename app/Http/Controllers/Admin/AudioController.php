<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AudioController extends Controller
{
    public function index()
    {
        return view('admin.audio.index');
    }
}

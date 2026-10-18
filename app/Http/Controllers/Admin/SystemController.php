<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealth;

class SystemController extends Controller
{
    public function index(SystemHealth $health)
    {
        return view('admin.system', ['data' => $health->collect()]);
    }

    public function data(SystemHealth $health)
    {
        return response()->json($health->collect());
    }
}

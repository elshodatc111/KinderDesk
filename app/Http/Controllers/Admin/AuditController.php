<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $items = AuditLog::with('user')->when($request->query('q'), fn ($q, $s) => $q->where('action', 'like', "%$s%"))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.audit', compact('items'));
    }
}

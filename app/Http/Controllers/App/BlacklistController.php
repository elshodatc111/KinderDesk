<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlacklistEntry;
use App\Services\Guardians\BlacklistService;
use Illuminate\Http\Request;

class BlacklistController extends Controller
{
    public function index()
    {
        return view('app.blacklist.index', ['entries' => BlacklistEntry::with('author')->latest('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:family,staff', 'name' => 'required|string|max:150', 'phone' => 'required|string', 'phone2' => 'nullable|string',
            'certificate_no' => 'nullable|string|max:30', 'reason' => 'required|string|min:3|max:300',
        ]);
        $p1 = normalize_phone($data['phone']);
        $p2 = filled($data['phone2'] ?? null) ? normalize_phone($data['phone2']) : null;
        if (! $p1) {
            return back()->withInput()->with('error', "Telefon raqam noto'g'ri");
        }
        $e = BlacklistEntry::create(BlacklistService::build($data['type'], $data['name'], [$p1, $p2], $data['certificate_no'] ?? null, $data['reason'], $request->user()->id) + ['tenant_id' => tenant()->id]);
        AuditLog::record('blacklist.added', $e, ['type' => $data['type'], 'reason' => $data['reason']]);

        return back()->with('success', "Qora ro'yxatga qo'shildi");
    }

    public function destroy(BlacklistEntry $entry)
    {
        AuditLog::record('blacklist.removed', $entry, ['type' => $entry->type]);
        $entry->delete();

        return back()->with('success', "Qora ro'yxatdan olib tashlandi");
    }
}

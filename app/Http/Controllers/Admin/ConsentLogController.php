<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Rozilik jurnali: kim, qachon, qaysi versiyaga, qaysi IP/qurilmadan rozilik bergan. */
class ConsentLogController extends Controller
{
    private function query(Request $request)
    {
        return DB::table('consents')
            ->join('users', 'users.id', '=', 'consents.user_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'consents.tenant_id')
            ->select('consents.*', 'users.name as user_name', 'users.phone as user_phone', 'tenants.name as tenant_name')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('users.name', 'like', "%$s%")->orWhere('users.phone', 'like', "%$s%")->orWhere('tenants.name', 'like', "%$s%")))
            ->when($request->query('version'), fn ($q, $v) => $q->where('consents.version', (int) $v))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('consents.accepted_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('consents.accepted_at', '<=', $d))
            ->orderByDesc('consents.id');
    }

    public function index(Request $request, ConsentService $consent)
    {
        return view('admin.consents', [
            'items' => $this->query($request)->paginate(40)->withQueryString(),
            'version' => $consent->version(),
            'pending' => \App\Models\User::where('status', 'active')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('consents')->whereColumn('consents.user_id', 'users.id')->where('consents.version', '>=', $consent->version()))->count(),
        ]);
    }

    public function csv(Request $request)
    {
        $rows = $this->query($request)->limit(50000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Vaqt', 'Foydalanuvchi', 'Telefon', "Bog'cha", 'Versiya', 'IP', 'Qurilma', 'Hujjat xeshi']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->accepted_at, $r->user_name, $r->user_phone, $r->tenant_name, $r->version, $r->ip, $r->user_agent, $r->docs_hash]);
            }
            fclose($out);
        }, 'rozilik-jurnali-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

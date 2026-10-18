<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Topup;
use App\Models\TrialRegistry;
use App\Models\WalletTransaction;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', ['stats' => $this->collect()]);
    }

    public function stats()
    {
        return response()->json($this->collect());
    }

    private function collect(): array
    {
        $tenants = Tenant::with('edition')->get();
        $states = ['trial' => 0, 'active' => 0, 'lifetime' => 0, 'read_only' => 0, 'suspended' => 0];
        $dueDelete = [];
        foreach ($tenants as $t) {
            $s = $t->accessState();
            $states[$s]++;
            if ($s === 'read_only' && $t->daysSinceExpiry() >= config('kinderdesk.delete_after_days')) {
                $dueDelete[] = ['id' => $t->id, 'name' => $t->name, 'days' => $t->daysSinceExpiry()];
            }
        }
        $monthStart = now()->startOfMonth();

        return [
            'tenants' => $tenants->count(),
            'states' => $states,
            'by_edition' => Edition::withCount('tenants')->orderBy('sort')->get()->map(fn ($e) => ['name' => $e->name, 'count' => $e->tenants_count])->all(),
            'pending_topups' => Topup::where('status', 'pending')->count(),
            'pending_amount' => (int) Topup::where('status', 'pending')->sum('amount'),
            'revenue_month' => (int) Subscription::where('source', '!=', 'admin_grant')->where('created_at', '>=', $monthStart)->sum('price'),
            'topups_month' => (int) Topup::where('status', 'approved')->where('reviewed_at', '>=', $monthStart)->sum('amount'),
            'new_week' => Tenant::where('created_at', '>=', now()->subDays(7))->count(),
            'expiring' => Tenant::whereBetween('access_expires_at', [now(), now()->addDays(config('kinderdesk.reminder_days'))])->count(),
            'due_delete' => $dueDelete,
            'trial_registry' => TrialRegistry::count(),
            'recent' => Tenant::latest()->limit(6)->get(['id', 'name', 'created_at'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'at' => $t->created_at->diffForHumans()])->all(),
        ];
    }
}

<?php

namespace App\Services\Stats;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Lead;
use App\Models\Payslip;
use App\Models\TelegramBot;
use App\Models\TgContact;
use App\Models\User;
use App\Services\Finance\FinanceService;

/** Rolga mos dashboard bloklari: faqat foydalanuvchi ko'rishga haqli narsalar. Tarbiyachi faqat o'z guruhlarini ko'radi. */
class DashboardWidgets
{
    public function build(User $user): array
    {
        $t = tenant();
        $out = ['alerts' => [], 'groups' => [], 'cash' => null, 'debtors' => [], 'followups' => 0, 'tg' => null, 'staff_absent' => 0];

        // ----- Bugungi davomad (guruhlar kesimida) -----
        if ($t->hasFeature('attendance') && $user->hasPermission('attendance.kids')) {
            $groups = Group::active()->main()->orderBy('name')->get();
            $emp = Employee::where('user_id', $user->id)->first();
            if ($emp && ! $user->is_owner && ! $user->hasPermission('groups.manage')) {
                $mine = GroupEmployee::where('employee_id', $emp->id)->whereNull('ended_on')->pluck('group_id');
                if ($mine->isNotEmpty()) {
                    $groups = $groups->whereIn('id', $mine)->values();
                }
            }
            $marks = KidAttendance::whereDate('date', today())->whereIn('group_id', $groups->pluck('id'))->selectRaw('group_id, status, count(*) c')->groupBy('group_id', 'status')->get()->groupBy('group_id');
            $unmarked = 0;
            foreach ($groups as $g) {
                $total = GroupKid::where('group_id', $g->id)->whereNull('ended_on')->count();
                $by = ($marks[$g->id] ?? collect())->pluck('c', 'status');
                $marked = (int) $by->sum();
                $present = (int) ($by['present'] ?? 0);
                $out['groups'][] = ['id' => $g->id, 'name' => $g->name, 'total' => $total, 'marked' => $marked, 'present' => $present, 'done' => $total > 0 && $marked >= $total];
                if ($total > 0 && $marked === 0) {
                    $unmarked++;
                }
            }
            if ($unmarked && now('Asia/Tashkent')->hour >= 10) {
                $out['alerts'][] = ['level' => 'warn', 'text' => __('Davomad belgilanmagan guruhlar: :n', ['n' => $unmarked]), 'url' => route('app.attendance.index')];
            }
        }

        // ----- Moliya -----
        if ($t->hasFeature('finance') && $user->hasPermission('finance.view')) {
            $out['cash'] = app(FinanceService::class)->balances('main');
            $out['debtors'] = Kid::active()->where('balance', '<', 0)->orderBy('balance')->limit(5)->get(['id', 'full_name', 'balance'])->map(fn ($k) => ['id' => $k->id, 'name' => $k->full_name, 'debt' => abs((int) $k->balance)])->all();
        }
        if ($t->hasFeature('finance') && $user->hasPermission('finance.confirm')) {
            $n = FinanceTransaction::where('status', 'pending')->count();
            if ($n) {
                $out['alerts'][] = ['level' => 'warn', 'text' => __("Tasdiq kutayotgan to'lovlar: :n", ['n' => $n]), 'url' => route('app.finance.index')];
            }
        }

        // ----- Ish haqi -----
        if ($t->hasFeature('payroll') && $user->hasPermission('payroll.approve')) {
            $n = Payslip::where('period', today()->startOfMonth()->toDateString())->where('status', 'draft')->count();
            if ($n) {
                $out['alerts'][] = ['level' => 'info', 'text' => __('Ish haqi qoralamasi tasdiqlanmagan: :n', ['n' => $n]), 'url' => route('app.payroll.index')];
            }
        }

        // ----- Xodimlar davomadi -----
        if ($t->hasFeature('staff_attendance') && $user->hasPermission('attendance.staff')) {
            $out['staff_absent'] = EmployeeAttendance::whereDate('date', today())->where('status', 'absent')->count();
            if ($out['staff_absent']) {
                $out['alerts'][] = ['level' => 'warn', 'text' => __('Bugun kelmagan xodimlar: :n', ['n' => $out['staff_absent']]), 'url' => route('app.staff-attendance.index')];
            }
        }

        // ----- Arizalar: bugun qo'ng'iroq qilinadiganlar -----
        if ($t->hasFeature('leads') && $user->hasPermission('leads.view')) {
            $out['followups'] = Lead::where('result', 'open')->whereDate('next_call_on', '<=', today())->count();
            if ($out['followups']) {
                $out['alerts'][] = ['level' => 'info', 'text' => __("Bugun qo'ng'iroq qilinadigan arizalar: :n", ['n' => $out['followups']]), 'url' => route('app.leads.index')];
            }
        }

        // ----- Obuna -----
        if ($user->hasPermission('subscription.manage') && $t->access_expires_at) {
            $days = $t->daysLeft();
            if ($days <= 7 && $days >= 0) {
                $out['alerts'][] = ['level' => 'danger', 'text' => __('Obuna tugashiga :n kun qoldi', ['n' => $days]), 'url' => route('app.subscription.index')];
            }
        }

        // ----- Telegram -----
        if ($t->hasFeature('telegram') && $user->hasPermission('tg.contacts')) {
            $bot = TelegramBot::forTenant($t->id);
            if ($bot && $bot->usable()) {
                $kids = Kid::active()->count();
                $connected = TgContact::where('bot_id', $bot->id)->where('type', 'guardian')->where('consented', true)->where('blocked', false)->distinct('kid_id')->count('kid_id');
                $out['tg'] = ['connected' => $connected, 'kids' => $kids, 'percent' => $kids ? (int) round($connected / $kids * 100) : 0];
            } elseif ($user->hasPermission('tg.manage')) {
                $out['alerts'][] = ['level' => 'info', 'text' => __("Telegram bot ulanmagan: ota-onalar davomad va to'lov xabarlarini olmaydi"), 'url' => route('app.telegram.settings')];
            }
            if ($bot && $bot->status === 'error' && $user->hasPermission('tg.manage')) {
                $out['alerts'][] = ['level' => 'danger', 'text' => __('Telegram bot xato bergan: :e', ['e' => $bot->last_error]), 'url' => route('app.telegram.settings')];
            }
        }

        return $out;
    }
}

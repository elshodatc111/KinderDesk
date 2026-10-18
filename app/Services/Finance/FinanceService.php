<?php

namespace App\Services\Finance;

use App\Models\DeskHandover;
use App\Models\FinanceTransaction;
use App\Models\Kid;
use App\Models\KidLedger;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Support\Realtime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pul harakati: bola to'lovlari, xarajat/daromad, o'tkazma, qaytarish.
 * Qoida: qoldiq = tasdiqlangan (confirmed) yozuvlar yig'indisi. Bola hisobi alohida jurnalda (kid_ledger).
 */
class FinanceService
{
    public function __construct(private SmsService $sms)
    {
    }

    /* ---------------- Qoldiqlar ---------------- */

    /**
     * Hamyon qoldiqlari. wallet: 'main' — direktor moliyasi, 'desk' — bog'cha kassasi.
     *
     * @return array<string,int> hisob => qoldiq
     */
    public function balances(string $wallet = 'main'): array
    {
        $rows = FinanceTransaction::where('wallet', $wallet)->where('status', 'confirmed')->selectRaw('account, sum(amount) as s')->groupBy('account')->pluck('s', 'account');

        return ['cash' => (int) ($rows['cash'] ?? 0), 'card' => (int) ($rows['card'] ?? 0), 'bank' => (int) ($rows['bank'] ?? 0)];
    }

    /** Kassadan topshirish uchun mavjud summa (kutilayotgan topshirishlar ayirilgan). */
    public function deskAvailable(string $account): int
    {
        $held = (int) DeskHandover::where('status', 'pending')->where('account', $account)->sum('amount');

        return ($this->balances('desk')[$account] ?? 0) - $held;
    }

    private function ensureFunds(string $account, int $need, string $wallet = 'main'): void
    {
        if (tenant()->setting('finance.allow_negative_cash')) {
            return;
        }
        $have = $this->balances($wallet)[$account] ?? 0;
        if ($have < $need) {
            $label = FinanceTransaction::accountLabels()[$account];
            throw new \DomainException(__($wallet === 'desk' ? "Kassada «:account» hisobida mablag' yetarli emas. Qoldiq: :sum so'm" : "Moliyada «:account» hisobida mablag' yetarli emas. Qoldiq: :sum so'm", ['account' => __($label), 'sum' => money($have)]));
        }
    }

    public function enabledAccounts(): array
    {
        $t = tenant();
        $out = [];
        foreach (['cash' => 'finance.method_cash', 'card' => 'finance.method_card', 'bank' => 'finance.method_bank'] as $acc => $key) {
            if ($t->setting($key, true)) {
                $out[] = $acc;
            }
        }

        return $out ?: ['cash'];
    }

    /* ---------------- Bola hisobi ---------------- */

    public function recalcKid(Kid $kid): int
    {
        $balance = (int) KidLedger::where('kid_id', $kid->id)->where('status', 'posted')->sum('amount');
        Kid::whereKey($kid->id)->update(['balance' => $balance]);
        $kid->balance = $balance;

        return $balance;
    }

    /** Oylik hisob (yechim). Takrorlansa — o'tkazib yuboriladi. */
    public function charge(Kid $kid, Carbon $period, int $amount, string $key, ?int $groupId, string $desc, string $type = 'charge', ?int $clubId = null): bool
    {
        if ($amount === 0) {
            return false;
        }
        $row = KidLedger::where('kid_id', $kid->id)->where('charge_key', $key)->first();
        if ($row) {
            // Bekor qilingan yechim (masalan, "kelmadi" keyin yana "keldi") qayta tiklanadi
            if ($row->status === 'canceled') {
                $row->update(['status' => 'posted', 'amount' => $amount, 'description' => $desc]);
                $this->recalcKid($kid);

                return true;
            }

            return false;
        }
        KidLedger::create([
            'tenant_id' => $kid->tenant_id, 'kid_id' => $kid->id, 'type' => $type, 'amount' => $amount,
            'period' => $period->copy()->startOfMonth(), 'charge_key' => $key, 'group_id' => $groupId, 'club_id' => $clubId,
            'status' => 'posted', 'description' => $desc,
        ]);
        $this->recalcKid($kid);

        return true;
    }

    /** Hisoblangan yechimni bekor qilish (kalit bo'yicha). */
    public function cancelCharge(Kid $kid, string $key): bool
    {
        $n = KidLedger::where('kid_id', $kid->id)->where('charge_key', $key)->where('status', 'posted')->update(['status' => 'canceled']);
        if ($n) {
            $this->recalcKid($kid);
        }

        return $n > 0;
    }

    /**
     * To'lov qabul qilish. Karta/bank to'lovi sozlamaga ko'ra tasdiq kutadi.
     */
    public function receivePayment(Kid $kid, int $amount, string $account, ?string $desc, User $by, ?Carbon $on = null): FinanceTransaction
    {
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        if (! in_array($account, $this->enabledAccounts(), true)) {
            throw new \DomainException("Bu to'lov usuli yoqilmagan");
        }
        $needsConfirm = in_array($account, ['card', 'bank'], true) && tenant()->setting('finance.require_confirmation', true);
        $status = $needsConfirm ? 'pending' : 'confirmed';

        $tx = DB::transaction(function () use ($kid, $amount, $account, $desc, $by, $on, $status) {
            $tx = FinanceTransaction::create([
                'tenant_id' => $kid->tenant_id, 'wallet' => 'desk', 'account' => $account, 'type' => 'kid_payment', 'amount' => $amount,
                'status' => $status, 'category' => "Bola to'lovi", 'description' => $desc, 'kid_id' => $kid->id,
                'occurred_on' => ($on ?? today())->toDateString(), 'created_by' => $by->id,
                'confirmed_by' => $status === 'confirmed' ? $by->id : null, 'confirmed_at' => $status === 'confirmed' ? now() : null,
            ]);
            KidLedger::create([
                'tenant_id' => $kid->tenant_id, 'kid_id' => $kid->id, 'type' => 'payment', 'amount' => $amount,
                'status' => $status === 'confirmed' ? 'posted' : 'pending', 'finance_transaction_id' => $tx->id,
                'description' => $desc ?: "To'lov", 'created_by' => $by->id, 'group_id' => $kid->currentLink?->group_id,
            ]);
            $this->recalcKid($kid);

            return $tx;
        });

        $this->afterChange('payment', ['kid' => $kid->full_name, 'amount' => $amount, 'status' => $status]);
        if ($status === 'confirmed') {
            $this->notifyPayment($kid, $amount);
        }

        return $tx;
    }

    public function confirm(FinanceTransaction $tx, User $by): FinanceTransaction
    {
        return DB::transaction(function () use ($tx, $by) {
            $tx = FinanceTransaction::lockForUpdate()->findOrFail($tx->id);
            if ($tx->status !== 'pending') {
                throw new \DomainException("Bu yozuv allaqachon ko'rib chiqilgan");
            }
            $tx->update(['status' => 'confirmed', 'confirmed_by' => $by->id, 'confirmed_at' => now()]);
            KidLedger::where('finance_transaction_id', $tx->id)->update(['status' => 'posted']);
            if ($tx->kid) {
                $this->recalcKid($tx->kid);
                DB::afterCommit(fn () => $this->notifyPayment($tx->kid, $tx->amount));
            }
            DB::afterCommit(fn () => $this->afterChange('payment', ['status' => 'confirmed']));

            return $tx;
        });
    }

    public function cancelPending(FinanceTransaction $tx, User $by): FinanceTransaction
    {
        return DB::transaction(function () use ($tx, $by) {
            $tx = FinanceTransaction::lockForUpdate()->findOrFail($tx->id);
            if ($tx->status !== 'pending') {
                throw new \DomainException("Faqat kutilayotgan to'lovni bekor qilish mumkin. Tasdiqlangan to'lov uchun qaytarish ishlating.");
            }
            $tx->update(['status' => 'canceled', 'canceled_by' => $by->id, 'canceled_at' => now()]);
            KidLedger::where('finance_transaction_id', $tx->id)->update(['status' => 'canceled']);
            if ($tx->kid) {
                $this->recalcKid($tx->kid);
            }
            DB::afterCommit(fn () => $this->afterChange('payment', ['status' => 'canceled']));

            return $tx;
        });
    }

    /** Bolaga to'lovni qaytarish (ortiqcha to'langan summa yoki ketganda). */
    public function refund(Kid $kid, int $amount, string $account, ?string $desc, User $by): FinanceTransaction
    {
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        $this->recalcKid($kid);
        if ($kid->balance < $amount) {
            throw new \DomainException(__("Bola hisobida qaytariladigan mablag' yetarli emas. Hisob: :sum so'm", ['sum' => money(max(0, $kid->balance))]));
        }
        $this->ensureFunds($account, $amount, 'desk');

        $tx = DB::transaction(function () use ($kid, $amount, $account, $desc, $by) {
            $tx = FinanceTransaction::create([
                'tenant_id' => $kid->tenant_id, 'wallet' => 'desk', 'account' => $account, 'type' => 'kid_refund', 'amount' => -$amount,
                'status' => 'confirmed', 'category' => "To'lov qaytarish", 'description' => $desc, 'kid_id' => $kid->id,
                'occurred_on' => today()->toDateString(), 'created_by' => $by->id, 'confirmed_by' => $by->id, 'confirmed_at' => now(),
            ]);
            KidLedger::create([
                'tenant_id' => $kid->tenant_id, 'kid_id' => $kid->id, 'type' => 'refund', 'amount' => -$amount, 'status' => 'posted',
                'finance_transaction_id' => $tx->id, 'description' => $desc ?: "To'lov qaytarildi", 'created_by' => $by->id,
            ]);
            $this->recalcKid($kid);

            return $tx;
        });
        $this->afterChange('refund', ['kid' => $kid->full_name, 'amount' => $amount]);

        return $tx;
    }

    /** Qo'lda tuzatish (qarzni kechish yoki qarz yozish). Musbat — bola foydasiga. */
    public function adjust(Kid $kid, int $amount, string $desc, User $by): void
    {
        KidLedger::create([
            'tenant_id' => $kid->tenant_id, 'kid_id' => $kid->id, 'type' => $amount >= 0 ? 'discount' : 'adjustment', 'amount' => $amount,
            'status' => 'posted', 'description' => $desc, 'created_by' => $by->id,
        ]);
        $this->recalcKid($kid);
        $this->afterChange('adjust', ['kid' => $kid->full_name]);
    }

    /* ---------------- Xarajat, daromad, o'tkazma ---------------- */

    public function record(string $type, string $account, int $amount, ?string $category, ?string $desc, User $by, ?Carbon $on = null, ?int $employeeId = null, ?int $payslipId = null, string $wallet = 'main'): FinanceTransaction
    {
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        $signed = in_array($type, ['income', 'deposit', 'subsidy'], true) ? $amount : -$amount;
        if ($signed < 0) {
            $this->ensureFunds($account, $amount, $wallet);
        }

        $tx = FinanceTransaction::create([
            'tenant_id' => tenant()->id, 'wallet' => $wallet, 'account' => $account, 'type' => $type, 'amount' => $signed, 'status' => 'confirmed',
            'category' => $category, 'description' => $desc, 'employee_id' => $employeeId, 'payslip_id' => $payslipId,
            'occurred_on' => ($on ?? today())->toDateString(), 'created_by' => $by->id, 'confirmed_by' => $by->id, 'confirmed_at' => now(),
        ]);
        $this->afterChange($type, ['amount' => $amount]);

        return $tx;
    }

    public function transfer(string $from, string $to, int $amount, ?string $desc, User $by): void
    {
        if ($from === $to) {
            throw new \DomainException("Bir xil hisobga o'tkazib bo'lmaydi");
        }
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        $this->ensureFunds($from, $amount);
        $group = (string) Str::uuid();
        $labels = FinanceTransaction::accountLabels();
        DB::transaction(function () use ($from, $to, $amount, $desc, $by, $group, $labels) {
            foreach ([[$from, -$amount, 'dan'], [$to, $amount, 'ga']] as [$acc, $amt, $dir]) {
                FinanceTransaction::create([
                    'tenant_id' => tenant()->id, 'wallet' => 'main', 'account' => $acc, 'type' => 'transfer', 'amount' => $amt, 'status' => 'confirmed',
                    'category' => "O'tkazma", 'description' => trim(($desc ?: '').' ('.$labels[$from].' → '.$labels[$to].')'),
                    'transfer_group' => $group, 'occurred_on' => today()->toDateString(), 'created_by' => $by->id,
                    'confirmed_by' => $by->id, 'confirmed_at' => now(),
                ]);
            }
        });
        $this->afterChange('transfer', ['amount' => $amount]);
    }

    /* ---------------- Moliya → Kassa (naqd pul berish) ---------------- */

    /** Direktor kassaga NAQD pul beradi (kassa xarajatlari uchun). Faqat naqd hisobdan. */
    public function fundDesk(int $amount, ?string $note, User $by): void
    {
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        $this->ensureFunds('cash', $amount);
        $group = (string) Str::uuid();
        DB::transaction(function () use ($amount, $note, $by, $group) {
            foreach ([['main', -$amount], ['desk', $amount]] as [$wallet, $amt]) {
                FinanceTransaction::create([
                    'tenant_id' => tenant()->id, 'wallet' => $wallet, 'account' => 'cash', 'type' => 'desk_fund', 'amount' => $amt, 'status' => 'confirmed',
                    'category' => 'Moliya → Kassa', 'description' => 'Kassaga naqd pul berildi'.($note ? ': '.$note : ''), 'transfer_group' => $group,
                    'occurred_on' => today()->toDateString(), 'created_by' => $by->id, 'confirmed_by' => $by->id, 'confirmed_at' => now(),
                ]);
            }
        });
        $this->afterChange('desk_fund', ['amount' => $amount]);
    }

    /* ---------------- Subsidiya ---------------- */

    /** Subsidiya olingan: summa direktor moliyasiga kirim bo'ladi. */
    public function receiveSubsidy(\App\Models\Subsidy $s, int $amount, string $account, ?\Illuminate\Support\Carbon $on, User $by): \App\Models\Subsidy
    {
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        if ($s->status !== 'expected') {
            throw new \DomainException("Bu subsidiya allaqachon yopilgan");
        }
        if (! in_array($account, ['cash', 'card', 'bank'], true)) {
            throw new \DomainException("Hisob noto'g'ri");
        }

        return DB::transaction(function () use ($s, $amount, $account, $on, $by) {
            $this->record('subsidy', $account, $amount, 'Subsidiya', $s->title, $by, $on);
            $s->update(['received' => $amount, 'account' => $account, 'received_on' => ($on ?? today())->toDateString(), 'status' => 'received']);

            return $s;
        });
    }

    /* ---------------- Kassa → Moliya (topshirish) ---------------- */

    /** Kassir direktorga pul topshirmoqchi. Direktor tasdiqlamaguncha pul kassada hisoblanadi. */
    public function requestHandover(string $account, int $amount, ?string $note, User $by): DeskHandover
    {
        if ($amount <= 0) {
            throw new \DomainException("Summa noldan katta bo'lishi kerak");
        }
        if (! in_array($account, ['cash', 'card', 'bank'], true)) {
            throw new \DomainException("Hisob noto'g'ri");
        }
        $avail = $this->deskAvailable($account);
        if ($amount > $avail) {
            throw new \DomainException(__("Kassada topshirish uchun mavjud summa: :sum so'm", ['sum' => money(max(0, $avail))]));
        }
        $h = DeskHandover::create(['tenant_id' => tenant()->id, 'account' => $account, 'amount' => $amount, 'note' => $note, 'status' => 'pending', 'created_by' => $by->id]);
        $this->afterChange('handover', ['amount' => $amount, 'status' => 'pending']);
        try {
            app(\App\Services\NotificationService::class)->toOwner(tenant(), __("Kassadan pul topshirildi: :sum so'm", ['sum' => money($amount)]), $by->name.' — '.__('tasdiqlashingizni kutmoqda'), route('app.finance.index'), 'info', 'wallet', 'finance.handover');
        } catch (\Throwable $e) {
            report($e);
        }

        return $h;
    }

    public function confirmHandover(DeskHandover $handover, User $by): DeskHandover
    {
        return DB::transaction(function () use ($handover, $by) {
            $h = DeskHandover::lockForUpdate()->findOrFail($handover->id);
            if ($h->status !== 'pending') {
                throw new \DomainException("Bu topshirish allaqachon ko'rib chiqilgan");
            }
            $group = (string) Str::uuid();
            $desc = 'Kassadan topshirildi'.($h->note ? ': '.$h->note : '');
            foreach ([['desk', -$h->amount], ['main', $h->amount]] as [$wallet, $amt]) {
                FinanceTransaction::create([
                    'tenant_id' => $h->tenant_id, 'wallet' => $wallet, 'account' => $h->account, 'type' => 'handover', 'amount' => $amt,
                    'status' => 'confirmed', 'category' => "Kassa → Moliya", 'description' => $desc, 'transfer_group' => $group,
                    'occurred_on' => today()->toDateString(), 'created_by' => $h->created_by, 'confirmed_by' => $by->id, 'confirmed_at' => now(),
                ]);
            }
            $h->update(['status' => 'confirmed', 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
            DB::afterCommit(fn () => $this->afterChange('handover', ['amount' => $h->amount, 'status' => 'confirmed']));

            return $h;
        });
    }

    public function rejectHandover(DeskHandover $handover, User $by, string $reason): DeskHandover
    {
        $h = DeskHandover::findOrFail($handover->id);
        if ($h->status !== 'pending') {
            throw new \DomainException("Bu topshirish allaqachon ko'rib chiqilgan");
        }
        $h->update(['status' => 'rejected', 'reject_reason' => $reason, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
        $this->afterChange('handover', ['status' => 'rejected']);

        return $h;
    }

    /* ---------------- Yordamchilar ---------------- */

    private function notifyPayment(Kid $kid, int $amount): void
    {
        try {
            $this->sms->sendTenant(tenant(), $kid->parent_phone, 'payment_received', ['bola' => $kid->full_name, 'summa' => money($amount)]);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            app(\App\Services\Telegram\TelegramNotifier::class)->payment($kid, $amount);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function afterChange(string $kind, array $data = []): void
    {
        Realtime::tenant(tenant()->id, 'finance', ['kind' => $kind] + $data + ['message' => null]);
    }
}

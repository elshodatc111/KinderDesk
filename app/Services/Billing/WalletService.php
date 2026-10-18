<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Bog'cha balansi: har bir o'zgarish o'zgarmas jurnal yozuvi bilan birga amalga oshadi. */
class WalletService
{
    public function credit(Tenant $tenant, int $amount, string $type, string $description, ?Model $ref = null, ?int $by = null): WalletTransaction
    {
        return $this->move($tenant, abs($amount), $type, $description, $ref, $by);
    }

    /** @throws InsufficientBalance */
    public function debit(Tenant $tenant, int $amount, string $type, string $description, ?Model $ref = null, ?int $by = null): WalletTransaction
    {
        return $this->move($tenant, -abs($amount), $type, $description, $ref, $by);
    }

    private function move(Tenant $tenant, int $signed, string $type, string $description, ?Model $ref, ?int $by): WalletTransaction
    {
        return DB::transaction(function () use ($tenant, $signed, $type, $description, $ref, $by) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            $after = $locked->balance + $signed;
            if ($after < 0) {
                throw new InsufficientBalance("Balansda mablag' yetarli emas");
            }

            $locked->balance = $after;
            $locked->save();
            $tenant->balance = $after;

            return WalletTransaction::create([
                'tenant_id' => $tenant->id,
                'type' => $type,
                'amount' => $signed,
                'balance_after' => $after,
                'description' => $description,
                'ref_type' => $ref ? class_basename($ref) : null,
                'ref_id' => $ref?->getKey(),
                'created_by' => $by,
            ]);
        });
    }
}

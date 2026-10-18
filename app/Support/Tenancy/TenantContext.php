<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * So'rov davomida "hozir qaysi bog'cha bilan ishlayapmiz" degan ma'lumotni saqlaydi.
 * Subdomen ishlatilmaydi: bog'cha kirgan foydalanuvchining tenant_id'sidan aniqlanadi.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    /** Bog'cha sohasida (/app) bo'lsak true: tenant yo'q bo'lsa hech narsa ko'rsatilmaydi. */
    private bool $strict = false;

    public function set(?Tenant $tenant, bool $strict = false): void
    {
        $this->tenant = $tenant;
        $this->strict = $strict;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->strict = false;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    /** Berilgan bog'cha kontekstida kodni bajaradi (navbat ishlari, buyruqlar uchun). */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $prevTenant = $this->tenant;
        $prevStrict = $this->strict;
        $this->set($tenant, true);
        try {
            return $callback();
        } finally {
            $this->tenant = $prevTenant;
            $this->strict = $prevStrict;
        }
    }
}

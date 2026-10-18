<?php

namespace App\Services\Tenancy;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantDeletion;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bog'chani QAYTARIB BO'LMAS qilib o'chiradi: barcha ma'lumotlar, foydalanuvchilar, fayllar.
 * Faqat nom, sana va statistika (shaxsiy ma'lumotsiz) hamda demo xeshlari qoladi.
 */
class TenantDeleter
{
    public function delete(Tenant $tenant, User $admin, ?string $reason = null): TenantDeletion
    {
        $tenantId = $tenant->id;
        $name = $tenant->name;

        $stats = [
            'kids' => DB::table('kids')->where('tenant_id', $tenantId)->count(),
            'employees' => DB::table('employees')->where('tenant_id', $tenantId)->count(),
            'users' => DB::table('users')->where('tenant_id', $tenantId)->count(),
            'edition' => $tenant->edition?->key,
            'registered_at' => $tenant->created_at?->toDateString(),
        ];

        $userIds = DB::table('users')->where('tenant_id', $tenantId)->pluck('id');
        $phones = DB::table('users')->where('tenant_id', $tenantId)->pluck('phone');
        $receipts = DB::table('topups')->where('tenant_id', $tenantId)->whereNotNull('receipt_path')->pluck('receipt_path');
        $logo = $tenant->logo_path;

        $tombstone = DB::transaction(function () use ($tenantId, $name, $admin, $reason, $stats, $userIds, $phones) {
            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $userIds)->delete();
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('password_reset_tokens')->whereIn('phone', $phones)->delete();
            DB::table('otp_codes')->whereIn('phone', $phones)->delete();
            DB::table('sms_logs')->where('tenant_id', $tenantId)->delete();
            DB::table('audit_logs')->where('tenant_id', $tenantId)->delete();

            // Qolgan barcha jadvallar tenant_id bo'yicha ON DELETE CASCADE orqali tozalanadi
            DB::table('tenants')->where('id', $tenantId)->delete();

            return TenantDeletion::create([
                'old_tenant_id' => $tenantId,
                'name' => $name,
                'reason' => $reason,
                'deleted_by' => $admin->id,
                'stats' => $stats,
                'deleted_at' => now(),
            ]);
        });

        // Fayllar
        foreach ($receipts as $path) {
            Storage::disk('local')->delete($path);
        }
        Storage::disk('local')->deleteDirectory('receipts/'.$tenantId);
        if ($logo) {
            Storage::disk('public')->delete($logo);
        }
        Storage::disk('public')->deleteDirectory('tenants/'.$tenantId);

        AuditLog::record('tenant.deleted', null, ['name' => $name, 'old_id' => $tenantId, 'reason' => $reason], null);
        Realtime::platform('tenant.deleted', ['tenant' => $name]);

        return $tombstone;
    }
}

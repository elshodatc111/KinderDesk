<?php

namespace App\Services\Security;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Site\Legal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Oferta, maxfiylik va shartlarga rozilik: versiya + jurnal. Rozilik bermagan foydalanuvchi tizimga kira olmaydi. */
class ConsentService
{
    public bool $forced = false;

    public function version(): int
    {
        return max(1, (int) PlatformSetting::get('legal.consent_version', '1'));
    }

    /** Super admin matnni o'zgartirib, qayta rozilik talab qilsa — versiya oshadi. */
    public function bump(): void
    {
        PlatformSetting::put('legal.consent_version', (string) ($this->version() + 1));
    }

    /** Hozirgi matnlarning (uz+ru) umumiy xeshi — nima uchun rozilik berilgani dalili. */
    public function docsHash(): string
    {
        $all = '';
        foreach (array_keys(Legal::PAGES) as $p) {
            foreach (['uz', 'ru'] as $l) {
                $all .= Legal::markdown($p, $l)."\n--\n";
            }
        }

        return hash('sha256', $all);
    }

    public function needs(User $user): bool
    {
        if (! config('kinderdesk.enforce_security') && ! $this->forced) {
            return false;
        }

        return ! DB::table('consents')->where('user_id', $user->id)->where('version', '>=', $this->version())->exists();
    }

    public function record(User $user, ?Request $request = null): void
    {
        if (! $this->needs($user)) {
            return;
        }
        DB::table('consents')->insert([
            'user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'version' => $this->version(), 'docs_hash' => $this->docsHash(),
            'ip' => $request?->ip(), 'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 250) : null, 'accepted_at' => now(),
        ]);
    }
}

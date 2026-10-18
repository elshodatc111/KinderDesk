<?php

namespace App\Services\Guardians;

use App\Models\Kid;
use App\Models\KidGuardian;

class GuardianService
{
    /**
     * 1-vasiy kids.parent_* maydonlaridan (ular asosiy to'lovchi sifatida ham o'qiladi), 2-vasiy — faqat `guardians2` imkoniyati bilan.
     *
     * @param  array<string, mixed>|null  $second  [relation, name, phone, phone2, is_payer, sms, pickup]
     */
    public function sync(Kid $kid, array $first, ?array $second): void
    {
        $this->put($kid, 1, $first);
        if ($second && filled($second['phone'] ?? null) && feature('guardians2')) {
            $this->put($kid, 2, $second);
        } else {
            KidGuardian::where('kid_id', $kid->id)->where('position', 2)->delete();
            // 2-vasiy olib tashlansa, unga ulangan Telegram akkauntlar ham uziladi
            \App\Models\TgContact::where('kid_id', $kid->id)->where('type', 'guardian')->where('guardian_position', 2)->delete();
        }
    }

    private function put(Kid $kid, int $pos, array $g): void
    {
        $p1 = $g['phone'] ?? null;
        $p2 = $g['phone2'] ?? null;
        KidGuardian::updateOrCreate(['kid_id' => $kid->id, 'position' => $pos], [
            'tenant_id' => $kid->tenant_id, 'relation' => $g['relation'] ?? 'other', 'name' => $g['name'] ?? null,
            'phone' => $p1, 'phone_hash' => PhoneHash::make($p1), 'phone2' => $p2, 'phone2_hash' => PhoneHash::make($p2),
            'is_payer' => (bool) ($g['is_payer'] ?? $pos === 1), 'sms' => (bool) ($g['sms'] ?? true), 'pickup' => (bool) ($g['pickup'] ?? true),
        ]);
    }
}

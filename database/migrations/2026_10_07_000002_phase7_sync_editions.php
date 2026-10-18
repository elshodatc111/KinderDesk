<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Tarif imkoniyatlarini config bilan moslashtiradi (yangi: guardians2, blacklist, admin_bonus; olib tashlangan: billing_prorate, billing_per_day). */
return new class extends Migration
{
    public function up(): void
    {
        // Super admin qo'lda o'zgartirgan bo'lishi mumkin: faqat yangi kalitlarni qo'shamiz va eskirganlarini olib tashlaymiz
        $new = ['guardians2', 'blacklist', 'admin_bonus'];
        foreach (config('kinderdesk.editions') as $key => $def) {
            $row = DB::table('editions')->where('key', $key)->first();
            if (! $row) {
                continue;
            }
            $cur = json_decode($row->features, true) ?: [];
            $add = array_values(array_intersect($new, $def['features']));
            $cur = array_values(array_unique(array_merge(array_diff($cur, ['billing_prorate', 'billing_per_day']), $add)));
            DB::table('editions')->where('id', $row->id)->update(['features' => json_encode($cur)]);
        }
        DB::table('tenant_feature_overrides')->whereIn('feature_key', ['billing_prorate', 'billing_per_day'])->delete();
    }

    public function down(): void
    {
    }
};

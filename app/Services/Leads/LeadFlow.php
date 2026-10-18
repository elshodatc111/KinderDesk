<?php

namespace App\Services\Leads;

use App\Models\Lead;

class LeadFlow
{
    /** Bolaga/xodimga aylantirilganda arizani «muvaffaqiyatli» bosqichga o'tkazadi. */
    public static function converted(int $leadId, int $targetId): void
    {
        $lead = Lead::find($leadId);
        if (! $lead) {
            return;
        }
        $statuses = array_values((array) tenant()->setting('leads.statuses'));
        $won = count($statuses) >= 3 ? $statuses[count($statuses) - 2] : ($lead->status);
        $lead->update(['status' => $won, 'result' => 'won', 'converted_id' => $targetId, 'stage_changed_at' => now()]);
    }

    /** 24 soatdan ortiq birinchi bosqichda turgan ochiq arizalar. */
    public static function stale(): \Illuminate\Support\Collection
    {
        $first = array_values((array) tenant()->setting('leads.statuses'))[0] ?? null;

        return Lead::where('result', 'open')->where('status', $first)->where('created_at', '<', now()->subDay())->get();
    }
}

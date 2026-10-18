<?php

namespace App\Services\Guardians;

use App\Models\AuditLog;
use App\Models\BlacklistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Qora ro'yxat faqat shu bog'chada; tekshiruv telefon xeshi va guvohnoma xeshi bo'yicha. Natija — ogohlantirish. */
class BlacklistService
{
    public function enabled(): bool
    {
        return feature('blacklist');
    }

    /** @param array<int, ?string> $phones */
    public function check(array $phones, ?string $cert = null, ?string $type = null): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }
        $hashes = array_values(array_filter(array_map(fn ($p) => PhoneHash::make(filled($p) ? (normalize_phone($p) ?: $p) : null), $phones)));
        $certHash = filled($cert) ? PhoneHash::make($cert) : null;
        if (! $hashes && ! $certHash) {
            return collect();
        }

        return BlacklistEntry::when($type, fn ($q) => $q->where('type', $type))->get()->filter(function (BlacklistEntry $e) use ($hashes, $certHash) {
            return array_intersect((array) $e->phone_hashes, $hashes) !== [] || ($certHash && $e->cert_hash === $certHash);
        })->values();
    }

    /**
     * Saqlashdan oldin chaqiriladi. null — davom etish mumkin. Aks holda ogohlantirish bilan orqaga qaytadi.
     * Davom etish: `blacklist.override` ruxsati + sabab; jurnalga yoziladi.
     */
    public function guard(Request $request, array $phones, ?string $cert, string $type, string $subject): ?\Illuminate\Http\RedirectResponse
    {
        $hits = $this->check($phones, $cert, $type);
        if ($hits->isEmpty()) {
            return null;
        }
        $user = $request->user();
        $reason = trim((string) $request->input('override_reason'));
        if ($request->boolean('blacklist_override') && $user->can('blacklist.override') && mb_strlen($reason) >= 5) {
            AuditLog::record('blacklist.override', null, ['subject' => $subject, 'type' => $type, 'entries' => $hits->pluck('id')->all(), 'reason' => $reason]);

            return null;
        }

        return back()->withInput()->with('blacklist', $hits->map(fn ($e) => ['name' => $e->name, 'reason' => $e->reason])->all())
            ->with('error', __("Qora ro'yxatda mos yozuv topildi. Davom etish uchun ruxsat va sabab kerak."));
    }

    public static function build(string $type, string $name, array $phones, ?string $cert, string $reason, ?int $by): array
    {
        $phones = array_values(array_filter($phones));

        return ['type' => $type, 'name' => $name, 'phones' => $phones, 'phone_hashes' => array_values(array_filter(array_map(fn ($p) => PhoneHash::make($p), $phones))),
            'cert_hash' => filled($cert) ? PhoneHash::make($cert) : null, 'reason' => $reason, 'created_by' => $by];
    }
}

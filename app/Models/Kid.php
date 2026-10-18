<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Kid extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'family_id', 'full_name', 'gender', 'birth_date', 'certificate_no', 'parent_name', 'parent_phone',
        'parent_phone2', 'address', 'custom_fee', 'balance', 'status', 'enrolled_on', 'archived_on', 'note',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'enrolled_on' => 'date:Y-m-d',
            'archived_on' => 'date:Y-m-d',
            'balance' => 'integer',
            'custom_fee' => 'integer',
        ];
    }

    public function family(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function health(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(KidHealth::class);
    }

    public function pickups(): HasMany
    {
        return $this->hasMany(KidPickup::class)->orderBy('id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(KidGuardian::class)->orderBy('position');
    }

    public function links(): HasMany
    {
        return $this->hasMany(GroupKid::class)->orderByDesc('started_on');
    }

    /** Asosiy (09:00–17:00) guruhdagi joriy a'zolik. */
    public function currentLink(): HasOne
    {
        return $this->hasOne(GroupKid::class)->whereNull('ended_on')
            ->whereHas('group', fn ($q) => $q->where('type', 'main'))->latest('id');
    }

    /** Qo'shimcha (shanba / kechki) guruhlardagi joriy a'zoliklar. */
    public function extraLinks(): HasMany
    {
        return $this->hasMany(GroupKid::class)->whereNull('ended_on')
            ->whereHas('group', fn ($q) => $q->where('type', '!=', 'main'));
    }

    public function clubLinks(): HasMany
    {
        return $this->hasMany(ClubKid::class)->whereNull('ended_on');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(KidLedger::class)->orderByDesc('id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(KidAttendance::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')->orderByDesc('id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function age(): string
    {
        $diff = $this->birth_date->diff(now());
        if ($diff->y >= 1) {
            return $diff->y.' yosh'.($diff->m ? ' '.$diff->m.' oy' : '');
        }

        return $diff->m.' oy';
    }

    public function isInDebt(): bool
    {
        return $this->balance < 0;
    }

    public function debt(): int
    {
        return $this->balance < 0 ? abs($this->balance) : 0;
    }
}

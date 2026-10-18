<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Employee extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'role_id', 'full_name', 'phone', 'position', 'position_note', 'birth_date', 'passport',
        'address', 'hired_on', 'left_on', 'status', 'salary_type', 'scheme_key', 'base_salary', 'daily_rate', 'kpi_per_kid', 'note', 'tg_token',
    ];

    protected $hidden = ['tg_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'hired_on' => 'date:Y-m-d',
            'left_on' => 'date:Y-m-d',
            'base_salary' => 'integer',
            'daily_rate' => 'integer',
            'kpi_per_kid' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function groupLinks(): HasMany
    {
        return $this->hasMany(GroupEmployee::class)->orderByDesc('started_on');
    }

    public function activeGroupLinks(): HasMany
    {
        return $this->hasMany(GroupEmployee::class)->whereNull('ended_on');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderByDesc('period');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')->orderByDesc('id');
    }

    /** Guruhga biriktiriladigan lavozimlar (tarbiyachi, yordamchi) — faqat bitta guruhda bo'ladi. */
    public const GROUP_ROLES = ['teacher', 'assistant'];

    /** To'garakka o'qituvchi bo'la oladigan lavozimlar. */
    public const CLUB_ROLES = ['teacher', 'assistant', 'pedagog'];

    public function scopeGroupStaff($q)
    {
        return $q->whereHas('role', fn ($r) => $r->whereIn('key', self::GROUP_ROLES));
    }

    public function scopeClubStaff($q)
    {
        return $q->whereHas('role', fn ($r) => $r->whereIn('key', self::CLUB_ROLES));
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Lavozim nomi (direktor yaratgan lavozim). */
    public function positionLabel(): string
    {
        return $this->role?->name ?: ($this->position_note ?: '—');
    }
}

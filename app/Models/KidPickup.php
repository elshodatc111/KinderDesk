<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Bolani olib ketishga ruxsat etilgan shaxs (pasport ma'lumoti saqlanmaydi). */
class KidPickup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'kid_id', 'name', 'relation', 'phone'];

    protected function casts(): array
    {
        return ['name' => 'encrypted', 'phone' => 'encrypted'];
    }
}

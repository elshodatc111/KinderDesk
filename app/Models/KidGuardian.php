<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Vasiy: ism va telefonlar bazada shifrlangan, qidiruv uchun faqat xesh saqlanadi. */
class KidGuardian extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'kid_id', 'position', 'relation', 'name', 'phone', 'phone_hash', 'phone2', 'phone2_hash', 'is_payer', 'sms', 'pickup', 'tg_token'];

    protected function casts(): array
    {
        return ['name' => 'encrypted', 'phone' => 'encrypted', 'phone2' => 'encrypted', 'is_payer' => 'boolean', 'sms' => 'boolean', 'pickup' => 'boolean'];
    }

    public function kid(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    protected $hidden = ['tg_token'];

    public static function relations(): array
    {
        return ['father' => 'Ota', 'mother' => 'Ona', 'other' => 'Boshqa'];
    }
}

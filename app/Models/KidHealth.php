<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bolaning sog'lig'i: allergiya, kasallik, dori, izoh, shifokor. Hammasi shifrlangan; AI ga hech qachon yuborilmaydi. */
class KidHealth extends Model
{
    use BelongsToTenant;

    protected $table = 'kid_health';

    protected $fillable = ['tenant_id', 'kid_id', 'allergies', 'conditions', 'medications', 'notes', 'doctor_name', 'doctor_phone', 'has_alert', 'updated_by'];

    protected function casts(): array
    {
        return [
            'allergies' => 'encrypted', 'conditions' => 'encrypted', 'medications' => 'encrypted', 'notes' => 'encrypted',
            'doctor_name' => 'encrypted', 'doctor_phone' => 'encrypted', 'has_alert' => 'boolean',
        ];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function isEmpty(): bool
    {
        return ! filled($this->allergies) && ! filled($this->conditions) && ! filled($this->medications) && ! filled($this->notes) && ! filled($this->doctor_name) && ! filled($this->doctor_phone);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsEventRule extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'event_key', 'enabled', 'template_id', 'options'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'options' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }
}

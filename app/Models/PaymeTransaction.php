<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymeTransaction extends Model
{
    public const STATE_CREATED = 1;
    public const STATE_COMPLETED = 2;
    public const STATE_CANCELED = -1;
    public const STATE_CANCELED_AFTER_COMPLETE = -2;

    protected $fillable = ['payme_id', 'topup_id', 'time', 'amount', 'state', 'reason', 'create_time', 'perform_time', 'cancel_time'];

    public function topup(): BelongsTo
    {
        return $this->belongsTo(Topup::class);
    }
}

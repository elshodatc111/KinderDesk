<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['tenant_id', 'to', 'event_key', 'template_id', 'message', 'status', 'provider_id', 'error'];
}

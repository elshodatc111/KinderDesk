<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpTelegramLink extends Model
{
    protected $fillable = ['phone', 'chat_id', 'tg_user_id', 'username', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}

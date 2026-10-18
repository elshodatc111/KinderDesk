<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Yuborish navbati (tezlik cheklovi: botga ~25 xabar/s). Tenant doirasi yo'q — worker barcha bog'chalarga xizmat qiladi. */
class TgOutbox extends Model
{
    protected $table = 'tg_outbox';

    protected $fillable = ['tenant_id', 'bot_id', 'post_id', 'contact_id', 'chat_id', 'method', 'payload', 'status', 'attempts', 'available_at', 'message_ids', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'message_ids' => 'array', 'available_at' => 'datetime', 'sent_at' => 'datetime', 'chat_id' => 'integer'];
    }

    public function bot()
    {
        return $this->belongsTo(TelegramBot::class, 'bot_id');
    }
}

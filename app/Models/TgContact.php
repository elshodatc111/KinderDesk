<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ulangan Telegram akkaunt: ota-ona (bola + vasiy o'rni) yoki xodim. Telefon so'ralmaydi; chat_id + username saqlanadi. */
class TgContact extends Model
{
    use BelongsToTenant;

    protected $table = 'telegram_contacts';

    protected $fillable = [
        'tenant_id', 'bot_id', 'type', 'kid_id', 'guardian_position', 'employee_id', 'chat_id', 'tg_user_id', 'username', 'first_name',
        'consented', 'consented_at', 'blocked',
    ];

    protected function casts(): array
    {
        return ['consented' => 'boolean', 'blocked' => 'boolean', 'consented_at' => 'datetime', 'chat_id' => 'integer', 'tg_user_id' => 'integer'];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, 'bot_id');
    }
}

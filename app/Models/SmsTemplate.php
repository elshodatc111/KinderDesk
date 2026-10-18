<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'event_key', 'name', 'body', 'status', 'eskiz_template_id', 'rejection_reason',
        'submitted_at', 'checked_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Qoralama',
            'pending' => 'Tasdiqlanmoqda',
            'approved' => 'Tasdiqlangan',
            'rejected' => 'Rad etilgan',
            default => $this->status,
        };
    }

    /** Shablon matniga o'zgaruvchilarni joylaydi: {bola} -> qiymat. */
    public function render(array $vars): string
    {
        $text = $this->body;
        foreach ($vars as $k => $v) {
            $text = str_replace('{'.$k.'}', (string) $v, $text);
        }

        return $text;
    }

    /** Shablondagi o'zgaruvchilar ro'yxati. */
    public function variables(): array
    {
        preg_match_all('/\{([a-z_]+)\}/u', $this->body, $m);

        return array_values(array_unique($m[1]));
    }
}

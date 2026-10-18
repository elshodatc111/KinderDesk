<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Platforma darajasidagi sozlamalar (karta raqami, aloqa va h.k.). */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'payment.card_number' => '',
        'payment.card_holder' => '',
        'payment.card_bank' => '',
        'payment.min_topup' => '10000',
        'payment.note' => "To'lovdan keyin chek rasmini 'Obuna' bo'limidan yuboring.",
        'support.phone' => '',
        'support.telegram' => '',
        'support.from' => '09:00',
        'support.to' => '18:00',
        'support.days' => '1,2,3,4,5',
        'support.response' => '',
        'social.telegram' => '',
        'social.instagram' => '',
        'social.youtube' => '',
        'ai.enabled' => '1',
        'ai.model_chat' => 'claude-haiku-5-5',
        'ai.model_report' => 'claude-sonnet-5-5',
        'ai.price_in' => '0',
        'ai.price_out' => '0',
        'ai.price_cache' => '0',
        'ai.questions.standard' => '30',
        'ai.questions.premium' => '100',
        'ai.tokens.standard' => '600000',
        'ai.tokens.premium' => '1500000',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::remember('platform_settings', 300, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? static::DEFAULTS[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('platform_settings');
    }
}

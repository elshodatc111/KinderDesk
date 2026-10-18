<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;

/** Telegram Bot API'ga yupqa qatlam. Hech qachon istisno tashlamaydi: {ok, result, error_code, description, retry_after} qaytaradi. Token logga yozilmaydi. */
class TelegramApi
{
    public function __construct(private string $token)
    {
    }

    public function call(string $method, array $params = [], int $timeout = 15): array
    {
        try {
            $res = Http::asJson()->timeout($timeout)->post("https://api.telegram.org/bot{$this->token}/{$method}", $params);
            $j = $res->json();
            if (! is_array($j)) {
                return ['ok' => false, 'error_code' => $res->status(), 'description' => 'Javob noto\'g\'ri'];
            }
            if (! ($j['ok'] ?? false)) {
                $j['retry_after'] = $j['parameters']['retry_after'] ?? null;
            }

            return $j;
        } catch (\Throwable $e) {
            // Xabar matnida token bo'lishi mumkin — tozalaymiz
            return ['ok' => false, 'error_code' => 0, 'description' => str_replace($this->token, '***', $e->getMessage())];
        }
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }

    public function getUpdates(int $offset, int $timeout = 0): array
    {
        return $this->call('getUpdates', ['offset' => $offset, 'timeout' => $timeout, 'limit' => 100, 'allowed_updates' => ['message', 'callback_query']], $timeout + 15);
    }

    public function send(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text] + $extra);
    }
}

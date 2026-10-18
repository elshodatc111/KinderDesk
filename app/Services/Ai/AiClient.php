<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/** Claude API (bitta umumiy kalit, super admin sozlamasida shifrlangan). Har chaqiruv `ai_usage`'ga yoziladi. */
class AiClient
{
    public function __construct(private PiiScanner $pii)
    {
    }

    public function key(): ?string
    {
        $enc = PlatformSetting::get('ai.api_key');
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function configured(): bool
    {
        return filled($this->key()) && PlatformSetting::get('ai.enabled', '1') === '1';
    }

    public function model(string $kind): string
    {
        return $kind === 'chat' ? PlatformSetting::get('ai.model_chat') : PlatformSetting::get('ai.model_report');
    }

    /** Taxminiy narx, mikro-dollar. Narxlar: 1 million token uchun dollar. */
    public function cost(int $in, int $out, int $cache): int
    {
        return (int) round($in * (float) PlatformSetting::get('ai.price_in', '0')
            + $out * (float) PlatformSetting::get('ai.price_out', '0')
            + $cache * (float) PlatformSetting::get('ai.price_cache', '0'));
    }

    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{text:string, input:int, output:int, cache:int}
     *
     * @throws AiException
     */
    public function complete(string $system, array $messages, string $kind, ?Tenant $tenant = null, ?User $user = null, int $maxTokens = 1024, ?string $model = null): array
    {
        $key = $this->key();
        if (! $key || PlatformSetting::get('ai.enabled', '1') !== '1') {
            throw new AiException("AI xizmati ulanmagan", 'off');
        }
        $model = $model ?: $this->model($kind);
        $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $system, 'messages' => $messages];

        try {
            $this->pii->assertClean([$system, $messages]);
        } catch (AiException $e) {
            $this->log($tenant, $user, $kind, $model, 0, 0, 0, 'blocked', $payload);
            throw $e;
        }

        try {
            $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->timeout(90)->acceptJson()->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (\Throwable $e) {
            $this->log($tenant, $user, $kind, $model, 0, 0, 0, 'error', $payload);
            throw new AiException("AI xizmatiga ulanib bo'lmadi", 'unavailable');
        }
        if (! $res->successful()) {
            $this->log($tenant, $user, $kind, $model, 0, 0, 0, 'error', $payload);
            throw new AiException("AI xizmati xato qaytardi (".$res->status().")", 'unavailable');
        }
        $json = $res->json();
        $text = collect($json['content'] ?? [])->where('type', 'text')->pluck('text')->join("\n");
        $in = (int) ($json['usage']['input_tokens'] ?? 0);
        $out = (int) ($json['usage']['output_tokens'] ?? 0);
        $cache = (int) ($json['usage']['cache_read_input_tokens'] ?? 0);
        $this->log($tenant, $user, $kind, $model, $in, $out, $cache, 'ok', $payload);
        PlatformSetting::put('ai.last_ok_at', now()->toIso8601String());

        return ['text' => $text, 'input' => $in, 'output' => $out, 'cache' => $cache];
    }

    /**
     * Jonli (oqimli) javob: Anthropic `stream: true`. Har bir matn bo'lagi $onDelta ga uzatiladi.
     *
     * @param  callable(string): void  $onDelta
     * @throws AiException
     */
    public function stream(string $system, array $messages, string $kind, callable $onDelta, ?Tenant $tenant = null, ?User $user = null, int $maxTokens = 1024, ?string $model = null): array
    {
        $key = $this->key();
        if (! $key || PlatformSetting::get('ai.enabled', '1') !== '1') {
            throw new AiException("AI xizmati ulanmagan", 'off');
        }
        $model = $model ?: $this->model($kind);
        $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $system, 'messages' => $messages, 'stream' => true];

        try {
            $this->pii->assertClean([$system, $messages]);
        } catch (AiException $e) {
            $this->log($tenant, $user, $kind, $model, 0, 0, 0, 'blocked', $payload);
            throw $e;
        }

        $text = '';
        $in = $out = $cache = 0;
        try {
            $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->withOptions(['stream' => true])->timeout(120)->post('https://api.anthropic.com/v1/messages', $payload);
            if (! $res->successful()) {
                $this->log($tenant, $user, $kind, $model, 0, 0, 0, 'error', $payload);
                throw new AiException("AI xizmati xato qaytardi (".$res->status().")", 'unavailable');
            }
            $body = $res->toPsrResponse()->getBody();
            $buf = '';
            while (! $body->eof()) {
                $buf .= $body->read(512);
                while (($pos = strpos($buf, "\n")) !== false) {
                    $line = trim(substr($buf, 0, $pos));
                    $buf = substr($buf, $pos + 1);
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $ev = json_decode(trim(substr($line, 5)), true);
                    if (! is_array($ev)) {
                        continue;
                    }
                    switch ($ev['type'] ?? '') {
                        case 'message_start':
                            $in = (int) ($ev['message']['usage']['input_tokens'] ?? 0);
                            $cache = (int) ($ev['message']['usage']['cache_read_input_tokens'] ?? 0);
                            break;
                        case 'content_block_delta':
                            if (($ev['delta']['type'] ?? '') === 'text_delta' && ($ev['delta']['text'] ?? '') !== '') {
                                $text .= $ev['delta']['text'];
                                $onDelta($ev['delta']['text']);
                            }
                            break;
                        case 'message_delta':
                            $out = (int) ($ev['usage']['output_tokens'] ?? $out);
                            break;
                        case 'error':
                            throw new AiException("AI xizmati xato qaytardi", 'unavailable');
                    }
                }
            }
        } catch (AiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->log($tenant, $user, $kind, $model, $in, $out, $cache, 'error', $payload);
            throw new AiException("AI xizmatiga ulanib bo'lmadi", 'unavailable');
        }
        if (trim($text) === '') {
            $this->log($tenant, $user, $kind, $model, $in, $out, $cache, 'error', $payload);
            throw new AiException("AI xizmati bo'sh javob qaytardi", 'unavailable');
        }
        $this->log($tenant, $user, $kind, $model, $in, $out, $cache, 'ok', $payload);
        PlatformSetting::put('ai.last_ok_at', now()->toIso8601String());

        return ['text' => $text, 'input' => $in, 'output' => $out, 'cache' => $cache];
    }

    private function log(?Tenant $tenant, ?User $user, string $kind, string $model, int $in, int $out, int $cache, string $status, array $payload): void
    {
        AiUsage::create([
            'tenant_id' => $tenant?->id, 'user_id' => $user?->id, 'kind' => $kind, 'model' => $model, 'input_tokens' => $in, 'output_tokens' => $out,
            'cache_tokens' => $cache, 'cost_micro' => $this->cost($in, $out, $cache), 'status' => $status,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
        ]);
    }
}

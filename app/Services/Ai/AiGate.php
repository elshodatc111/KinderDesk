<?php

namespace App\Services\Ai;

use App\Models\AiChat;
use App\Models\AiUsage;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;

/** Ruxsat va limitlar: yoqilganlik, kunlik savol limiti (foydalanuvchi), oylik token limiti (bog'cha). */
class AiGate
{
    public function __construct(private AiClient $client)
    {
    }

    public function tenantReady(Tenant $t, string $feature): ?string
    {
        if (! $this->client->configured()) {
            return "AI xizmati hozircha ulanmagan";
        }
        if ($t->ai_disabled) {
            return "AI bu bog'cha uchun o'chirilgan";
        }
        if (! $t->hasFeature($feature)) {
            return "AI sizning tarifingizda mavjud emas";
        }
        if (! $t->setting('ai.enabled', true)) {
            return "AI direktor tomonidan o'chirilgan";
        }

        return null;
    }

    public function editionKey(Tenant $t): string
    {
        return $t->lifetime ? 'premium' : ($t->edition?->key ?? 'start');
    }

    public function dailyQuestionLimit(Tenant $t): int
    {
        $defaults = ['start' => 0, 'standard' => 30, 'premium' => 100];
        $k = $this->editionKey($t);

        return (int) PlatformSetting::get("ai.questions.$k", (string) ($defaults[$k] ?? 0));
    }

    public function monthlyTokenLimit(Tenant $t): int
    {
        if ($t->ai_token_limit !== null) {
            return (int) $t->ai_token_limit;
        }
        $defaults = ['start' => 0, 'standard' => 600000, 'premium' => 1500000];
        $k = $this->editionKey($t);

        return (int) PlatformSetting::get("ai.tokens.$k", (string) ($defaults[$k] ?? 0));
    }

    public function monthTokens(Tenant $t): int
    {
        return (int) AiUsage::where('tenant_id', $t->id)->where('status', 'ok')->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens),0) as s')->value('s');
    }

    public function questionsToday(User $u): int
    {
        return AiChat::where('user_id', $u->id)->where('created_at', '>=', now()->startOfDay())->count();
    }

    /** Oylik limit: 80 % da bir marta ogohlantiradi, 100 % da to'xtatadi. @throws AiException */
    public function assertTokens(Tenant $t): void
    {
        $limit = $this->monthlyTokenLimit($t);
        if ($limit <= 0) {
            throw new AiException("Bu bog'cha uchun AI limiti belgilanmagan", 'limit');
        }
        $used = $this->monthTokens($t);
        if ($used >= $limit) {
            throw new AiException("Oylik AI limiti tugadi. Keyingi oydan davom etadi", 'limit');
        }
        if ($used >= $limit * 0.8 && $t->ai_warned_month !== now()->format('Y-m')) {
            $t->forceFill(['ai_warned_month' => now()->format('Y-m')])->save();
            app(NotificationService::class)->toOwner($t, __('AI limiti 80 % ishlatildi'), __("Oylik AI limitining 80 % i sarflandi."), route('app.ai.index'), 'warn', 'sparkles', 'ai.report');
        }
    }

    /** @throws AiException */
    public function assertQuestion(User $u, Tenant $t): void
    {
        if ($this->questionsToday($u) >= $this->dailyQuestionLimit($t)) {
            throw new AiException("Bugungi savol limiti tugadi", 'limit');
        }
        $this->assertTokens($t);
    }
}

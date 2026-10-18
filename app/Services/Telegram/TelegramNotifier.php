<?php

namespace App\Services\Telegram;

use App\Models\Kid;
use App\Models\KidGuardian;
use App\Models\TelegramBot;
use App\Models\TgContact;

/** Avtomatik xabarlar: davomad (keldi/kelmadi) va to'lov. */
class TelegramNotifier
{
    public function __construct(private TelegramOutbox $outbox)
    {
    }

    private function bot(int $tenantId, string $event): ?TelegramBot
    {
        $tenant = tenant();
        if (! $tenant || ! $tenant->hasFeature('telegram')) {
            return null;
        }
        $bot = TelegramBot::forTenant($tenantId);

        return $bot && $bot->usable() && $bot->eventEnabled($event) ? $bot : null;
    }

    private function render(TelegramBot $bot, string $event, array $vars): string
    {
        $text = $bot->template($event);
        foreach ($vars as $k => $v) {
            $text = str_replace('{'.$k.'}', (string) $v, $text);
        }

        return $text;
    }

    private function contacts(Kid $kid, ?array $positions = null)
    {
        return TgContact::where('type', 'guardian')->where('kid_id', $kid->id)->where('consented', true)->where('blocked', false)
            ->when($positions, fn ($q) => $q->whereIn('guardian_position', $positions))->get()->unique('chat_id');
    }

    /** status: present => keldi; absent/sick => kelmadi */
    public function attendance(Kid $kid, string $status, ?string $groupName = null): int
    {
        $event = $status === 'present' ? 'attendance_came' : 'attendance_absent';
        $bot = $this->bot($kid->tenant_id, $event);
        if (! $bot) {
            return 0;
        }
        $text = $this->render($bot, $event, [
            'kid' => $kid->full_name, 'group' => $groupName, 'date' => now()->format('d.m.Y'), 'time' => now()->format('H:i'),
            'kindergarten' => tenant()?->name,
        ]);

        return $this->fan($bot, $this->contacts($kid), $text);
    }

    /** To'lov qabul qilindi: faqat to'lovchi vasiyga. */
    public function payment(Kid $kid, int $amount): int
    {
        $bot = $this->bot($kid->tenant_id, 'payment');
        if (! $bot) {
            return 0;
        }
        $payer = KidGuardian::where('kid_id', $kid->id)->where('is_payer', true)->orderBy('position')->value('position')
            ?? KidGuardian::where('kid_id', $kid->id)->orderBy('position')->value('position') ?? 1;
        $text = $this->render($bot, 'payment', [
            'kid' => $kid->full_name, 'amount' => money($amount), 'balance' => money((int) $kid->fresh()->balance),
            'date' => now()->format('d.m.Y'), 'time' => now()->format('H:i'), 'kindergarten' => tenant()?->name,
        ]);

        return $this->fan($bot, $this->contacts($kid, [(int) $payer]), $text);
    }

    private function fan(TelegramBot $bot, $contacts, string $text): int
    {
        foreach ($contacts as $c) {
            $this->outbox->queue($bot, $c->chat_id, 'sendMessage', ['text' => $text, 'protect_content' => $bot->protect_content], null, $c->id);
        }
        if ($contacts->isNotEmpty()) {
            $this->outbox->kick();
        }

        return $contacts->count();
    }
}

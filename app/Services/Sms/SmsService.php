<?php

namespace App\Services\Sms;

use App\Jobs\SendSmsJob;
use App\Models\SmsEventRule;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

class SmsService
{
    public function __construct(private SmsManager $manager)
    {
    }

    /* ---------------- Platforma xabarlari ---------------- */

    /** Platforma xabari: matn tasdiqlangan shablondan yoki standart matndan olinadi. */
    public function sendPlatform(string $phone, string $eventKey, array $vars = []): ?SmsLog
    {
        $phone = normalize_phone($phone);
        if (! $phone) {
            return null;
        }

        $template = SmsTemplate::whereNull('tenant_id')->where('event_key', $eventKey)->where('status', 'approved')->latest('id')->first();
        $body = $template?->body ?? config("kinderdesk.sms_events.$eventKey.body");
        if (! $body) {
            return null;
        }

        $message = $this->render($body, $vars);
        $log = SmsLog::create([
            'tenant_id' => null, 'to' => $phone, 'event_key' => $eventKey,
            'template_id' => $template?->id, 'message' => $message, 'status' => 'queued',
        ]);

        // OTP kabi muhim xabarlar darhol yuboriladi
        SendSmsJob::dispatchSync($log->id, 'platform');

        return $log->fresh();
    }

    /* ---------------- Bog'cha xabarlari ---------------- */

    /**
     * Bog'cha nomidan ota-onaga SMS. Quyidagi shartlar bajarilmasa yuborilmaydi (log'da sababi yoziladi):
     * xususiyat yoqiq, obuna faol, Eskiz akkaunt ulangan, hodisa yoqilgan, shablon tasdiqlangan, tinch soat emas.
     */
    public function sendTenant(Tenant $tenant, ?string $phone, string $eventKey, array $vars = []): ?SmsLog
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant, $phone, $eventKey, $vars) {
            $phone = normalize_phone($phone);
            if (! $phone || ! $tenant->hasFeature('sms') || $tenant->isReadOnly()) {
                return null;
            }

            $rule = SmsEventRule::where('event_key', $eventKey)->first();
            if (! $rule || ! $rule->enabled) {
                return null;
            }

            $block = function (string $reason, ?SmsTemplate $tpl = null) use ($tenant, $phone, $eventKey) {
                return SmsLog::create([
                    'tenant_id' => $tenant->id, 'to' => $phone, 'event_key' => $eventKey, 'template_id' => $tpl?->id,
                    'message' => '', 'status' => 'blocked', 'error' => $reason,
                ]);
            };

            $account = $tenant->smsAccount;
            if (! $account || ! $account->is_active) {
                return $block('Eskiz akkaunt ulanmagan');
            }

            $template = $rule->template;
            if (! $template || ! $template->isApproved()) {
                return $block('Shablon Eskiz tomonidan tasdiqlanmagan', $template);
            }

            if ($this->inQuietHours($rule->options ?? [])) {
                return $block('Tinch soatlar', $template);
            }

            $vars = ['bogcha' => $tenant->name] + $vars;
            $log = SmsLog::create([
                'tenant_id' => $tenant->id, 'to' => $phone, 'event_key' => $eventKey, 'template_id' => $template->id,
                'message' => $this->render($template->body, $vars), 'status' => 'queued',
            ]);
            SendSmsJob::dispatch($log->id, 'tenant');

            return $log;
        });
    }

    /** Test tugmasi: qoidalarni chetlab, shablon matnini namunaviy qiymatlar bilan o'z raqamingizga yuboradi. */
    public function sendTest(Tenant $tenant, SmsTemplate $template, string $phone): SendResult
    {
        $phone = normalize_phone($phone);
        if (! $phone) {
            return SendResult::fail("Telefon raqam noto'g'ri");
        }
        $sender = $this->manager->forTenant($tenant);
        if (! $sender) {
            return SendResult::fail('Avval Eskiz akkauntini ulang');
        }

        $sample = ['bogcha' => $tenant->name, 'bola' => 'Ali', 'summa' => '500 000', 'matn' => 'Sinov xabari', 'sana' => now()->format('d.m.Y')];
        $message = $this->render($template->body, $sample);
        $result = $sender->send($phone, $message);

        SmsLog::create([
            'tenant_id' => $tenant->id, 'to' => $phone, 'event_key' => 'test', 'template_id' => $template->id,
            'message' => $message, 'status' => $result->ok ? 'sent' : 'failed',
            'provider_id' => $result->providerId, 'error' => $result->error,
        ]);

        return $result;
    }

    /* ---------------- Shablon moderatsiyasi ---------------- */

    /** Shablonni Eskiz moderatsiyasiga yuboradi. $tenant = null — platforma shabloni. */
    public function submitTemplate(SmsTemplate $template, ?Tenant $tenant): void
    {
        $client = $this->clientFor($tenant);
        $id = $client->createTemplate($template->body);
        $template->update([
            'status' => 'pending',
            'eskiz_template_id' => $id,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Eskiz'dagi shablon holatini so'rab, bazadagi shablonlarni yangilaydi.
     *
     * @return int yangilangan shablonlar soni
     */
    public function syncTemplates(?Tenant $tenant): int
    {
        $client = $this->clientFor($tenant);
        $remote = collect($client->listTemplates());

        $query = SmsTemplate::query()->where('tenant_id', $tenant?->id)->whereIn('status', ['pending', 'approved', 'rejected']);
        $count = 0;
        foreach ($query->get() as $tpl) {
            $match = $remote->first(function ($r) use ($tpl) {
                if ($tpl->eskiz_template_id && $r['id'] === $tpl->eskiz_template_id) {
                    return true;
                }

                return $this->normalizeText($r['text']) === $this->normalizeText($tpl->body);
            });
            if (! $match) {
                continue;
            }
            $status = EskizClient::mapStatus($match['status']);
            $tpl->update([
                'status' => $status,
                'eskiz_template_id' => $match['id'] ?? $tpl->eskiz_template_id,
                'checked_at' => now(),
                'rejection_reason' => $status === 'rejected' ? ($tpl->rejection_reason ?: 'Eskiz shablonni rad etdi') : null,
            ]);
            $count++;
        }

        return $count;
    }

    /** Akkaunt ulanishini tekshiradi va balansni saqlaydi. */
    public function checkAccount(Tenant $tenant): int
    {
        $account = $tenant->smsAccount;
        if (! $account) {
            throw new \RuntimeException('Eskiz akkaunt kiritilmagan');
        }
        try {
            $client = $this->manager->tenantClient($account);
            $balance = $client->balance();
            $account->update(['balance' => $balance, 'last_checked_at' => now(), 'last_error' => null]);

            return (int) $balance;
        } catch (\Throwable $e) {
            $account->update(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 250)]);
            throw $e;
        }
    }

    /* ---------------- Yordamchilar ---------------- */

    private function clientFor(?Tenant $tenant): EskizClient
    {
        if ($tenant) {
            $account = $tenant->smsAccount;
            if (! $account) {
                throw new \RuntimeException('Avval Eskiz akkauntini ulang');
            }

            return $this->manager->tenantClient($account);
        }

        return $this->manager->platformClient();
    }

    public function render(string $body, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $body = str_replace('{'.$k.'}', (string) $v, $body);
        }

        return $body;
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    private function inQuietHours(array $options): bool
    {
        $from = $options['quiet_from'] ?? null;
        $to = $options['quiet_to'] ?? null;
        if (! $from || ! $to) {
            return false;
        }
        $now = now()->format('H:i');

        return $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
    }
}

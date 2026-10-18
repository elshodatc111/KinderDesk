<?php

namespace App\Services\Sms;

use App\Models\Tenant;
use App\Models\TenantSmsAccount;

class SmsManager
{
    /** Platforma SMS jo'natuvchisi (OTP, direktor ogohlantirishlari). */
    public function platform(): SmsSender
    {
        if (config('services.sms.driver') === 'eskiz') {
            return $this->platformClient();
        }

        return new LogSender();
    }

    public function platformClient(): EskizClient
    {
        $cacheKey = 'eskiz.platform.token';

        return new EskizClient(
            (string) config('services.eskiz.email'),
            (string) config('services.eskiz.password'),
            config('services.eskiz.from'),
            cache()->get($cacheKey),
            fn (string $token, $exp) => cache()->put($cacheKey, $token, $exp),
        );
    }

    /** Bog'chaning o'z Eskiz akkaunti. */
    public function tenantClient(TenantSmsAccount $account): EskizClient
    {
        return new EskizClient(
            $account->email,
            $account->password,
            $account->sender,
            $account->token,
            function (string $token, $exp) use ($account) {
                $account->forceFill(['token' => $token, 'token_expires_at' => $exp])->save();
            },
        );
    }

    public function forTenant(Tenant $tenant): ?SmsSender
    {
        $account = $tenant->smsAccount;
        if (! $account || ! $account->is_active) {
            return null;
        }

        return $this->tenantClient($account);
    }
}

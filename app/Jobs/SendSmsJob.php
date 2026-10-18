<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Models\Tenant;
use App\Services\Sms\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $logId, public string $scope)
    {
    }

    public function handle(SmsManager $manager): void
    {
        $log = SmsLog::find($this->logId);
        if (! $log || $log->status !== 'queued') {
            return;
        }

        $sender = $this->scope === 'tenant'
            ? ($log->tenant_id ? $manager->forTenant(Tenant::findOrFail($log->tenant_id)) : null)
            : $manager->platform();

        if (! $sender) {
            $log->update(['status' => 'blocked', 'error' => 'Yuboruvchi sozlanmagan']);

            return;
        }

        $result = $sender->send($log->to, $log->message);
        $log->update([
            'status' => $result->ok ? 'sent' : 'failed',
            'provider_id' => $result->providerId,
            'error' => $result->error ? mb_substr($result->error, 0, 250) : null,
        ]);
    }
}

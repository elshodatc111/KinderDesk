<?php

namespace App\Services\Billing;

use App\Models\PaymeTransaction;
use App\Models\Topup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payme Merchant API (JSON-RPC): Payme bizning serverga so'rov yuboradi.
 * Hisob maydoni: account[topup_id]. Summa tiyinda (1 so'm = 100 tiyin).
 */
class PaymeService
{
    private const ERR_AUTH = -32504;
    private const ERR_METHOD = -32601;
    private const ERR_PARSE = -32700;
    private const ERR_WRONG_AMOUNT = -31001;
    private const ERR_TX_NOT_FOUND = -31003;
    private const ERR_CANT_PERFORM = -31008;
    private const ERR_CANT_CANCEL = -31007;
    private const ERR_ACCOUNT_NOT_FOUND = -31050;
    private const ERR_ACCOUNT_BUSY = -31051;
    private const ERR_PENDING_EXISTS = -31099;

    public function __construct(private TopupService $topups)
    {
    }

    public function handle(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload) || ! isset($payload['method'])) {
            return $this->error(null, self::ERR_PARSE, 'Parse error');
        }
        $id = $payload['id'] ?? null;

        if (! $this->authorized($request)) {
            return $this->error($id, self::ERR_AUTH, 'Insufficient privileges to perform this method');
        }

        $params = $payload['params'] ?? [];

        try {
            $result = match ($payload['method']) {
                'CheckPerformTransaction' => $this->checkPerform($params),
                'CreateTransaction' => $this->create($params),
                'PerformTransaction' => $this->perform($params),
                'CancelTransaction' => $this->cancel($params),
                'CheckTransaction' => $this->check($params),
                'GetStatement' => $this->statement($params),
                'SetFiscalData' => ['success' => true],
                default => throw new PaymeError(self::ERR_METHOD, 'Method not found'),
            };
        } catch (PaymeError $e) {
            return $this->error($id, $e->getCode(), $e->getMessage(), $e->data);
        }

        return ['jsonrpc' => '2.0', 'result' => $result, 'id' => $id];
    }

    /* ---------------- Metodlar ---------------- */

    private function checkPerform(array $params): array
    {
        $this->resolveTopup($params);

        return ['allow' => true];
    }

    private function create(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $existing = PaymeTransaction::query()->where('payme_id', (string) ($params['id'] ?? ''))->lockForUpdate()->first();

            if ($existing) {
                if ($existing->state !== PaymeTransaction::STATE_CREATED) {
                    throw new PaymeError(self::ERR_CANT_PERFORM, 'Transaction cannot be performed');
                }
                if ($this->expired($existing)) {
                    $this->cancelExpired($existing);
                    throw new PaymeError(self::ERR_CANT_PERFORM, 'Transaction timed out');
                }

                return $this->createResponse($existing);
            }

            $topup = $this->resolveTopup($params);

            // Bitta buyurtmaga faqat bitta ochiq tranzaksiya
            $open = PaymeTransaction::query()->where('topup_id', $topup->id)->where('state', PaymeTransaction::STATE_CREATED)->lockForUpdate()->first();
            if ($open) {
                if ($this->expired($open)) {
                    $this->cancelExpired($open);
                } else {
                    throw new PaymeError(self::ERR_PENDING_EXISTS, 'Another transaction is pending for this order');
                }
            }

            $tx = PaymeTransaction::create([
                'payme_id' => (string) $params['id'],
                'topup_id' => $topup->id,
                'time' => (int) ($params['time'] ?? 0),
                'amount' => (int) $params['amount'],
                'state' => PaymeTransaction::STATE_CREATED,
                'create_time' => $this->nowMs(),
            ]);

            return $this->createResponse($tx);
        });
    }

    private function perform(array $params): array
    {
        $tx = $this->findTx($params);

        if ($tx->state === PaymeTransaction::STATE_COMPLETED) {
            return $this->performResponse($tx);
        }
        if ($tx->state !== PaymeTransaction::STATE_CREATED) {
            throw new PaymeError(self::ERR_CANT_PERFORM, 'Transaction cannot be performed');
        }
        if ($this->expired($tx)) {
            $this->cancelExpired($tx);
            throw new PaymeError(self::ERR_CANT_PERFORM, 'Transaction timed out');
        }

        DB::transaction(function () use ($tx) {
            $this->topups->completePayme($tx->topup);
            $tx->update(['state' => PaymeTransaction::STATE_COMPLETED, 'perform_time' => $this->nowMs()]);
        });

        return $this->performResponse($tx->fresh());
    }

    private function cancel(array $params): array
    {
        $tx = $this->findTx($params);
        $reason = (int) ($params['reason'] ?? 0);

        if (in_array($tx->state, [PaymeTransaction::STATE_CANCELED, PaymeTransaction::STATE_CANCELED_AFTER_COMPLETE], true)) {
            return $this->cancelResponse($tx);
        }

        if ($tx->state === PaymeTransaction::STATE_CREATED) {
            $tx->update(['state' => PaymeTransaction::STATE_CANCELED, 'reason' => $reason, 'cancel_time' => $this->nowMs()]);

            return $this->cancelResponse($tx);
        }

        // To'langan tranzaksiyani qaytarish: balansdagi mablag' yetarli bo'lishi shart
        try {
            DB::transaction(function () use ($tx, $reason) {
                $this->topups->reversePayme($tx->topup);
                $tx->update(['state' => PaymeTransaction::STATE_CANCELED_AFTER_COMPLETE, 'reason' => $reason, 'cancel_time' => $this->nowMs()]);
            });
        } catch (InsufficientBalance) {
            throw new PaymeError(self::ERR_CANT_CANCEL, 'Order completed and funds already used; cannot cancel');
        }

        return $this->cancelResponse($tx->fresh());
    }

    private function check(array $params): array
    {
        $tx = $this->findTx($params);

        return [
            'create_time' => $tx->create_time,
            'perform_time' => $tx->perform_time,
            'cancel_time' => $tx->cancel_time,
            'transaction' => (string) $tx->id,
            'state' => $tx->state,
            'reason' => $tx->reason,
        ];
    }

    private function statement(array $params): array
    {
        $from = (int) ($params['from'] ?? 0);
        $to = (int) ($params['to'] ?? 0);

        $rows = PaymeTransaction::query()->whereBetween('time', [$from, $to])->orderBy('time')->get();

        return ['transactions' => $rows->map(fn (PaymeTransaction $t) => [
            'id' => $t->payme_id,
            'time' => $t->time,
            'amount' => $t->amount,
            'account' => ['topup_id' => (string) $t->topup_id],
            'create_time' => $t->create_time,
            'perform_time' => $t->perform_time,
            'cancel_time' => $t->cancel_time,
            'transaction' => (string) $t->id,
            'state' => $t->state,
            'reason' => $t->reason,
        ])->all()];
    }

    /* ---------------- Yordamchilar ---------------- */

    private function resolveTopup(array $params): Topup
    {
        $topupId = $params['account']['topup_id'] ?? null;
        $topup = $topupId && ctype_digit((string) $topupId) ? Topup::query()->where('method', 'payme')->find((int) $topupId) : null;

        if (! $topup) {
            throw new PaymeError(self::ERR_ACCOUNT_NOT_FOUND, 'Order not found', 'topup_id');
        }
        if ($topup->status !== 'pending') {
            throw new PaymeError(self::ERR_ACCOUNT_BUSY, 'Order is not available for payment', 'topup_id');
        }
        if ((int) ($params['amount'] ?? 0) !== $topup->amount * 100) {
            throw new PaymeError(self::ERR_WRONG_AMOUNT, 'Wrong amount');
        }

        return $topup;
    }

    private function findTx(array $params): PaymeTransaction
    {
        $tx = PaymeTransaction::query()->where('payme_id', (string) ($params['id'] ?? ''))->first();
        if (! $tx) {
            throw new PaymeError(self::ERR_TX_NOT_FOUND, 'Transaction not found');
        }

        return $tx;
    }

    private function expired(PaymeTransaction $tx): bool
    {
        return ($this->nowMs() - $tx->create_time) > config('services.payme.timeout_ms');
    }

    private function cancelExpired(PaymeTransaction $tx): void
    {
        $tx->update(['state' => PaymeTransaction::STATE_CANCELED, 'reason' => 4, 'cancel_time' => $this->nowMs()]);
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    private function createResponse(PaymeTransaction $tx): array
    {
        return ['create_time' => $tx->create_time, 'transaction' => (string) $tx->id, 'state' => $tx->state];
    }

    private function performResponse(PaymeTransaction $tx): array
    {
        return ['transaction' => (string) $tx->id, 'perform_time' => $tx->perform_time, 'state' => $tx->state];
    }

    private function cancelResponse(PaymeTransaction $tx): array
    {
        return ['transaction' => (string) $tx->id, 'cancel_time' => $tx->cancel_time, 'state' => $tx->state];
    }

    private function authorized(Request $request): bool
    {
        $allowedIps = config('services.payme.allowed_ips');
        if ($allowedIps && ! in_array($request->ip(), $allowedIps, true)) {
            return false;
        }

        $cfg = config('services.payme');
        $key = $cfg['test_mode'] ? $cfg['test_key'] : $cfg['key'];
        if (! filled($key)) {
            return false;
        }

        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Basic ')) {
            return false;
        }
        $decoded = base64_decode(substr($header, 6), true);

        return $decoded !== false && hash_equals('Paycom:'.$key, $decoded);
    }

    private function error(mixed $id, int $code, string $message, ?string $data = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => ['uz' => $message, 'ru' => $message, 'en' => $message],
                'data' => $data,
            ],
            'id' => $id,
        ];
    }
}

<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Eskiz.uz API klienti. Endpointlar config/services.php (eskiz.endpoints) da.
 * Bitta klass ham platforma, ham har bir bog'chaning o'z akkaunti uchun ishlaydi.
 */
class EskizClient implements SmsSender
{
    private ?string $token;

    /**
     * @param  callable|null  $onToken  yangi token olinganda chaqiriladi: fn(string $token, \Carbon\Carbon $expiresAt)
     */
    public function __construct(
        private string $email,
        private string $password,
        private ?string $from = null,
        ?string $token = null,
        private $onToken = null,
        private ?string $baseUrl = null,
    ) {
        $this->token = $token;
        $this->baseUrl = rtrim($baseUrl ?? config('services.eskiz.base_url'), '/');
    }

    private function url(string $endpointKey): string
    {
        return $this->baseUrl.config("services.eskiz.endpoints.$endpointKey");
    }

    private function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson()->withToken((string) $this->token);
    }

    /** Login qilib token oladi. */
    public function login(): string
    {
        $res = Http::timeout(15)->acceptJson()->asForm()->post($this->url('login'), [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $token = $res->json('data.token');
        if (! $res->successful() || ! $token) {
            throw new \RuntimeException($res->json('message') ?: 'Eskiz: login xato (email yoki parol noto\'g\'ri)');
        }

        $this->token = $token;
        if ($this->onToken) {
            ($this->onToken)($token, now()->addDays(29));
        }

        return $token;
    }

    /** 401 bo'lsa bir marta qayta login qilib urinadi. */
    private function request(string $method, string $endpointKey, array $data = [], bool $form = false): Response
    {
        if (! $this->token) {
            $this->login();
        }

        $make = function () use ($method, $endpointKey, $data, $form): Response {
            $req = $this->http();
            if ($form) {
                $req = $req->asForm();
            }

            return $req->{$method}($this->url($endpointKey), $data);
        };

        $res = $make();
        if ($res->status() === 401) {
            $this->login();
            $res = $make();
        }

        return $res;
    }

    public function send(string $phone, string $message): SendResult
    {
        try {
            $payload = [
                'mobile_phone' => $phone,
                'message' => $message,
                'from' => $this->from ?: config('services.eskiz.from'),
            ];
            if ($cb = config('services.eskiz.callback_url')) {
                $payload['callback_url'] = $cb;
            }
            $res = $this->request('post', 'send', $payload, true);

            if ($res->successful()) {
                return SendResult::ok((string) ($res->json('id') ?? $res->json('data.id') ?? ''));
            }

            return SendResult::fail((string) ($res->json('message') ?: 'Eskiz xatosi ('.$res->status().')'));
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }

    /** Akkaunt balansi (SMS limiti). */
    public function balance(): ?int
    {
        $res = $this->request('get', 'limit');
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('message') ?: 'Eskiz: balansni olib bo\'lmadi');
        }
        $b = $res->json('data.balance') ?? $res->json('balance');

        return $b === null ? null : (int) $b;
    }

    /** Shablonni moderatsiyaga yuboradi. Eskiz shablon id'sini qaytaradi (bo'lsa). */
    public function createTemplate(string $text): ?string
    {
        $res = $this->request('post', 'template_create', ['template' => $text], true);
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('message') ?: 'Eskiz: shablon yuborilmadi ('.$res->status().')');
        }

        $id = $res->json('result.id') ?? $res->json('data.id') ?? $res->json('id');

        return $id === null ? null : (string) $id;
    }

    /**
     * Akkauntdagi shablonlar ro'yxati.
     *
     * @return array<int, array{id: ?string, text: string, status: string}>
     */
    public function listTemplates(): array
    {
        $res = $this->request('get', 'template_list');
        if (! $res->successful()) {
            throw new \RuntimeException($res->json('message') ?: 'Eskiz: shablonlar ro\'yxatini olib bo\'lmadi');
        }

        $rows = $res->json('result') ?? $res->json('data') ?? [];
        if (isset($rows['data']) && is_array($rows['data'])) {
            $rows = $rows['data'];
        }

        $out = [];
        foreach ((array) $rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => isset($row['id']) ? (string) $row['id'] : null,
                'text' => (string) ($row['template'] ?? $row['text'] ?? ''),
                'status' => strtolower((string) ($row['status'] ?? '')),
            ];
        }

        return $out;
    }

    /** Eskiz holatini ichki holatga (pending|approved|rejected) o'tkazadi. */
    public static function mapStatus(string $eskizStatus): string
    {
        $eskizStatus = strtolower(trim($eskizStatus));
        foreach (config('kinderdesk.eskiz_status_map') as $internal => $values) {
            if (in_array($eskizStatus, $values, true)) {
                return $internal;
            }
        }

        return 'pending';
    }
}

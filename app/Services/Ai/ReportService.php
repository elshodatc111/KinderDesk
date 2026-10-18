<?php

namespace App\Services\Ai;

use App\Models\AiReport;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;

/**
 * Direktor uchun mustaqil AI hisobot. Raqamlarni kod hisoblaydi (FactsPacket), AI izohlaydi va sabab taxmin qiladi.
 * Natijadagi har raqam paketdagi faktlar bilan tekshiriladi; mos kelmasa bir marta qayta so'raladi, so'ng «ishonchsiz» belgilanadi.
 */
class ReportService
{
    public function __construct(private AiClient $client, private AiGate $gate, private FactsPacket $packet, private NotificationService $notify)
    {
    }

    public function feature(string $period): string
    {
        return $period === 'weekly' ? 'ai_report_weekly' : 'ai_report_daily';
    }

    public function systemPrompt(string $lang, string $period): string
    {
        $l = $lang === 'ru' ? 'rus' : "o'zbek";
        $scope = $period === 'weekly' ? '7 kunlik' : 'kunlik (30 kunlik tendensiya bilan)';

        return <<<TXT
Sen bog'cha direktori uchun mustaqil tahlilchisan. Senga tizimning HISOBLANGAN FAKTLAR PAKETI beriladi ({$scope}). Hisobotni {$l} tilida yoz.
Qat'iy qoidalar:
1. Raqamlarni faqat paketdan ol. Hech qachon raqam o'ylab topma va o'zing hisoblab yangi raqam chiqarma. Paketda yo'q narsani «ma'lumot yetarli emas» deb yoz.
2. Har kamchilik uchun dalil raqamlarni `evidence` ro'yxatida keltir: {"key": "paketdagi nuqtali yo'l (masalan attendance.percent_7d)", "value": paketdagi qiymat}.
3. Sabab dalilga tayansa cause_type="grounded", aks holda "hypothesis"; ma'lumot yetmasa "insufficient_data". Ishonch: confidence = high|medium|low.
4. Xodimlarni faqat paketdagi belgi (masalan Xodim-12) yoki guruh va lavozim bilan ko'rsat. Ism, telefon yoki boshqa shaxsiy ma'lumot yozma.
5. Javobgar sifatida ROL ko'rsat (administrator, hisobchi, tarbiyachi ...). Har kamchilik uchun aniq qadam (action) yoz.
6. Faqat quyidagi JSON ni qaytar, boshqa matn yo'q:
{"today":["3 qisqa satr: bugungi holat"],"issues":[{"title":"","evidence":[{"key":"","value":0}],"cause":"","cause_type":"grounded","confidence":"medium","role":"","action":""}],"good":[""],"tomorrow":[""]}
TXT;
    }

    /** @return array<int, string> topilgan muammolar */
    public function verify(array $result, array $facts): array
    {
        $flat = FactsPacket::flatten($facts);
        $problems = [];

        foreach ($result['issues'] ?? [] as $i) {
            foreach ($i['evidence'] ?? [] as $ev) {
                $key = (string) ($ev['key'] ?? '');
                if (! array_key_exists($key, $flat)) {
                    $problems[] = "kalit yo'q: $key";
                    continue;
                }
                $a = $flat[$key];
                $b = $ev['value'] ?? null;
                $ok = is_numeric($a) && is_numeric($b) ? abs((float) $a - (float) $b) < 0.011 : (string) $a === (string) $b;
                if (! $ok) {
                    $problems[] = "$key paketda ".json_encode($a).", hisobotda ".json_encode($b);
                }
            }
        }

        $allowed = [];
        foreach ($flat as $k => $v) {
            if (is_numeric($v)) {
                $allowed[] = (float) $v;
            } elseif (is_string($v) && preg_match_all('/\d+/', $v, $m)) {
                foreach ($m[0] as $n) {
                    $allowed[] = (float) $n;
                }
            }
            if (preg_match_all('/\d+/', (string) $k, $m)) {
                foreach ($m[0] as $n) {
                    $allowed[] = (float) $n;
                }
            }
        }
        $texts = array_merge($result['today'] ?? [], $result['good'] ?? [], $result['tomorrow'] ?? []);
        foreach ($result['issues'] ?? [] as $i) {
            $texts[] = ($i['title'] ?? '').' '.($i['cause'] ?? '').' '.($i['action'] ?? '');
        }
        foreach ($texts as $text) {
            $text = preg_replace('/\d{4}-\d{2}-\d{2}/', ' ', (string) $text);
            $text = preg_replace('/Xodim-\d+/u', ' ', $text);
            preg_match_all('/\d+(?:[ \x{00A0}]\d{3})*(?:[.,]\d+)?/u', $text, $m);
            foreach ($m[0] as $raw) {
                $n = (float) str_replace(',', '.', preg_replace('/[ \x{00A0}]/u', '', $raw));
                if ($n < 10 || ($n >= 1990 && $n <= 2100)) {
                    continue;
                }
                $tol = $n < 100 ? 0.51 : 1.01;
                if (! collect($allowed)->contains(fn ($x) => abs($x - $n) < $tol)) {
                    $problems[] = "raqam paketda yo'q: $raw";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    private function parse(string $text): ?array
    {
        $a = strpos($text, '{');
        $b = strrpos($text, '}');
        if ($a === false || $b === false) {
            return null;
        }
        $j = json_decode(substr($text, $a, $b - $a + 1), true);

        return is_array($j) && isset($j['issues']) ? $j : null;
    }

    public function generate(Tenant $t, Carbon $date, string $period = 'daily'): AiReport
    {
        if ($err = $this->gate->tenantReady($t, $this->feature($period))) {
            throw new AiException($err, 'off');
        }
        $this->gate->assertTokens($t);

        $facts = $this->packet->build($date, $period);
        $report = AiReport::updateOrCreate(['date' => $date->toDateString(), 'period' => $period], ['tenant_id' => $t->id, 'status' => 'failed', 'facts' => $facts, 'result' => null, 'attempts' => 0, 'error' => null, 'model' => $this->client->model('report')]);
        $lang = $t->owner?->locale ?: 'uz';
        $system = $this->systemPrompt($lang, $period);
        $messages = [['role' => 'user', 'content' => "FAKTLAR PAKETI:\n".json_encode($facts, JSON_UNESCAPED_UNICODE)]];

        $result = null;
        $problems = [];
        $verifyRetried = false;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $report->update(['attempts' => $attempt]);
            try {
                $res = $this->client->complete($system, $messages, 'report', $t, null, 3000);
            } catch (AiException $e) {
                if ($e->reason !== 'unavailable') {
                    $report->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 290)]);
                    $this->failed($t, $date, $period);

                    return $report;
                }
                $report->update(['error' => mb_substr($e->getMessage(), 0, 290)]);
                continue;
            }
            $parsed = $this->parse($res['text']);
            if (! $parsed) {
                $report->update(['error' => "Javob JSON emas"]);
                $messages[] = ['role' => 'assistant', 'content' => $res['text']];
                $messages[] = ['role' => 'user', 'content' => "Faqat belgilangan JSON formatida qayta yubor."];
                continue;
            }
            $problems = $this->verify($parsed, $facts);
            $result = $parsed;
            if ($problems === [] || $verifyRetried) {
                break;
            }
            $verifyRetried = true;
            $messages[] = ['role' => 'assistant', 'content' => $res['text']];
            $messages[] = ['role' => 'user', 'content' => "Quyidagi raqamlar paketga mos kelmadi: ".implode('; ', array_slice($problems, 0, 12)).". Faqat paketdagi raqamlar bilan hisobotni qayta yoz."];
        }

        if (! $result) {
            $report->update(['status' => 'failed']);
            $this->failed($t, $date, $period);

            return $report;
        }
        $report->update(['status' => $problems === [] ? 'ready' : 'unreliable', 'result' => $result + ['problems' => $problems], 'error' => null]);
        $this->notify->toTenantUsers($t, 'ai.report', __('AI hisobot tayyor'), $date->format('d.m.Y'), route('app.ai.reports.show', $report), $problems ? 'warn' : 'info', 'sparkles');

        return $report;
    }

    private function failed(Tenant $t, Carbon $date, string $period): void
    {
        $this->notify->toTenantUsers($t, 'ai.report', __('AI hisobot tayyor emas'), $date->format('d.m.Y').' — '.__('xizmat vaqtincha ishlamadi. Keyingi kuni qayta uriniladi.'), route('app.ai.reports.index'), 'warn', 'sparkles');
    }

    /** Pseudonim belgilarini (Xodim-12) direktorga ismga qaytaradi (sozlama yoqilgan bo'lsa). */
    public function deanonymize(string $text): string
    {
        if (! tenant()->setting('ai.show_names', false)) {
            return $text;
        }
        $names = Employee::pluck('full_name', 'id');

        return preg_replace_callback('/Xodim-(\d+)/u', fn ($m) => $names[(int) $m[1]] ?? $m[0], $text);
    }
}

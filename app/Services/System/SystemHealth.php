<?php

namespace App\Services\System;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Server holati: super admin panelida real vaqtga yaqin ko'rsatiladi. */
class SystemHealth
{
    /** @return array{overall:string, checks:array<int,array>, metrics:array, at:string} */
    public function collect(): array
    {
        $checks = [
            $this->disk(), $this->memory(), $this->cpu(), $this->database(), $this->cache(),
            $this->scheduler(), $this->worker(), $this->failedJobs(), $this->realtime(), $this->sms(), $this->ai(), $this->storage(), $this->logs(),
        ];
        $rank = ['ok' => 0, 'warn' => 1, 'fail' => 2, 'na' => 0];
        $overall = collect($checks)->map(fn ($c) => $rank[$c['status']])->max();

        return [
            'overall' => ['ok', 'warn', 'fail'][$overall],
            'checks' => $checks,
            'info' => [
                'php' => PHP_VERSION, 'laravel' => app()->version(), 'os' => PHP_OS_FAMILY,
                'env' => app()->environment(), 'debug' => (bool) config('app.debug'), 'time' => now()->format('d.m.Y H:i:s'),
                'timezone' => config('app.timezone'), 'db' => config('database.default'), 'queue' => config('queue.default'),
                'cache' => config('cache.default'), 'broadcast' => config('broadcasting.default'), 'sms' => config('kinderdesk.sms_driver', env('SMS_DRIVER', 'log')),
            ],
            'at' => now()->toIso8601String(),
        ];
    }

    private function row(string $key, string $label, string $status, string $value, ?string $hint = null, ?int $percent = null, array $r = []): array
    {
        $label = __($label);
        $value = __($value, $r);
        $hint = $hint === null ? null : __($hint, $r);

        return compact('key', 'label', 'status', 'value', 'hint', 'percent');
    }

    private function disk(): array
    {
        $path = base_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if (! $free || ! $total) {
            return $this->row('disk', 'Disk', 'na', '—');
        }
        $usedPct = (int) round(100 - $free / $total * 100);
        $status = $free / $total < 0.05 ? 'fail' : ($free / $total < 0.15 ? 'warn' : 'ok');

        return $this->row('disk', 'Disk', $status, $this->bytes($free).' / '.$this->bytes($total), 'Bo\'sh joy / jami', $usedPct);
    }

    private function memory(): array
    {
        $total = $free = null;
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            $info = File::get('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $info, $a) && preg_match('/MemAvailable:\s+(\d+)/', $info, $b)) {
                $total = $a[1] * 1024;
                $free = $b[1] * 1024;
            }
        } elseif (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            $out = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>NUL');
            if ($out && preg_match('/FreePhysicalMemory=(\d+)/', $out, $a) && preg_match('/TotalVisibleMemorySize=(\d+)/', $out, $b)) {
                $free = $a[1] * 1024;
                $total = $b[1] * 1024;
            }
        }
        if (! $total) {
            return $this->row('memory', 'Xotira (RAM)', 'na', 'PHP: :n', 'Server xotirasi o\'qilmadi', null, ['n' => $this->bytes(memory_get_usage(true))]);
        }
        $usedPct = (int) round(100 - $free / $total * 100);

        return $this->row('memory', 'Xotira (RAM)', $usedPct > 92 ? 'fail' : ($usedPct > 80 ? 'warn' : 'ok'), $this->bytes($total - $free).' / '.$this->bytes($total), 'Band / jami', $usedPct);
    }

    private function cpu(): array
    {
        $pct = null;
        $hint = null;
        $rep = [];
        if (function_exists('sys_getloadavg') && ($l = @sys_getloadavg())) {
            $cores = max(1, $this->cores());
            $pct = (int) min(100, round($l[0] / $cores * 100));
            $hint = 'Yuklama (1/5/15 daq): :load · yadro: :cores';
            $rep = ['load' => implode(' / ', array_map(fn ($x) => number_format($x, 2), $l)), 'cores' => $cores];
        } elseif (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            $out = @shell_exec('wmic cpu get loadpercentage /Value 2>NUL');
            if ($out && preg_match('/LoadPercentage=(\d+)/', $out, $m)) {
                $pct = (int) $m[1];
                $hint = 'Protsessor yuklamasi';
            }
        }
        if ($pct === null) {
            return $this->row('cpu', 'Protsessor (CPU)', 'na', '—', 'Bu serverda yuklamani o\'qib bo\'lmadi');
        }

        return $this->row('cpu', 'Protsessor (CPU)', $pct > 95 ? 'fail' : ($pct > 80 ? 'warn' : 'ok'), $pct.'%', $hint, $pct, $rep);
    }

    private function cores(): int
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/cpuinfo')) {
            return max(1, substr_count(File::get('/proc/cpuinfo'), "processor\t"));
        }

        return (int) (getenv('NUMBER_OF_PROCESSORS') ?: 1);
    }

    private function database(): array
    {
        try {
            $t = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $t) * 1000);

            return $this->row('database', 'Ma\'lumotlar bazasi', $ms > 500 ? 'warn' : 'ok', 'Ulangan · :ms ms', config('database.default'), null, ['ms' => $ms]);
        } catch (\Throwable $e) {
            return $this->row('database', 'Ma\'lumotlar bazasi', 'fail', 'Ulanmagan', mb_substr($e->getMessage(), 0, 120));
        }
    }

    private function cache(): array
    {
        try {
            $t = microtime(true);
            Cache::put('kd:health:ping', 1, 30);
            $ok = Cache::get('kd:health:ping') === 1;
            $ms = (int) round((microtime(true) - $t) * 1000);

            return $this->row('cache', 'Kesh', $ok ? 'ok' : 'warn', ($ok ? 'Ishlayapti' : 'Javob yo\'q').' · :ms ms', config('cache.default'), null, ['ms' => $ms]);
        } catch (\Throwable $e) {
            return $this->row('cache', 'Kesh', 'fail', 'Xato', mb_substr($e->getMessage(), 0, 120));
        }
    }

    private function scheduler(): array
    {
        $at = Cache::get('kd:health:scheduler');
        if (! $at) {
            return $this->row('scheduler', 'Rejalashtiruvchi (scheduler)', 'fail', 'Ishlamayapti', 'php artisan schedule:work buyrug\'ini ishga tushiring — oylik hisoblash, obuna va eslatmalar shunga bog\'liq');
        }
        $age = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($at));

        return $this->row('scheduler', 'Rejalashtiruvchi (scheduler)', $age > 180 ? 'fail' : 'ok', $age > 180 ? 'To\'xtagan' : 'Ishlayapti', 'Oxirgi signal: :t', null, ['t' => \Illuminate\Support\Carbon::parse($at)->diffForHumans()]);
    }

    private function worker(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->row('worker', 'Navbat ishchisi (queue worker)', 'ok', 'Sinxron rejim', 'Navbat ishlatilmaydi — SMS darhol yuboriladi');
        }
        $pending = 0;
        try {
            $pending = (int) DB::table('jobs')->count();
        } catch (\Throwable $e) {
        }
        $at = Cache::get('kd:health:worker');
        $age = $at ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($at)) : null;
        if ($age === null) {
            return $this->row('worker', 'Navbat ishchisi (queue worker)', 'fail', 'Ishlamayapti', 'php artisan queue:work buyrug\'ini ishga tushiring — SMS yuborish shunga bog\'liq. Navbatda: :n', null, ['n' => $pending]);
        }
        $status = $age > 240 ? 'fail' : ($pending > 200 ? 'warn' : 'ok');

        return $this->row('worker', 'Navbat ishchisi (queue worker)', $status, $age > 240 ? 'To\'xtagan' : 'Ishlayapti', 'Navbatda: :n · oxirgi signal: :t', null, ['n' => $pending, 'time' => 0, 't' => \Illuminate\Support\Carbon::parse($at)->diffForHumans()]);
    }

    private function failedJobs(): array
    {
        try {
            $n = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            return $this->row('failed', 'Xato bilan tugagan vazifalar', 'na', '—');
        }

        return $this->row('failed', 'Xato bilan tugagan vazifalar', $n > 20 ? 'fail' : ($n > 0 ? 'warn' : 'ok'), (string) $n, $n ? 'php artisan queue:retry all yoki queue:flush' : null);
    }

    private function realtime(): array
    {
        $driver = config('broadcasting.default');
        if ($driver !== 'reverb') {
            return $this->row('realtime', 'Jonli aloqa', 'ok', 'Yangilanib turish rejimi', 'Sahifalar har 20 soniyada yangilanadi (:d)', null, ['d' => $driver]);
        }
        $host = config('reverb.servers.reverb.host') ?: '127.0.0.1';
        $port = (int) (config('reverb.servers.reverb.port') ?: 8080);
        $host = in_array($host, ['0.0.0.0', '::'], true) ? '127.0.0.1' : $host;
        $c = @fsockopen($host, $port, $en, $es, 1.0);
        if ($c) {
            fclose($c);

            return $this->row('realtime', 'Jonli aloqa (Reverb)', 'ok', 'Ishlayapti', $host.':'.$port);
        }

        return $this->row('realtime', 'Jonli aloqa (Reverb)', 'fail', 'Ishlamayapti', 'php artisan reverb:start');
    }

    private function sms(): array
    {
        try {
            $since = now()->subDay();
            $failed = SmsLog::withoutGlobalScopes()->where('created_at', '>=', $since)->whereIn('status', ['failed', 'blocked'])->count();
            $total = SmsLog::withoutGlobalScopes()->where('created_at', '>=', $since)->count();
        } catch (\Throwable $e) {
            return $this->row('sms', 'SMS (24 soat)', 'na', '—');
        }
        $ratio = $total ? $failed / $total : 0;

        return $this->row('sms', 'SMS (24 soat)', $total >= 5 && $ratio > 0.5 ? 'fail' : ($failed > 0 && $ratio > 0.2 ? 'warn' : 'ok'), ':total ta · xato: :failed', 'Haydovchi: :d', null, ['total' => $total, 'failed' => $failed, 'd' => env('SMS_DRIVER', 'log')]);
    }

    private function ai(): array
    {
        try {
            if (! app(\App\Services\Ai\AiClient::class)->configured()) {
                return $this->row('ai', 'AI xizmati', 'na', "Ulanmagan");
            }
            $last = \App\Models\PlatformSetting::get('ai.last_ok_at');
            $errors = \App\Models\AiUsage::where('created_at', '>=', now()->subDay())->where('status', 'error')->count();
            $ok = \App\Models\AiUsage::where('created_at', '>=', now()->subDay())->where('status', 'ok')->count();
        } catch (\Throwable $e) {
            return $this->row('ai', 'AI xizmati', 'na', '—');
        }
        $status = $errors >= 3 && $ok === 0 ? 'fail' : ($errors > 0 ? 'warn' : 'ok');

        return $this->row('ai', 'AI xizmati', $status, $last ? 'Oxirgi muvaffaqiyatli: :t' : "Hali chaqirilmagan", '24 soat: :ok ta muvaffaqiyatli, :err ta xato', null,
            ['t' => $last ? \Illuminate\Support\Carbon::parse($last)->format('d.m H:i') : '', 'ok' => $ok, 'err' => $errors]);
    }

    private function storage(): array
    {
        $dirs = [storage_path('app'), storage_path('logs'), storage_path('framework/cache'), base_path('bootstrap/cache')];
        $bad = array_values(array_filter($dirs, fn ($d) => ! is_dir($d) || ! is_writable($d)));

        return $bad
            ? $this->row('storage', 'Fayl tizimi', 'fail', 'Yozib bo\'lmaydi', implode(', ', array_map(fn ($d) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $d), $bad)))
            : $this->row('storage', 'Fayl tizimi', 'ok', 'Yozish mumkin', 'storage/ va bootstrap/cache');
    }

    private function logs(): array
    {
        $file = storage_path('logs/laravel.log');
        if (! is_file($file)) {
            return $this->row('logs', 'Xatolar jurnali', 'ok', 'Xato yo\'q', 'laravel.log mavjud emas');
        }
        $size = filesize($file);
        $fh = fopen($file, 'r');
        fseek($fh, max(0, $size - 400000));
        $tail = stream_get_contents($fh);
        fclose($fh);
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\][^\n]*\.(ERROR|CRITICAL|ALERT|EMERGENCY):/m', $tail, $m);
        $cut = now()->subDay()->format('Y-m-d H:i:s');
        $recent = count(array_filter($m[1], fn ($d) => $d >= $cut));

        return $this->row('logs', 'Xatolar jurnali (24 soat)', $recent > 50 ? 'fail' : ($recent > 0 ? 'warn' : 'ok'), ':n ta xato', 'Hajmi: :size · storage/logs/laravel.log', null, ['n' => $recent, 'size' => $this->bytes($size)]);
    }

    private function bytes(float|int $b): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $u) {
            if ($b < 1024 || $u === 'TB') {
                return ($i ? number_format($b, 1) : (string) $b).' '.$u;
            }
            $b /= 1024;
        }

        return '';
    }
}

<?php

namespace App\Services\Backup;

use App\Models\AuditLog;
use App\Models\BackupJob;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Tashqi zaxira tizimi uchun: kuniga bitta .sql nusxa, 24 soat saqlanadi.
 * Faqat ma'lumotlar bazasi — APP_KEY bu yerga KIRMAYDI (shifrlangan maydonlarni ochish uchun uni alohida saqlang).
 */
class BackupService
{
    public const KEEP_HOURS = 24;

    public function enabled(): bool
    {
        return PlatformSetting::get('backup.enabled', '0') === '1' && filled(PlatformSetting::get('backup.login')) && filled(PlatformSetting::get('backup.password_hash'));
    }

    /** IP ruxsat ro'yxati (vergul/probel bilan). Bo'sh bo'lsa — hech kimga ruxsat yo'q. */
    public function ipAllowed(string $ip): bool
    {
        $list = preg_split('/[\s,;]+/', (string) PlatformSetting::get('backup.ips', ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($list as $rule) {
            if ($rule === $ip || (str_contains($rule, '/') && $this->inCidr($ip, $rule))) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr) + [1 => 32];
        $a = @inet_pton($ip);
        $b = @inet_pton($net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if ($bytes && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;

        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    public function credentialsOk(Request $request): bool
    {
        $login = $request->input('login');
        $pass = $request->input('password');
        if ($login === null && $request->getUser() !== null) {
            $login = $request->getUser();
            $pass = $request->getPassword();
        }
        $okLogin = hash_equals((string) PlatformSetting::get('backup.login'), (string) $login);
        $okPass = Hash::check((string) $pass, (string) PlatformSetting::get('backup.password_hash'));

        return $okLogin && $okPass;
    }

    public function setCredentials(string $login, ?string $password): void
    {
        PlatformSetting::put('backup.login', $login);
        if (filled($password)) {
            PlatformSetting::put('backup.password_hash', Hash::make($password));
        }
    }

    /** Bugungi (Toshkent) ish bor bo'lsa — o'sha qaytadi, yo'q bo'lsa yangisi yaratiladi. */
    public function requestJob(string $ip): BackupJob
    {
        $today = now('Asia/Tashkent')->toDateString();
        $job = BackupJob::whereDate('for_date', $today)->whereIn('status', ['pending', 'running', 'ready'])->latest('id')->first();
        if ($job) {
            return $job;
        }
        $job = BackupJob::create(['uid' => Str::lower(Str::random(32)), 'status' => 'pending', 'for_date' => $today, 'requested_ip' => $ip]);
        $this->spawn($job);

        return $job;
    }

    /** Ishni fon jarayonida ishga tushiradi (so'rov javobini kutdirmaydi). Bo'lmasa — `backup:run --pending` rejalashtiruvchisi oladi. */
    public function spawn(BackupJob $job): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        try {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
            $artisan = base_path('artisan');
            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen('start /B "" "'.$php.'" "'.$artisan.'" backup:run '.$job->uid.' > NUL 2>&1', 'r'));
            } else {
                exec('nohup '.escapeshellarg($php).' '.escapeshellarg($artisan).' backup:run '.escapeshellarg($job->uid).' > /dev/null 2>&1 &');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Dump yaratadi. Faqat bitta jarayon bir ishni oladi. */
    public function run(BackupJob $job): void
    {
        if (BackupJob::where('id', $job->id)->where('status', 'pending')->update(['status' => 'running', 'started_at' => now()]) !== 1) {
            return;
        }
        $rel = 'backups/'.$job->uid.'.sql';
        try {
            Storage::disk('local')->makeDirectory('backups');
            $abs = Storage::disk('local')->path($rel);
            $this->dump($abs);
            $job->update([
                'status' => 'ready', 'path' => $rel, 'size' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
                'ready_at' => now(), 'expires_at' => now()->addHours(self::KEEP_HOURS), 'error' => null,
            ]);
        } catch (\Throwable $e) {
            @unlink(Storage::disk('local')->path($rel));
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 280)]);
            report($e);
        }
    }

    public function runPending(): int
    {
        // Uzilib qolgan «running» ishlar (15 daqiqadan ortiq) qayta navbatga qaytariladi
        BackupJob::where('status', 'running')->where('started_at', '<', now()->subMinutes(15))->update(['status' => 'pending']);
        $n = 0;
        foreach (BackupJob::where('status', 'pending')->orderBy('id')->get() as $job) {
            $this->run($job);
            $n++;
        }

        return $n;
    }

    /** 24 soatdan o'tgan nusxalarni o'chiradi. */
    public function purge(): int
    {
        $n = 0;
        foreach (BackupJob::where('status', 'ready')->where('expires_at', '<', now())->get() as $job) {
            if ($job->path) {
                Storage::disk('local')->delete($job->path);
            }
            $job->update(['status' => 'expired', 'path' => null]);
            $n++;
        }

        return $n;
    }

    public function log(string $action, Request $request, array $meta = []): void
    {
        AuditLog::create(['action' => $action, 'ip' => $request->ip(), 'meta' => $meta ?: null]);
    }

    /* ---------------- Dumper ---------------- */

    private function dump(string $target): void
    {
        $conn = config('database.default');
        $cfg = config("database.connections.$conn");
        if (in_array($cfg['driver'], ['mysql', 'mariadb'], true) && $this->mysqldump($cfg, $target)) {
            return;
        }
        $this->phpDump($target);
    }

    private function mysqldump(array $cfg, string $target): bool
    {
        $bin = trim((string) PlatformSetting::get('backup.mysqldump', ''));
        $candidates = array_filter([$bin, 'mysqldump', 'mariadb-dump', 'C:\\xampp\\mysql\\bin\\mysqldump.exe']);
        foreach ($candidates as $c) {
            $args = [$c, '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'], '--single-transaction', '--quick', '--routines', '--triggers', '--default-character-set=utf8mb4', '--result-file='.$target, $cfg['database']];
            $p = new Process($args, null, ['MYSQL_PWD' => (string) $cfg['password']]); // parol buyruq satrida ko'rinmasin
            $p->setTimeout(1800);
            try {
                $p->run();
            } catch (\Throwable $e) {
                continue;
            }
            if ($p->isSuccessful() && is_file($target) && filesize($target) > 0) {
                return true;
            }
        }
        @unlink($target);

        return false;
    }

    /** Toza PHP dumper (mysqldump bo'lmasa yoki sqlite uchun). */
    private function phpDump(string $target): void
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $fh = fopen($target, 'w');
        if (! $fh) {
            throw new \RuntimeException('Fayl yaratib bo\'lmadi');
        }
        fwrite($fh, "-- KinderDesk zaxira nusxasi ".now()->toIso8601String()."\n-- Shifrlangan maydonlarni ochish uchun APP_KEY alohida saqlanishi kerak.\n");
        if ($driver === 'mysql' || $driver === 'mariadb') {
            fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))->map(fn ($r) => array_values((array) $r)[0])->all();
        } else {
            $tables = collect(DB::select("select name from sqlite_master where type='table' and name not like 'sqlite_%' order by name"))->pluck('name')->all();
        }
        $q = fn (string $t) => ($driver === 'mysql' || $driver === 'mariadb') ? '`'.str_replace('`', '``', $t).'`' : '"'.str_replace('"', '""', $t).'"';
        foreach ($tables as $t) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $create = array_values((array) DB::selectOne('SHOW CREATE TABLE '.$q($t)))[1];
                fwrite($fh, "\nDROP TABLE IF EXISTS ".$q($t).";\n".$create.";\n");
            } else {
                $create = DB::selectOne("select sql from sqlite_master where type='table' and name = ?", [$t])->sql;
                fwrite($fh, "\nDROP TABLE IF EXISTS ".$q($t).";\n".$create.";\n");
            }
            $stmt = $pdo->query('SELECT * FROM '.$q($t));
            $batch = [];
            $cols = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $cols ??= implode(', ', array_map($q, array_keys($row)));
                $batch[] = '('.implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row)).')';
                if (count($batch) >= 200) {
                    fwrite($fh, 'INSERT INTO '.$q($t)." ($cols) VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, 'INSERT INTO '.$q($t)." ($cols) VALUES\n".implode(",\n", $batch).";\n");
            }
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        }
        fclose($fh);
    }
}

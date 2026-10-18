<?php

namespace App\Services\Import;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\ImportRun;
use App\Models\Kid;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\FinanceService;
use App\Services\Guardians\BlacklistService;
use App\Services\Guardians\GuardianService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Ma'lumotlarni CSV (UTF-8) dan import qilish: shablon → oldindan ko'rish (tekshiruv) → tasdiqlash. Faqat direktor. */
class ImportService
{
    public const MAX_ROWS = 2000;

    /** Ustun nomlari sinonimlari (UZ/RU/EN). Boshqa tizimdan olingan jadval ham shu nomlar bilan mos kelsa o'qiladi. */
    private const ALIASES = [
        'full_name' => ['bola ismi', 'ism', 'fio', 'fish', 'full_name', 'name', 'ф.и.о', 'фио', 'имя', 'ребенок', 'ребёнок', 'bola', 'child'],
        'gender' => ['jinsi', 'jins', 'gender', 'пол', 'sex'],
        'birth_date' => ['tugilgan sana', "tug'ilgan sana", 'tugilgan_sana', 'birth_date', 'birthday', 'дата рождения', 'туғилган сана', 'tug_sana'],
        'group' => ['guruh', 'group', 'группа', 'guruh nomi'],
        'parent_name' => ['ota-ona ismi', 'ota ona', 'ota-ona', 'vasiy', 'parent_name', 'parent', 'родитель', 'ота-она'],
        'parent_phone' => ['telefon', 'tel', 'phone', 'parent_phone', 'телефон', 'ota-ona telefoni', 'ota_ona_telefoni'],
        'parent_phone2' => ['telefon 2', 'telefon2', 'phone2', 'parent_phone2', 'qo\'shimcha telefon', 'телефон 2'],
        'g2_name' => ['2-vasiy ismi', 'vasiy2 ismi', 'vasiy2_ismi', 'guardian2_name', 'g2_name'],
        'g2_phone' => ['2-vasiy telefoni', 'vasiy2 telefoni', 'vasiy2_telefoni', 'guardian2_phone', 'g2_phone'],
        'balance' => ['balans', 'qoldiq', 'balance', 'баланс', 'boshlangich qoldiq', "boshlang'ich qoldiq"],
        'certificate_no' => ['guvohnoma', 'certificate_no', 'свидетельство', 'guvohnoma raqami'],
        'address' => ['manzil', 'address', 'адрес'],
        // xodimlar
        'role' => ['lavozim', 'role', 'должность', 'rol'],
        'position_note' => ['izoh', 'position_note', 'lavozim izohi'],
        'base_salary' => ['oylik', 'maosh', 'base_salary', 'salary', 'оклад', 'зарплата'],
        'hired_on' => ['ishga kirgan sana', 'hired_on', 'ishga_kirgan', 'дата приема'],
    ];

    public function template(string $type): string
    {
        $rows = $type === 'employees'
            ? [['Ism', 'Telefon', 'Lavozim', 'Oylik', 'Ishga kirgan sana'], ['Dilfuza Karimova', '90 123 45 67', 'Tarbiyachi', '3000000', '01.09.2025']]
            : [['Bola ismi', 'Jinsi', "Tug'ilgan sana", 'Guruh', 'Ota-ona ismi', 'Telefon', 'Telefon 2', '2-vasiy ismi', '2-vasiy telefoni', 'Boshlangich qoldiq', 'Guvohnoma', 'Manzil'],
                ['Ali Valiyev', "O'g'il", '15.03.2021', 'Quyosh', 'Vali Valiyev', '90 123 45 67', '', 'Nodira Valiyeva', '91 234 56 78', '-200000', '', 'Toshkent']];
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        rewind($out);

        return stream_get_contents($out);
    }

    /** @return array<int, array<string,string>> */
    public function read(UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
        }
        $lines = preg_split('/\r\n|\n|\r/', $raw);
        $first = $lines[0] ?? '';
        $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, $raw);
        rewind($fh);
        $head = fgetcsv($fh, 0, $delim);
        if (! $head) {
            return [];
        }
        $map = [];
        foreach ($head as $i => $h) {
            $n = mb_strtolower(trim((string) $h));
            $n = str_replace(['’', 'ʻ', 'ʼ', '`', '‘'], "'", $n);
            foreach (self::ALIASES as $key => $names) {
                if (in_array($n, array_map(fn ($x) => str_replace(['’', 'ʻ', 'ʼ', '`', '‘'], "'", $x), $names), true)) {
                    $map[$i] = $key;
                    break;
                }
            }
        }
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($map as $i => $key) {
                $row[$key] = trim((string) ($r[$i] ?? ''));
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) {
                throw new \DomainException('Bir martada '.self::MAX_ROWS.' tadan ko\'p qator import qilib bo\'lmaydi. Faylni bo\'laklarga bo\'ling.');
            }
        }
        fclose($fh);

        return $rows;
    }

    private function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.y'] as $f) {
            try {
                $d = Carbon::createFromFormat('!'.$f, $v);
                if ($d && $d->format($f) === $v) {
                    return $d->toDateString();
                }
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    private function gender(?string $v): ?string
    {
        $v = mb_strtolower(trim((string) $v));
        $v = str_replace(['’', 'ʻ', 'ʼ', '`', '‘'], "'", $v);
        if (in_array($v, ["o'g'il", 'ogil', 'ugil', "o'g`il", 'erkak', 'm', 'male', 'мальчик', 'м', 'уғил', "o'gil"], true)) {
            return 'male';
        }
        if (in_array($v, ['qiz', 'ayol', 'f', 'female', 'девочка', 'ж', 'қиз'], true)) {
            return 'female';
        }

        return null;
    }

    /** Oldindan ko'rish: har qator uchun ok / warn / skip va sabab. */
    public function preview(string $type, array $rows, User $by, string $source, ?string $filename): ImportRun
    {
        $items = $type === 'employees' ? $this->checkEmployees($rows) : $this->checkKids($rows);
        $summary = [
            'type' => $type, 'total' => count($items),
            'ok' => collect($items)->where('status', 'ok')->count(), 'warn' => collect($items)->where('status', 'warn')->count(), 'skip' => collect($items)->where('status', 'skip')->count(),
            'new_groups' => collect($items)->pluck('new_group')->filter()->unique()->values()->all(),
        ];

        return ImportRun::create([
            'user_id' => $by->id, 'source' => $source, 'filename' => $filename, 'status' => 'preview',
            'payload' => Crypt::encryptString(json_encode($items, JSON_UNESCAPED_UNICODE)), 'summary' => $summary,
        ]);
    }

    private function checkKids(array $rows): array
    {
        $blacklist = app(BlacklistService::class);
        $groups = Group::active()->main()->get()->keyBy(fn ($g) => mb_strtolower($g->name));
        $seen = [];
        $out = [];
        foreach ($rows as $i => $r) {
            $msgs = [];
            $status = 'ok';
            $name = trim($r['full_name'] ?? '');
            $phone = normalize_phone($r['parent_phone'] ?? '');
            $phone2 = ($r['parent_phone2'] ?? '') !== '' ? normalize_phone($r['parent_phone2']) : null;
            $g2phone = ($r['g2_phone'] ?? '') !== '' ? normalize_phone($r['g2_phone']) : null;
            $birth = $this->date($r['birth_date'] ?? '');
            $gender = $this->gender($r['gender'] ?? '');
            $groupName = trim($r['group'] ?? '');
            $bal = (int) preg_replace('/[^\d-]/', '', (string) ($r['balance'] ?? '0')) ?: 0;
            if (str_contains((string) ($r['balance'] ?? ''), '-') && $bal > 0) {
                $bal = -$bal;
            }

            if ($name === '') { $msgs[] = 'Ism yo\'q'; }
            if (! $phone) { $msgs[] = "Telefon noto'g'ri"; }
            if (! $birth) { $msgs[] = "Tug'ilgan sana noto'g'ri (kk.oo.yyyy)"; } elseif ($birth >= today()->toDateString()) { $msgs[] = "Tug'ilgan sana kelajakda"; }
            if (! $gender) { $msgs[] = 'Jinsi aniqlanmadi (o\'g\'il/qiz)'; }
            if ($groupName === '') { $msgs[] = 'Guruh yo\'q'; }
            if (($r['parent_phone2'] ?? '') !== '' && ! $phone2) { $msgs[] = '2-telefon noto\'g\'ri'; }
            if (($r['g2_phone'] ?? '') !== '' && ! $g2phone) { $msgs[] = '2-vasiy telefoni noto\'g\'ri'; }

            if (! $msgs) {
                $key = mb_strtolower($name).'|'.$phone;
                if (isset($seen[$key])) {
                    $msgs[] = 'Fayldа takror qator';
                }
                $seen[$key] = true;
            }
            if (! $msgs && Kid::whereRaw('lower(full_name) = ?', [mb_strtolower($name)])->where('parent_phone', $phone)->exists()) {
                $msgs[] = 'Bu bola allaqachon mavjud (ism va telefon bir xil)';
            }
            if ($msgs) {
                $status = 'skip';
            } else {
                if ($blacklist->check([$phone, $phone2, $g2phone], $r['certificate_no'] ?? null, 'family')->isNotEmpty()) {
                    $status = 'skip';
                    $msgs[] = "Qora ro'yxatda";
                } elseif (Kid::where('parent_phone', $phone)->exists()) {
                    $status = 'warn';
                    $msgs[] = 'Shu telefonli boshqa bola bor (aka-uka bo\'lishi mumkin)';
                }
            }
            $newGroup = ($status !== 'skip' && ! isset($groups[mb_strtolower($groupName)])) ? $groupName : null;
            if ($newGroup) {
                $msgs[] = 'Yangi guruh yaratiladi';
                $status = $status === 'ok' ? 'warn' : $status;
            }
            $out[] = [
                'line' => $i + 2, 'status' => $status, 'msgs' => $msgs, 'new_group' => $newGroup,
                'd' => ['full_name' => $name, 'gender' => $gender, 'birth_date' => $birth, 'group' => $groupName, 'parent_name' => $r['parent_name'] ?? '', 'parent_phone' => $phone, 'parent_phone2' => $phone2,
                    'g2_name' => $r['g2_name'] ?? '', 'g2_phone' => $g2phone, 'balance' => $bal, 'certificate_no' => $r['certificate_no'] ?? '', 'address' => $r['address'] ?? ''],
            ];
        }

        return $out;
    }

    private function checkEmployees(array $rows): array
    {
        $roles = Role::where('tenant_id', tenant()->id)->where('key', '!=', 'director')->get();
        $byName = $roles->keyBy(fn ($r) => mb_strtolower($r->name));
        $byKey = $roles->keyBy('key');
        $out = [];
        $seen = [];
        foreach ($rows as $i => $r) {
            $msgs = [];
            $name = trim($r['full_name'] ?? '');
            $phone = ($r['parent_phone'] ?? '') !== '' ? normalize_phone($r['parent_phone']) : null;
            $hired = $this->date($r['hired_on'] ?? '');
            $roleName = mb_strtolower(trim($r['role'] ?? ''));
            $role = $byName[$roleName] ?? $byKey[$roleName] ?? $byKey['staff'] ?? null;
            if ($name === '') { $msgs[] = "Ism yo'q"; }
            if (($r['parent_phone'] ?? '') !== '' && ! $phone) { $msgs[] = "Telefon noto'g'ri"; }
            if (! $role) { $msgs[] = 'Lavozim topilmadi'; }
            if ($phone && (isset($seen[$phone]) || Employee::where('phone', $phone)->exists())) { $msgs[] = 'Bu telefonli xodim mavjud'; }
            if ($phone) { $seen[$phone] = true; }
            $status = $msgs ? 'skip' : 'ok';
            if (! $msgs && $roleName !== '' && ! isset($byName[$roleName]) && ! isset($byKey[$roleName])) {
                $status = 'warn';
                $msgs[] = "Lavozim topilmadi — «Boshqa xodim» qo'yiladi";
            }
            $out[] = ['line' => $i + 2, 'status' => $status, 'msgs' => $msgs, 'new_group' => null, 'd' => [
                'full_name' => $name, 'phone' => $phone, 'role_id' => $role?->id, 'position_note' => $r['position_note'] ?? '',
                'base_salary' => (int) preg_replace('/\D/', '', (string) ($r['base_salary'] ?? '0')), 'hired_on' => $hired,
            ]];
        }

        return $out;
    }

    public function items(ImportRun $run): array
    {
        return $run->payload ? (json_decode(Crypt::decryptString($run->payload), true) ?: []) : [];
    }

    /** Tasdiqlash: yaroqli qatorlarni yozadi (hammasi bitta tranzaksiyada). */
    public function commit(ImportRun $run, User $by): array
    {
        abort_unless($run->status === 'preview', 409);
        $items = collect($this->items($run))->where('status', '!=', 'skip')->values();
        $type = $run->summary['type'] ?? 'kids';
        $made = 0;
        $groupsMade = 0;

        DB::transaction(function () use ($items, $type, $by, &$made, &$groupsMade) {
            if ($type === 'employees') {
                foreach ($items as $it) {
                    $d = $it['d'];
                    Employee::create(['tenant_id' => tenant()->id, 'full_name' => $d['full_name'], 'phone' => $d['phone'], 'role_id' => $d['role_id'], 'position_note' => $d['position_note'] ?: null,
                        'base_salary' => $d['base_salary'], 'hired_on' => $d['hired_on'], 'status' => 'active']);
                    $made++;
                }

                return;
            }
            $guardians = app(GuardianService::class);
            $finance = app(FinanceService::class);
            $groups = Group::active()->main()->get()->keyBy(fn ($g) => mb_strtolower($g->name));
            foreach ($items as $it) {
                $d = $it['d'];
                $key = mb_strtolower($d['group']);
                if (! isset($groups[$key])) {
                    $groups[$key] = Group::create(['tenant_id' => tenant()->id, 'name' => $d['group'], 'type' => 'main', 'monthly_fee' => 0, 'capacity' => 30, 'status' => 'active']);
                    $groupsMade++;
                }
                $kid = Kid::create([
                    'tenant_id' => tenant()->id, 'full_name' => $d['full_name'], 'gender' => $d['gender'], 'birth_date' => $d['birth_date'], 'certificate_no' => $d['certificate_no'] ?: null,
                    'parent_name' => $d['parent_name'] ?: null, 'parent_phone' => $d['parent_phone'], 'parent_phone2' => $d['parent_phone2'], 'address' => $d['address'] ?: null,
                    'status' => 'active', 'balance' => 0, 'enrolled_on' => today()->toDateString(),
                ]);
                GroupKid::create(['tenant_id' => $kid->tenant_id, 'group_id' => $groups[$key]->id, 'kid_id' => $kid->id, 'started_on' => today()->toDateString(), 'started_by' => $by->id]);
                $g1 = ['relation' => 'other', 'name' => $d['parent_name'] ?: null, 'phone' => $d['parent_phone'], 'phone2' => $d['parent_phone2'], 'is_payer' => true];
                $g2 = $d['g2_phone'] ? ['relation' => 'other', 'name' => $d['g2_name'] ?: null, 'phone' => $d['g2_phone'], 'is_payer' => false] : null;
                $guardians->sync($kid, $g1, $g2);
                if ((int) $d['balance'] !== 0) {
                    $finance->adjust($kid, (int) $d['balance'], "Boshlang'ich qoldiq (import)", $by);
                }
                $made++;
            }
        });

        $run->update(['status' => 'done', 'payload' => null, 'summary' => ($run->summary ?? []) + ['made' => $made, 'groups_made' => $groupsMade]]);
        AuditLog::record('import.done', null, ['type' => $type, 'made' => $made, 'source' => $run->source]);

        return ['made' => $made, 'groups_made' => $groupsMade];
    }
}

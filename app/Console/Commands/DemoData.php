<?php

namespace App\Console\Commands;

use App\Models\Edition;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\ChargeService;
use App\Services\Finance\FinanceService;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/** Mahalliy sinov uchun: namunaviy bog'cha (guruhlar, bolalar, to'lovlar, davomad). */
class DemoData extends Command
{
    protected $signature = 'kd:demo {--edition=premium}';

    protected $description = "Namunaviy bog'cha yaratadi: telefon 998900000001, parol Demo12345";

    public function handle(TenantProvisioner $prov, TenantContext $ctx, FinanceService $finance, ChargeService $charges): int
    {
        if (User::where('phone', '998900000001')->exists()) {
            $this->warn('Namunaviy bog\'cha allaqachon mavjud (998900000001).');

            return self::SUCCESS;
        }
        $tenant = $prov->register([
            'name' => "Quyoshli bog'cha (namuna)", 'owner_name' => 'Dilnoza Karimova', 'phone' => '998900000001', 'password' => 'Demo12345',
            'edition' => $this->option('edition'), 'region' => 'Toshkent', 'city' => 'Chilonzor',
        ]);

        $ctx->run($tenant, function () use ($tenant, $finance, $charges) {
            $owner = $tenant->owner;
            Auth::setUser($owner);
            $roles = \App\Models\Role::where('tenant_id', $tenant->id)->pluck('id', 'key');
            $tenant->setSetting('billing.newcomer_mode', 'prorated');

            $groups = collect([['Quyoshchalar', 650000, '3-4 yosh'], ['Kaptarlar', 700000, '4-5 yosh'], ['Yulduzlar', 750000, '5-6 yosh']])->map(
                fn ($g) => Group::create(['tenant_id' => $tenant->id, 'name' => $g[0], 'monthly_fee' => $g[1], 'age_range' => $g[2], 'capacity' => 15, 'status' => 'active'])
            );
            $teachers = collect(["Malika Yusupova", "Zarina Rahimova", "Nigora Abdullayeva"])->map(fn ($n, $i) => Employee::create([
                'tenant_id' => $tenant->id, 'full_name' => $n, 'phone' => '99890111000'.$i, 'role_id' => $roles['teacher'], 'hired_on' => today()->subYear(),
                'status' => 'active', 'base_salary' => 3000000 + $i * 200000, 'kpi_per_kid' => 50000, 'daily_rate' => 150000,
            ]));
            foreach ($groups as $i => $g) {
                GroupEmployee::create(['tenant_id' => $tenant->id, 'group_id' => $g->id, 'employee_id' => $teachers[$i]->id, 'started_on' => today()->subMonths(6)]);
            }
            Employee::create(['tenant_id' => $tenant->id, 'full_name' => 'Gulnora Tosheva', 'role_id' => $roles['cook'], 'status' => 'active', 'hired_on' => today()->subYear(), 'base_salary' => 2500000]);

            $names = ['Ali Valiyev', 'Madina Karimova', 'Sardor Aliyev', 'Laylo Hasanova', 'Jasur Ergashev', 'Nilufar Qodirova', 'Bobur Tursunov', 'Sevara Nazarova', 'Otabek Rahmonov', 'Dilshod Mirzayev', 'Zuhra Ismoilova', 'Aziz Karimov', 'Hilola Saidova', 'Umid Yoqubov', 'Shahnoza Ortiqova'];
            $kids = collect($names)->map(function ($n, $i) use ($tenant, $groups) {
                $k = Kid::create(['tenant_id' => $tenant->id, 'full_name' => $n, 'gender' => $i % 2 ? 'female' : 'male', 'birth_date' => today()->subYears(3 + $i % 3)->subDays($i * 37),
                    'parent_name' => 'Ota-ona '.explode(' ', $n)[1], 'parent_phone' => '99893'.str_pad((string) (1000000 + $i * 7919), 7, '0'), 'status' => 'active', 'balance' => 0, 'enrolled_on' => today()->subMonths(2)]);
                GroupKid::create(['tenant_id' => $tenant->id, 'group_id' => $groups[$i % 3]->id, 'kid_id' => $k->id, 'started_on' => today()->subMonths(2)]);

                return $k;
            });

            foreach ($kids as $i => $k) {
                $charges->chargeKid($k, today()->subMonth()->startOfMonth(), 'monthly_fixed');
                $charges->chargeKid($k, today()->startOfMonth(), 'prorated');
            }
            foreach ($kids as $i => $k) {
                if ($i % 4 !== 3) {
                    $finance->receivePayment($k->fresh(), 650000 + ($i % 3) * 50000, $i % 2 ? 'card' : 'cash', "Oylik to'lov", $tenant->owner);
                }
            }
            $finance->record('expense', 'cash', 120000, 'Xo\'jalik', 'Tozalash vositalari', $tenant->owner, null, null, null, 'desk');
            $h1 = $finance->requestHandover('cash', 1000000, 'Haftalik tushum', $tenant->owner);
            $finance->confirmHandover($h1, $tenant->owner);
            $finance->record('expense', 'cash', 450000, 'Oziq-ovqat', 'Haftalik bozor', $tenant->owner);

            foreach ($kids as $i => $k) {
                for ($d = 0; $d < 5; $d++) {
                    $date = today()->subDays($d);
                    if ($date->isWeekend()) {
                        continue;
                    }
                    KidAttendance::create(['tenant_id' => $tenant->id, 'group_id' => $groups[$i % 3]->id, 'kid_id' => $k->id, 'date' => $date, 'status' => ($i + $d) % 7 === 0 ? 'absent' : 'present']);
                }
            }
            // Kechki va shanba guruhlari
            $eve = Group::create(['tenant_id' => $tenant->id, 'name' => 'Kechki soatlar', 'type' => 'evening', 'daily_fee' => 25000, 'start_time' => '17:00', 'end_time' => '20:00', 'monthly_fee' => 0, 'capacity' => 10, 'status' => 'active']);
            $sat = Group::create(['tenant_id' => $tenant->id, 'name' => 'Shanba kunlari', 'type' => 'saturday', 'daily_fee' => 60000, 'start_time' => '09:00', 'end_time' => '14:00', 'monthly_fee' => 0, 'capacity' => 12, 'status' => 'active']);
            foreach ([0, 1, 2] as $i) {
                GroupKid::create(['tenant_id' => $tenant->id, 'group_id' => $eve->id, 'kid_id' => $kids[$i]->id, 'started_on' => today()->subMonth()]);
            }
            foreach ([3, 4] as $i) {
                GroupKid::create(['tenant_id' => $tenant->id, 'group_id' => $sat->id, 'kid_id' => $kids[$i]->id, 'started_on' => today()->subMonth()]);
            }
            foreach ([0, 1] as $i) {
                for ($d = 1; $d <= 3; $d++) {
                    $date = today()->subDays($d);
                    if ($date->isWeekend()) {
                        continue;
                    }
                    KidAttendance::create(['tenant_id' => $tenant->id, 'group_id' => $eve->id, 'kid_id' => $kids[$i]->id, 'date' => $date, 'status' => 'present']);
                    $charges->syncDaily($kids[$i], $eve, $date, 'present');
                }
            }
            // To'garak
            $club = \App\Models\Club::create(['tenant_id' => $tenant->id, 'name' => 'Ingliz tili', 'teacher_id' => $teachers[0]->id, 'fee' => 200000, 'billing' => 'monthly', 'schedule' => 'Du, Chor, Ju 15:00', 'status' => 'active']);
            foreach ([0, 2, 5] as $i) {
                \App\Models\ClubKid::create(['tenant_id' => $tenant->id, 'club_id' => $club->id, 'kid_id' => $kids[$i]->id, 'started_on' => today()->startOfMonth()]);
                $charges->chargeClubMonth($kids[$i], $club, today()->startOfMonth());
            }
            // Kassadan direktorga topshirish (kutilmoqda)
            $finance->requestHandover('cash', 300000, 'Kunlik tushum', $owner);

            Lead::create(['tenant_id' => $tenant->id, 'type' => 'kid', 'status' => 'Yangi', 'name' => 'Feruza Ahmedova', 'phone' => '998901234567', 'source' => 'Instagram', 'result' => 'open', 'data' => ['Bola' => 'Amir', 'Yoshi' => '4']]);
            Lead::create(['tenant_id' => $tenant->id, 'type' => 'kid', 'status' => 'Aloqa qilindi', 'name' => 'Rustam Orifov', 'phone' => '998909876543', 'source' => 'Telegram', 'result' => 'open']);
        });

        $this->info("Tayyor! Kirish: 90 000 00 01 / Demo12345");

        return self::SUCCESS;
    }
}

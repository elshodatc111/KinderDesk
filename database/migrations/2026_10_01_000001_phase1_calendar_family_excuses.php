<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 1-bosqich: ish kalendari, oila bog'lamasi, sababli kelmagan chegirmasi, xodim davomatida sabab. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 10); // working | off | holiday
            $table->string('title')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'date']);
        });

        Schema::create('calendar_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // oyning birinchi kuni
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'month']);
        });

        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from'); // chegirma shu sanadan (keyingi oyning 1-kuni) kuchga kiradi
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('kids', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id')->nullable()->after('tenant_id')->index();
        });

        Schema::create('kid_excuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $table->date('period'); // oyning birinchi kuni
            $table->unsignedSmallInteger('days');
            $table->string('reason');
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->bigInteger('amount')->default(0); // tasdiqlanganda yoziladi
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('employee_attendance', function (Blueprint $table) {
            $table->string('excuse', 12)->nullable()->after('note'); // excused | unexcused (bo'sh = hali belgilanmagan)
            $table->unsignedBigInteger('excuse_by')->nullable()->after('excuse');
        });

        // Mavjud administrator lavozimlariga yangi ruxsatlarni qo'shamiz
        $add = ['calendar.view', 'family.manage', 'discount.enter', 'attendance.excuse'];
        DB::table('roles')->where('key', 'admin')->get()->each(function ($r) use ($add) {
            $perms = json_decode($r->permissions ?? '[]', true) ?: [];
            if (! in_array('*', $perms, true)) {
                DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($perms, $add))))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendance', fn (Blueprint $t) => $t->dropColumn(['excuse', 'excuse_by']));
        Schema::table('kids', fn (Blueprint $t) => $t->dropColumn('family_id'));
        foreach (['kid_excuses', 'families', 'calendar_months', 'calendar_days'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

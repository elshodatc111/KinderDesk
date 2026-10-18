<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 2-bosqich: guruh o'lchami, guruhdagi vazifa, ish haqi sxemalari, almashtirish, payslip satrlari, shikoyatlar. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $t) {
            $t->string('size', 8)->default('large')->after('type'); // large | small (katta / kichik guruh)
        });

        Schema::table('group_employee', function (Blueprint $t) {
            $t->string('role_in_group', 12)->default('teacher')->after('employee_id'); // teacher | assistant
        });

        Schema::table('employees', function (Blueprint $t) {
            $t->string('scheme_key', 30)->nullable()->after('salary_type'); // bo'sh = avtomatik
        });

        Schema::create('pay_schemes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key', 30);
            $t->json('params');
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
        });

        Schema::create('substitutions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $t->foreignId('absent_employee_id')->constrained('employees')->cascadeOnDelete();
            $t->unsignedBigInteger('substitute_employee_id')->nullable(); // bo'sh = o'rindosh yo'q, shu kun hech kimga yozilmaydi
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['group_id', 'date', 'absent_employee_id']);
            $t->index(['tenant_id', 'date']);
        });

        Schema::create('payslip_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('period'); // oyning birinchi kuni
            $t->string('type', 16); // premium | event_bonus | fine | deduction | advance
            $t->unsignedBigInteger('amount');
            $t->string('reason')->nullable();
            $t->string('source', 30)->nullable(); // manual | bulk | auto
            $t->unsignedBigInteger('finance_transaction_id')->nullable(); // avans uchun xarajat yozuvi
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'employee_id', 'period']);
        });

        Schema::table('payslips', function (Blueprint $t) {
            $t->unsignedBigInteger('advance')->default(0)->after('penalty');
            $t->json('detail')->nullable()->after('note');
            $t->timestamp('approved_at')->nullable()->after('detail');
            $t->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
        });

        Schema::create('admin_bonus_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $t->date('period'); // qaysi oyda hisoblangan
            $t->unsignedBigInteger('amount');
            $t->timestamps();
            $t->unique(['tenant_id', 'kid_id']); // har bola uchun faqat bir marta
        });

        Schema::create('complaints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedBigInteger('employee_id')->nullable()->index();
            $t->unsignedBigInteger('group_id')->nullable()->index();
            $t->string('note', 300);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'date']);
        });

        $add = ['substitution.manage', 'payroll.items'];
        DB::table('roles')->where('key', 'admin')->get()->each(function ($r) use ($add) {
            $perms = json_decode($r->permissions ?? '[]', true) ?: [];
            if (! in_array('*', $perms, true)) {
                DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($perms, ['substitution.manage']))))]);
            }
        });
    }

    public function down(): void
    {
        foreach (['complaints', 'admin_bonus_events', 'payslip_items', 'substitutions', 'pay_schemes'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('payslips', fn (Blueprint $t) => $t->dropColumn(['advance', 'detail', 'approved_at', 'approved_by']));
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('scheme_key'));
        Schema::table('group_employee', fn (Blueprint $t) => $t->dropColumn('role_in_group'));
        Schema::table('groups', fn (Blueprint $t) => $t->dropColumn('size'));
    }
};

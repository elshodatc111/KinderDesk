<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('monthly_fee')->default(0);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('age_range', 40)->nullable();
            $table->string('room', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | archived
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('kids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('gender', 10)->default('male'); // male | female
            $table->date('birth_date');
            $table->string('certificate_no', 30)->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone', 20);
            $table->string('parent_phone2', 20)->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('custom_fee')->nullable(); // guruh narxidan farqli shaxsiy narx
            $table->bigInteger('balance')->default(0); // kesh: + haq, - qarz
            $table->string('status', 20)->default('active'); // active | archived
            $table->date('enrolled_on')->nullable();
            $table->date('archived_on')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'parent_phone']);
            $table->unique(['tenant_id', 'certificate_no']);
        });

        Schema::create('group_kid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'kid_id', 'ended_on']);
            $table->index(['tenant_id', 'group_id', 'ended_on']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('full_name');
            $table->string('phone', 20)->nullable();
            $table->string('position', 30)->default('other');
            $table->string('position_note')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('passport', 20)->nullable();
            $table->string('address')->nullable();
            $table->date('hired_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('status', 20)->default('active'); // active | archived
            $table->string('salary_type', 20)->nullable(); // fixed | attendance | hybrid (bo'sh = bog'cha sozlamasi)
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('daily_rate')->default(0);
            $table->unsignedBigInteger('kpi_per_kid')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('group_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'ended_on']);
            $table->index(['tenant_id', 'group_id', 'ended_on']);
        });

        Schema::create('kid_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 12)->default('present'); // present | absent | sick
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();
            $table->unique(['kid_id', 'date']);
            $table->index(['tenant_id', 'group_id', 'date']);
        });

        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 12)->default('present'); // present | late | absent | leave
            $table->time('check_in')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // kid | employee
            $table->string('status', 60); // sozlamadagi bosqich nomi
            $table->string('name');
            $table->string('phone', 20);
            $table->string('phone2', 20)->nullable();
            $table->string('source', 60)->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('note')->nullable();
            $table->string('result', 12)->default('open'); // open | won | lost
            $table->string('lost_reason')->nullable();
            $table->unsignedBigInteger('converted_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'type', 'result']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('noteable');
            $table->text('body');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        // Pul harakati jurnali: musbat — hisobga kirim, manfiy — chiqim
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('account', 10); // cash | card | bank
            $table->string('type', 20); // kid_payment | kid_refund | income | expense | salary | transfer
            $table->bigInteger('amount');
            $table->string('status', 12)->default('confirmed'); // pending | confirmed | canceled
            $table->string('category', 80)->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('kid_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('payslip_id')->nullable();
            $table->uuid('transfer_group')->nullable();
            $table->date('occurred_on');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->unsignedBigInteger('canceled_by')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'account', 'status']);
            $table->index(['tenant_id', 'occurred_on']);
        });

        // Bola hisobi jurnali: musbat — bola foydasiga, manfiy — qarz
        Schema::create('kid_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $table->string('type', 12); // charge | payment | discount | refund | adjustment
            $table->bigInteger('amount');
            $table->date('period')->nullable(); // oyning birinchi kuni
            $table->string('charge_key', 60)->nullable(); // takrorlanishdan himoya: "2026-10:g5"
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('finance_transaction_id')->nullable();
            $table->string('status', 12)->default('posted'); // posted | pending | canceled
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['kid_id', 'charge_key']);
            $table->index(['tenant_id', 'kid_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('period');
            $table->string('strategy', 20);
            $table->unsignedBigInteger('base_amount')->default(0);
            $table->unsignedSmallInteger('worked_days')->default(0);
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('kids_count')->default(0);
            $table->unsignedBigInteger('kpi_amount')->default(0);
            $table->unsignedBigInteger('bonus')->default(0);
            $table->unsignedBigInteger('penalty')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid')->default(0);
            $table->string('status', 12)->default('draft'); // draft | approved | paid
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['payslips', 'kid_ledger', 'finance_transactions', 'notes', 'leads', 'employee_attendance', 'kid_attendance', 'group_employee', 'employees', 'group_kid', 'kids', 'groups'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

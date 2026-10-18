<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guruh turlari: main (09:00-17:00, oylik), saturday (shanba, kunlik), evening (17:00-20:00, kunlik)
        Schema::table('groups', function (Blueprint $table) {
            $table->string('type', 12)->default('main')->after('name');
            $table->unsignedBigInteger('daily_fee')->default(0)->after('monthly_fee');
            $table->time('start_time')->nullable()->after('daily_fee');
            $table->time('end_time')->nullable()->after('start_time');
        });

        // Bola bir kunda bir nechta guruhda (asosiy + kechki) belgilanishi mumkin
        Schema::table('kid_attendance', function (Blueprint $table) {
            $table->unique(['kid_id', 'group_id', 'date']);
        });
        Schema::table('kid_attendance', function (Blueprint $table) {
            $table->dropUnique(['kid_id', 'date']);
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('teacher_id')->nullable()->index();
            $table->unsignedBigInteger('fee')->default(0);
            $table->string('billing', 12)->default('monthly'); // monthly | per_session
            $table->string('schedule', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('club_kid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'club_id', 'ended_on']);
            $table->index(['tenant_id', 'kid_id', 'ended_on']);
        });

        Schema::create('club_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 12)->default('present');
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'kid_id', 'date']);
        });

        Schema::table('kid_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('club_id')->nullable()->after('group_id');
        });

        // Kassa (bog'cha) va Moliya (direktor) hamyonlari
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('wallet', 8)->default('main')->after('tenant_id'); // desk | main
            $table->index(['tenant_id', 'wallet', 'account', 'status']);
        });
        DB::table('finance_transactions')->whereIn('type', ['kid_payment', 'kid_refund'])->update(['wallet' => 'desk']);

        Schema::create('desk_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('account', 10);
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->string('status', 12)->default('pending'); // pending | confirmed | rejected
            $table->string('reject_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        // Xodim lavozimi — direktor yaratgan rol
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('role_id'));
        Schema::dropIfExists('desk_handovers');
        Schema::table('finance_transactions', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'wallet', 'account', 'status']);
            $t->dropColumn('wallet');
        });
        Schema::table('kid_ledger', fn (Blueprint $t) => $t->dropColumn('club_id'));
        Schema::dropIfExists('club_attendance');
        Schema::dropIfExists('club_kid');
        Schema::dropIfExists('clubs');
        Schema::table('kid_attendance', function (Blueprint $t) {
            $t->unique(['kid_id', 'date']);
        });
        Schema::table('kid_attendance', fn (Blueprint $t) => $t->dropUnique(['kid_id', 'group_id', 'date']));
        Schema::table('groups', fn (Blueprint $t) => $t->dropColumn(['type', 'daily_fee', 'start_time', 'end_time']));
    }
};

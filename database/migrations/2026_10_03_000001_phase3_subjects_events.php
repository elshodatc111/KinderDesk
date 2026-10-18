<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('kind', 10)->default('lesson'); // club (pullik) | lesson (bog'cha ichidagi dars)
            $t->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('pay_mode', 12)->default('per_lesson'); // per_lesson | fixed
            $t->unsignedInteger('lesson_rate')->default(0);
            $t->unsignedInteger('fixed_salary')->default(0);
            $t->string('status', 10)->default('active');
            $t->timestamps();
        });

        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('date');
            $t->boolean('held')->default(true);
            $t->string('note', 200)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'date']);
        });

        Schema::create('lesson_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $t->unique(['lesson_id', 'group_id']);
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('title', 150);
            $t->date('date');
            $t->date('deadline')->nullable();
            $t->string('status', 12)->default('draft'); // draft|pending|registration|locked|held|closed|cancelled
            $t->text('note')->nullable();
            $t->unsignedBigInteger('approved_total')->nullable();
            $t->unsignedInteger('price')->nullable();
            $t->boolean('reapproval')->default(false);
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'date']);
        });

        Schema::create('event_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->string('kind', 12)->default('other'); // transport|ticket|food|other
            $t->string('title', 120);
            $t->unsignedBigInteger('planned')->default(0);
            $t->unsignedBigInteger('actual')->nullable();
            $t->timestamps();
        });

        Schema::create('event_kids', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('kid_id')->constrained('kids')->cascadeOnDelete();
            $t->string('attended', 8)->nullable(); // present | absent
            $t->boolean('charged')->default(false);
            $t->timestamps();
            $t->unique(['event_id', 'kid_id']);
        });

        Schema::create('event_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->unsignedInteger('pay')->default(0);
            $t->timestamps();
            $t->unique(['event_id', 'employee_id']);
        });

        Schema::create('group_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $t->date('date');
            $t->string('kind', 20)->default('matinee'); // matinee | excursion | contest | other
            $t->string('title', 150);
            $t->string('status', 10)->default('pending'); // pending | approved | rejected
            $t->foreignId('created_by')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'date']);
        });

        Schema::create('group_event_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_event_id')->constrained('group_events')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->unique(['group_event_id', 'employee_id']);
        });

        // Mavjud bog'chalarda yangi ruxsatlar va «Pedagog» lavozimi
        $perms = ['subjects.manage', 'events.manage'];
        DB::table('roles')->where('key', 'admin')->get()->each(function ($r) use ($perms) {
            $cur = json_decode($r->permissions, true) ?: [];
            if (! in_array('*', $cur, true)) {
                DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($cur, $perms))))]);
            }
        });
        DB::table('tenants')->pluck('id')->each(function ($tid) {
            if (! DB::table('roles')->where('tenant_id', $tid)->where('key', 'pedagog')->exists()) {
                DB::table('roles')->insert(['tenant_id' => $tid, 'key' => 'pedagog', 'name' => 'Pedagog', 'permissions' => json_encode(['kids.view', 'groups.view', 'clubs.view']), 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        foreach (['group_event_staff', 'group_events', 'event_staff', 'event_kids', 'event_costs', 'events', 'lesson_groups', 'lessons', 'subjects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

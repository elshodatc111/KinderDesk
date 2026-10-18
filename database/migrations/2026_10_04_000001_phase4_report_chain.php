<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('role_key', 40);                       // kim topshiradi (lavozim kaliti)
            $t->unsignedTinyInteger('level')->default(1);     // 1: xodim → administrator, 2: administrator → direktor
            $t->string('frequency', 10)->default('daily');    // daily | weekly | monthly
            $t->string('deadline_time', 5)->default('23:59');
            $t->string('content', 10)->default('text');       // text | file | media | auto
            $t->boolean('is_parent')->default(false);          // ota-onaga hisobot (KPI sharti)
            $t->boolean('affects_bonus')->default(true);
            $t->boolean('active')->default(true);
            $t->text('hint')->nullable();
            $t->timestamps();
        });

        Schema::create('report_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('type_id')->constrained('report_types')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable();
            $t->date('date');                                   // kunlik: kun, haftalik: dushanba, oylik: 1-sana
            $t->string('status', 10)->default('submitted');     // submitted | accepted | returned
            $t->boolean('late')->default(false);
            $t->text('body')->nullable();
            $t->json('files')->nullable();
            $t->json('meta')->nullable();                       // 2-daraja: 1-daraja natijalari
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->unique(['type_id', 'employee_id', 'date']);
            $t->index(['tenant_id', 'date']);
        });

        Schema::create('report_waivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('period');                                 // oyning 1-sanasi
            $t->date('date')->nullable();                       // bo'sh bo'lsa — butun oy
            $t->string('reason', 300);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'employee_id', 'period']);
        });

        $perms = ['report.submit', 'report.review'];
        DB::table('roles')->get()->each(function ($r) use ($perms) {
            $cur = json_decode($r->permissions, true) ?: [];
            if (in_array('*', $cur, true)) {
                return;
            }
            $add = match ($r->key) {
                'admin' => $perms,
                'teacher', 'assistant', 'cook', 'pedagog', 'nurse' => ['report.submit'],
                default => [],
            };
            if ($add) {
                DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($cur, $add))))]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_waivers');
        Schema::dropIfExists('report_submissions');
        Schema::dropIfExists('report_types');
    }
};

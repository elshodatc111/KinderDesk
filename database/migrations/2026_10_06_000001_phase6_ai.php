<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('lang', 5)->default('uz');
            $t->text('question');
            $t->text('answer')->nullable();
            $t->boolean('masked')->default(false);
            $t->smallInteger('rating')->nullable();           // 1 foydali, -1 foydasiz
            $t->timestamps();
            $t->index(['tenant_id', 'user_id', 'created_at']);
        });

        Schema::create('ai_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('date');                                  // hisobot sanasi (kun yoki hafta oxiri)
            $t->string('period', 8)->default('daily');         // daily | weekly
            $t->string('status', 12)->default('ready');        // ready | unreliable | failed
            $t->string('model', 60)->nullable();
            $t->json('facts')->nullable();
            $t->json('result')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('error', 300)->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'date', 'period']);
        });

        Schema::create('ai_usage', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('kind', 10);                            // chat | report | test
            $t->string('model', 60)->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('cache_tokens')->default(0);
            $t->unsignedBigInteger('cost_micro')->default(0);  // mikro-dollar (1e-6 USD)
            $t->string('status', 10)->default('ok');           // ok | error | blocked
            $t->longText('payload')->nullable();               // chiqish paketi (tekshirish uchun jurnal)
            $t->timestamp('created_at')->nullable()->index();
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::table('tenants', function (Blueprint $t) {
            $t->boolean('ai_disabled')->default(false);         // super admin majburan o'chirgan
            $t->unsignedBigInteger('ai_token_limit')->nullable(); // oylik limit (bo'sh — standart)
            $t->string('ai_warned_month', 7)->nullable();
        });

        DB::table('roles')->get()->each(function ($r) {
            $cur = json_decode($r->permissions, true) ?: [];
            if (in_array('*', $cur, true)) {
                return;
            }
            if (in_array($r->key, ['admin', 'accountant', 'cashier', 'teacher', 'assistant', 'pedagog'], true)) {
                DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($cur, ['ai.chat']))))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['ai_disabled', 'ai_token_limit', 'ai_warned_month']));
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_reports');
        Schema::dropIfExists('ai_chats');
    }
};

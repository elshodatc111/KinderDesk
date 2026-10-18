<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sms_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->text('password'); // shifrlangan
            $table->string('sender', 30)->nullable();
            $table->text('token')->nullable(); // shifrlangan
            $table->dateTime('token_expires_at')->nullable();
            $table->bigInteger('balance')->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // tenant_id = null bo'lsa — platforma shabloni (OTP va ogohlantirishlar)
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_key', 40);
            $table->string('name');
            $table->text('body');
            $table->string('status', 20)->default('draft'); // draft | pending | approved | rejected
            $table->string('eskiz_template_id', 40)->nullable();
            $table->string('rejection_reason')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'event_key']);
        });

        Schema::create('sms_event_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 40);
            $table->boolean('enabled')->default(false);
            $table->foreignId('template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'event_key']);
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('to', 20);
            $table->string('event_key', 40)->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('queued'); // queued | sent | delivered | failed | blocked
            $table->string('provider_id', 60)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['sms_logs', 'sms_event_rules', 'sms_templates', 'tenant_sms_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('edition_id')->constrained('editions')->restrictOnDelete();
            $table->unsignedTinyInteger('months');
            $table->unsignedBigInteger('price')->default(0);
            $table->date('starts_on');
            $table->date('ends_on'); // shu kunning oxirigacha amal qiladi (kiritilgan)
            $table->string('status', 20)->default('active'); // active | expired | canceled
            $table->string('source', 20)->default('balance'); // balance | auto_renew | admin_grant
            $table->dateTime('reminded_at')->nullable();
            $table->dateTime('expired_notice_at')->nullable();
            $table->unsignedBigInteger('renewed_from_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'ends_on']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // topup | subscription | refund | adjustment
            $table->bigInteger('amount'); // + kirim, - chiqim
            $table->bigInteger('balance_after');
            $table->string('description');
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20); // manual | payme
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | canceled
            $table->string('receipt_path')->nullable();
            $table->string('receipt_hash', 64)->nullable()->index();
            $table->date('paid_on')->nullable();
            $table->string('sender_card', 30)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('payme_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('payme_id', 40)->unique();
            $table->foreignId('topup_id')->constrained('topups')->cascadeOnDelete();
            $table->unsignedBigInteger('time'); // Payme vaqti (ms)
            $table->unsignedBigInteger('amount'); // tiyin
            $table->tinyInteger('state'); // 1, 2, -1, -2
            $table->tinyInteger('reason')->nullable();
            $table->unsignedBigInteger('create_time');
            $table->unsignedBigInteger('perform_time')->default(0);
            $table->unsignedBigInteger('cancel_time')->default(0);
            $table->timestamps();
            $table->index(['topup_id', 'state']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('trial_registry', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12); // phone | email | tax_id
            $table->string('hash', 64);
            $table->timestamp('created_at')->nullable();
            $table->unique(['kind', 'hash']);
        });

        Schema::create('tenant_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_tenant_id');
            $table->string('name');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->json('stats')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('purpose', 20); // register | login | reset
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'otp_codes', 'audit_logs', 'tenant_deletions', 'trial_registry', 'platform_settings', 'payme_transactions', 'topups', 'wallet_transactions', 'subscriptions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_key')->unique();
            $table->string('name');
            $table->foreignId('edition_id')->constrained('editions')->restrictOnDelete();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->dateTime('trial_started_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            // Obuna yoki sinov muddati tugaydigan lahza (kesh): max(trial_ends_at, aktiv obuna tugashi)
            $table->dateTime('access_expires_at')->nullable()->index();
            $table->bigInteger('balance')->default(0);
            $table->boolean('auto_renew')->default(true); // muddat tugaganda balansdan avtomatik yechish
            $table->dateTime('reminded_for')->nullable(); // qaysi tugash sanasi uchun 3 kunlik eslatma yuborilgan
            $table->dateTime('expired_notified_for')->nullable(); // qaysi tugash sanasi uchun "tugadi" xabari yuborilgan
            $table->dateTime('deletion_notice_sent_at')->nullable();
            $table->dateTime('last_active_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_key']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name');
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
    }
};

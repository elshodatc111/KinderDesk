<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sog'liq va xavfsizlik: barcha maydonlar shifrlangan (modelda 'encrypted')
        Schema::create('kid_health', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('kid_id')->constrained()->cascadeOnDelete();
            $t->text('allergies')->nullable();
            $t->text('conditions')->nullable();
            $t->text('medications')->nullable();
            $t->text('notes')->nullable();
            $t->text('doctor_name')->nullable();
            $t->text('doctor_phone')->nullable();
            $t->boolean('has_alert')->default(false); // allergiya/kasallik bor (qidiruv va nishon uchun; mazmuni shifrlangan)
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->unique('kid_id');
        });

        // Bolani olib ketishga ruxsat etilgan shaxslar (pasport saqlanmaydi)
        Schema::create('kid_pickups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('kid_id')->constrained()->cascadeOnDelete();
            $t->text('name');
            $t->string('relation', 60)->nullable();
            $t->text('phone')->nullable();
            $t->timestamps();
            $t->index('kid_id');
        });

        // Zaxira nusxa vazifalari (tashqi zaxira tizimi uchun API)
        Schema::create('backup_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uid', 40)->unique();
            $t->string('status', 10)->default('pending'); // pending|running|ready|failed|expired
            $t->date('for_date')->index();
            $t->string('path', 255)->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->string('error', 300)->nullable();
            $t->string('requested_ip', 45)->nullable();
            $t->unsignedInteger('downloads')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        // Ma'lumot import: oldindan ko'rish (preview) → tasdiqlash
        Schema::create('import_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable();
            $t->string('source', 20);                  // template|smartkids
            $t->string('filename', 200)->nullable();
            $t->string('status', 10)->default('preview'); // preview|done
            $t->longText('payload')->nullable();       // shifrlangan JSON; tasdiqdan keyin o'chiriladi
            $t->json('summary')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('backup_jobs');
        Schema::dropIfExists('kid_pickups');
        Schema::dropIfExists('kid_health');
    }
};

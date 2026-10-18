<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Oferta / maxfiylik / shartlarga rozilik jurnali (o'zgartirilmaydi, faqat qo'shiladi)
        Schema::create('consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('tenant_id')->nullable()->index();
            $t->unsignedInteger('version');
            $t->string('docs_hash', 64); // rozilik vaqtidagi matnlar xeshi (dalil)
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('accepted_at');
            $t->index(['user_id', 'version']);
        });

        // YouTube video yo'riqnomalar (super admin boshqaradi)
        Schema::create('guide_videos', function (Blueprint $t) {
            $t->id();
            $t->string('title_uz', 200);
            $t->string('title_ru', 200)->nullable();
            $t->string('youtube_id', 20);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // «Bu qurilmani 30 kun eslab qol»
        Schema::create('trusted_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->index();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });

        // Telegram orqali ikki bosqichli kirish (direktor va super admin)
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('tg_chat_id')->nullable()->unique();
            $t->string('tg_username', 64)->nullable();
            $t->timestamp('tg_linked_at')->nullable();
            $t->string('tg_link_hash', 64)->nullable()->index();
            $t->timestamp('tg_link_expires')->nullable();
            $t->text('tg_recovery')->nullable(); // JSON: heshlangan zaxira kodlar
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['tg_chat_id', 'tg_username', 'tg_linked_at', 'tg_link_hash', 'tg_link_expires', 'tg_recovery']));
        Schema::dropIfExists('guide_videos');
        Schema::dropIfExists('consents');
    }
};

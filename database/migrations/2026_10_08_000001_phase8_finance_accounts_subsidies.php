<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Direktorning o'z hisob raqamlari (karta / bank). Pul harakati turi bo'yicha (cash/card/bank) yuritiladi,
        // bu yerda esa ota-onalarga ko'rsatiladigan rekvizitlar saqlanadi.
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // card | bank
            $table->string('title', 80);
            $table->string('number', 40);
            $table->string('holder', 120)->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        // Subsidiya: bog'chaga davlat/tashkilot tomonidan beriladigan mablag' (alohida hisoblanadi)
        Schema::create('subsidies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('source', 120)->nullable();
            $table->date('period'); // oy boshi
            $table->unsignedInteger('kids_count')->nullable();
            $table->unsignedBigInteger('rate')->nullable(); // bola boshiga
            $table->unsignedBigInteger('expected')->default(0);
            $table->unsignedBigInteger('received')->default(0);
            $table->string('account', 10)->nullable();
            $table->date('received_on')->nullable();
            $table->string('status', 12)->default('expected'); // expected | received | canceled
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidies');
        Schema::dropIfExists('finance_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hash(?string $phone): ?string
    {
        return $phone ? hash_hmac('sha256', preg_replace('/\D/', '', $phone), (string) config('app.key')) : null;
    }

    public function up(): void
    {
        Schema::create('kid_guardians', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('kid_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('position')->default(1);
            $t->string('relation', 20)->default('other'); // father|mother|other
            $t->text('name')->nullable();      // shifrlangan
            $t->text('phone')->nullable();     // shifrlangan
            $t->string('phone_hash', 64)->nullable()->index();
            $t->text('phone2')->nullable();    // shifrlangan
            $t->string('phone2_hash', 64)->nullable()->index();
            $t->boolean('is_payer')->default(false);
            $t->boolean('sms')->default(true);
            $t->boolean('pickup')->default(true);
            $t->timestamps();
            $t->unique(['kid_id', 'position']);
        });

        Schema::create('blacklist_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10)->default('family'); // family|staff
            $t->text('name');                           // shifrlangan
            $t->text('phones')->nullable();             // shifrlangan JSON (ko'rsatish uchun)
            $t->json('phone_hashes')->nullable();
            $t->string('cert_hash', 64)->nullable()->index();
            $t->string('reason', 300);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });

        Schema::table('leads', function (Blueprint $t) {
            $t->date('next_call_on')->nullable();
            $t->timestamp('stage_changed_at')->nullable();
        });

        // Mavjud bolalarning ota-ona maydonlarini vasiy sifatida ko'chirish
        DB::table('kids')->orderBy('id')->chunkById(200, function ($kids) {
            foreach ($kids as $k) {
                if (! $k->parent_phone && ! $k->parent_name) {
                    continue;
                }
                DB::table('kid_guardians')->insertOrIgnore([
                    'tenant_id' => $k->tenant_id, 'kid_id' => $k->id, 'position' => 1, 'relation' => 'other',
                    'name' => $k->parent_name ? Crypt::encryptString($k->parent_name) : null,
                    'phone' => $k->parent_phone ? Crypt::encryptString($k->parent_phone) : null, 'phone_hash' => $this->hash($k->parent_phone),
                    'phone2' => $k->parent_phone2 ? Crypt::encryptString($k->parent_phone2) : null, 'phone2_hash' => $this->hash($k->parent_phone2),
                    'is_payer' => true, 'sms' => true, 'pickup' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        // Yangi ruxsatlar mavjud lavozimlarga
        foreach (DB::table('roles')->whereIn('key', ['admin'])->get() as $r) {
            $perms = array_values(array_unique(array_merge(json_decode($r->permissions, true) ?: [], ['guardians.manage', 'blacklist.view', 'blacklist.manage'])));
            DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['next_call_on', 'stage_changed_at']));
        Schema::dropIfExists('blacklist_entries');
        Schema::dropIfExists('kid_guardians');
    }
};

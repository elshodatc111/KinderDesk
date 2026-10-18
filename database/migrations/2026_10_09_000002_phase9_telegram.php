<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_bots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('is_platform')->default(false);
            $t->text('token')->nullable();              // shifrlangan
            $t->unsignedBigInteger('bot_id')->nullable();
            $t->string('username', 80)->nullable();
            $t->string('name', 120)->nullable();
            $t->string('description', 600)->nullable();
            $t->string('short_description', 140)->nullable();
            $t->boolean('protect_content')->default(false);
            $t->json('events')->nullable();
            $t->json('templates')->nullable();
            $t->string('secret', 64);
            $t->unsignedBigInteger('last_update_id')->default(0);
            $t->string('status', 12)->default('new');   // new|ok|error
            $t->string('last_error', 300)->nullable();
            $t->timestamp('last_ok_at')->nullable();
            $t->timestamps();
            $t->unique('tenant_id');
        });

        Schema::create('telegram_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $t->string('type', 10);                     // guardian|employee
            $t->unsignedBigInteger('kid_id')->nullable()->index();
            $t->unsignedTinyInteger('guardian_position')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable()->index();
            $t->bigInteger('chat_id')->index();
            $t->bigInteger('tg_user_id')->nullable();
            $t->string('username', 80)->nullable();
            $t->string('first_name', 120)->nullable();
            $t->boolean('consented')->default(false);
            $t->timestamp('consented_at')->nullable();
            $t->boolean('blocked')->default(false);
            $t->timestamps();
        });

        Schema::create('tg_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10);                     // report|meal|announce
            $t->string('state', 10)->default('draft');  // draft|sent|deleted
            $t->unsignedBigInteger('group_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->text('text')->nullable();               // veb e'lon matni / qisqa tavsif
            $t->bigInteger('source_chat_id')->nullable();
            $t->json('source_message_ids')->nullable();
            $t->string('media_group_id', 64)->nullable();
            $t->unsignedInteger('recipients')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'state']);
        });

        Schema::create('tg_outbox', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $t->unsignedBigInteger('post_id')->nullable()->index();
            $t->unsignedBigInteger('contact_id')->nullable();
            $t->bigInteger('chat_id');
            $t->string('method', 30);
            $t->json('payload');
            $t->string('status', 10)->default('pending'); // pending|sent|failed
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->json('message_ids')->nullable();
            $t->string('error', 300)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'available_at']);
        });

        Schema::table('kid_guardians', fn (Blueprint $t) => $t->string('tg_token', 40)->nullable()->index());
        Schema::table('employees', fn (Blueprint $t) => $t->string('tg_token', 40)->nullable()->index());

        // Mavjud lavozimlarga yangi Telegram ruxsatlari
        $add = [
            'admin' => ['tg.contacts', 'tg.broadcast'], 'teacher' => ['tg.contacts', 'tg.report'],
            'assistant' => ['tg.contacts', 'tg.report'], 'pedagog' => ['tg.report'], 'cook' => ['tg.meal'],
        ];
        foreach (DB::table('roles')->whereIn('key', array_keys($add))->get() as $r) {
            $perms = array_values(array_unique(array_merge(json_decode($r->permissions, true) ?: [], $add[$r->key])));
            DB::table('roles')->where('id', $r->id)->update(['permissions' => json_encode($perms)]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('tg_token'));
        Schema::table('kid_guardians', fn (Blueprint $t) => $t->dropColumn('tg_token'));
        Schema::dropIfExists('tg_outbox');
        Schema::dropIfExists('tg_posts');
        Schema::dropIfExists('telegram_contacts');
        Schema::dropIfExists('telegram_bots');
    }
};

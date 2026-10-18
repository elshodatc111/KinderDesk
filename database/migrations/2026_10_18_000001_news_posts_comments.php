<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sotuv sahifasidagi «Yangiliklar»: matn, rasm + matn yoki YouTube video + matn
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10)->default('text');          // text|image|video
            $t->string('slug', 190)->unique();
            $t->string('title_uz', 200);
            $t->string('title_ru', 200)->nullable();
            $t->longText('body_uz')->nullable();              // tozalangan HTML
            $t->longText('body_ru')->nullable();
            $t->string('cover_path', 190)->nullable();        // storage/app/posts/...
            $t->string('youtube_id', 11)->nullable();
            $t->string('status', 10)->default('draft');      // draft|published
            $t->timestamp('published_at')->nullable();        // kelajak vaqt = rejalashtirilgan
            $t->timestamp('edited_at')->nullable();
            $t->boolean('is_pinned')->default(false);
            $t->boolean('comments_enabled')->default(true);
            $t->unsignedInteger('views')->default(0);
            $t->timestamps();
            $t->index(['status', 'published_at']);
        });

        Schema::create('post_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('post_comments')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_name', 80);
            $t->text('body');
            $t->string('status', 10)->default('pending');    // pending|approved|hidden
            $t->boolean('is_staff')->default(false);          // KinderDesk jamoasi javobi
            $t->string('ip_hash', 64)->nullable();            // IP ning HMAC xeshi (spam uchun), IP o'zi saqlanmaydi
            $t->timestamps();
            $t->index(['post_id', 'status']);
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('posts');
    }
};

<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Support\Site\HtmlSanitizer;
use App\Support\Site\Social;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withSession(['locale' => 'uz']);
        Carbon::setTestNow('2026-10-18 12:00:00');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('is_super_admin', true)->firstOrFail();
    }

    private function mkPost(array $o = []): Post
    {
        $p = new Post(array_merge([
            'type' => 'text', 'title_uz' => 'Salom dunyo', 'title_ru' => null, 'body_uz' => '<p>Matn</p>', 'body_ru' => null,
            'status' => 'published', 'published_at' => now()->subHour(), 'comments_enabled' => true,
        ], $o));
        $p->slug = Post::makeSlug($p->title_uz);
        $p->save();

        return $p;
    }

    private function sendComment(Post $p, array $o = [], int $age = 10)
    {
        $token = Crypt::encryptString((string) (time() - $age));

        return $this->post(route('news.comment', $p->slug), array_merge(['name' => 'Mehmon', 'body' => 'Juda yaxshi post', 't' => $token], $o));
    }

    // ---------- Ijtimoiy tarmoqlar ----------

    public function test_social_normalize(): void
    {
        $this->assertSame('https://t.me/kinderdesk', Social::normalize('telegram', '@kinderdesk'));
        $this->assertSame('https://instagram.com/kd.uz', Social::normalize('instagram', 'https://www.instagram.com/kd.uz'));
        $this->assertSame('', Social::normalize('youtube', ''));
        $this->assertNull(Social::normalize('telegram', 'https://evil.com/x'));
        $this->assertNull(Social::normalize('youtube', 'https://user:pw@youtube.com/@kd'));
    }

    public function test_footer_shows_only_filled_social_links(): void
    {
        $this->get('/')->assertOk()->assertDontSee('t.me/kdchannel');
        PlatformSetting::put('social.telegram', 'https://t.me/kdchannel');
        $this->get('/')->assertOk()->assertSee('https://t.me/kdchannel', false)->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_settings_social_save_and_validation(): void
    {
        $this->actingAs($this->admin());
        $this->put(route('admin.settings.social'), ['social_telegram' => '@kinderdesk', 'social_instagram' => '', 'social_youtube' => ''])->assertSessionHasNoErrors();
        $this->assertSame('https://t.me/kinderdesk', PlatformSetting::get('social.telegram'));
        $this->put(route('admin.settings.social'), ['social_telegram' => 'https://evil.com/a', 'social_instagram' => '', 'social_youtube' => ''])->assertSessionHasErrors();
    }

    // ---------- Sanitizer ----------

    public function test_sanitizer_strips_dangerous_markup(): void
    {
        $h = HtmlSanitizer::clean('<p onclick="x()">Salom <script>alert(1)</script><b>q</b> <a href="javascript:alert(1)">l</a> <a href="https://a.uz">ok</a></p><img src=x onerror=y><iframe src="//e"></iframe>');
        $this->assertStringNotContainsString('script', $h);
        $this->assertStringNotContainsString('onclick', $h);
        $this->assertStringNotContainsString('javascript', $h);
        $this->assertStringNotContainsString('<img', $h);
        $this->assertStringNotContainsString('iframe', $h);
        $this->assertStringContainsString('href="https://a.uz"', $h);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $h);
    }

    // ---------- Admin: postlar ----------

    public function test_admin_pages_open_and_are_closed_to_guests(): void
    {
        $this->get(route('admin.posts.index'))->assertRedirect();
        $this->actingAs($this->admin());
        $this->get(route('admin.posts.index'))->assertOk();
        $this->get(route('admin.posts.create'))->assertOk()->assertSee('Jonli ko\'rinish', false);
        $p = $this->mkPost();
        $this->get(route('admin.posts.edit', $p))->assertOk()->assertSee('Salom dunyo');
        $this->get(route('admin.comments.index'))->assertOk();
    }

    public function test_create_text_post_sanitizes_and_defaults_time(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.posts.store'), [
            'type' => 'text', 'title_uz' => "Yangi o'zgarishlar", 'body_uz' => '<p>Matn<script>x</script></p>', 'status' => 'published', 'comments_enabled' => 1,
        ])->assertSessionHasNoErrors();
        $p = Post::firstOrFail();
        $this->assertStringNotContainsString('script', $p->body_uz);
        $this->assertSame(now()->toDateTimeString(), $p->published_at->toDateTimeString());
        $this->assertStringStartsWith('yangi-ozgarishlar', $p->slug);
        $this->assertTrue($p->comments_enabled);
    }

    public function test_text_post_requires_body_and_title(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.posts.store'), ['type' => 'text', 'title_uz' => 'A', 'body_uz' => '<p> </p>', 'status' => 'published'])->assertSessionHasErrors('body_uz');
        $this->post(route('admin.posts.store'), ['type' => 'text', 'title_uz' => '', 'body_uz' => '<p>x</p>', 'status' => 'published'])->assertSessionHasErrors('title_uz');
    }

    public function test_image_post_needs_cover_and_stores_it(): void
    {
        $this->actingAs($this->admin());
        $base = ['type' => 'image', 'title_uz' => 'Rasmli', 'body_uz' => '<p>x</p>', 'status' => 'published'];
        $this->post(route('admin.posts.store'), $base)->assertSessionHasErrors('cover');
        $this->post(route('admin.posts.store'), $base + ['cover' => UploadedFile::fake()->image('a.jpg', 1200, 800)])->assertSessionHasNoErrors();
        $p = Post::firstOrFail();
        $this->assertStringStartsWith('posts/', $p->cover_path);
        Storage::disk('local')->assertExists($p->cover_path);
        auth()->logout();
        $this->get(route('news.cover', $p->slug))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        // post o'chganda rasm ham o'chadi
        $this->actingAs($this->admin());
        $this->delete(route('admin.posts.destroy', $p))->assertRedirect();
        Storage::disk('local')->assertMissing($p->cover_path);
    }

    public function test_video_post_validates_youtube(): void
    {
        $this->actingAs($this->admin());
        $base = ['type' => 'video', 'title_uz' => 'Video', 'body_uz' => '<p>x</p>', 'status' => 'published'];
        $this->post(route('admin.posts.store'), $base + ['video_url' => 'https://evil.com/x'])->assertSessionHasErrors('video_url');
        $this->post(route('admin.posts.store'), $base + ['video_url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertSessionHasNoErrors();
        $p = Post::firstOrFail();
        $this->assertSame('dQw4w9WgXcQ', $p->youtube_id);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $p->embedUrl());
        $this->get(route('news.show', $p->slug))->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_update_marks_edited_and_switching_type_drops_cover(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.posts.store'), ['type' => 'image', 'title_uz' => 'R', 'status' => 'published', 'cover' => UploadedFile::fake()->image('a.png', 800, 600)]);
        $p = Post::firstOrFail();
        $old = $p->cover_path;
        $this->put(route('admin.posts.update', $p), ['type' => 'text', 'title_uz' => 'R2', 'body_uz' => '<p>m</p>', 'status' => 'published'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertNull($p->cover_path);
        $this->assertNotNull($p->edited_at);
        Storage::disk('local')->assertMissing($old);
    }

    // ---------- Ko'rinish: qoralama / rejalashtirilgan ----------

    public function test_draft_and_scheduled_are_hidden_from_public(): void
    {
        $pub = $this->mkPost(['title_uz' => 'Ochiq post']);
        $draft = $this->mkPost(['title_uz' => 'Qoralama post', 'status' => 'draft', 'published_at' => null]);
        $future = $this->mkPost(['title_uz' => 'Kelajak post', 'published_at' => now()->addDay()]);

        $this->get(route('news.index'))->assertOk()->assertSee('Ochiq post')->assertDontSee('Qoralama post')->assertDontSee('Kelajak post');
        $this->get('/')->assertOk()->assertSee('Ochiq post')->assertDontSee('Qoralama post');
        $this->get(route('news.show', $draft->slug))->assertNotFound();
        $this->get(route('news.show', $future->slug))->assertNotFound();

        $this->actingAs($this->admin());
        $this->get(route('news.show', $draft->slug))->assertOk();

        // vaqt kelganda o'zi chiqadi
        auth()->logout();
        Carbon::setTestNow(now()->addDays(2));
        $this->get(route('news.show', $future->slug))->assertOk();
    }

    public function test_locale_fallback_ru_to_uz(): void
    {
        $p = $this->mkPost(['title_uz' => 'Uzbekcha sarlavha', 'title_ru' => 'Русский заголовок', 'body_uz' => '<p>Uz matn</p>', 'body_ru' => '']);
        $this->withSession(['locale' => 'ru'])->get(route('news.show', $p->slug))->assertSee('Русский заголовок')->assertSee('Uz matn');
        $this->withSession(['locale' => 'uz'])->get(route('news.show', $p->slug))->assertSee('Uzbekcha sarlavha')->assertDontSee('Русский заголовок');
    }

    public function test_views_counted_once_per_session(): void
    {
        $p = $this->mkPost();
        $this->get(route('news.show', $p->slug));
        $this->get(route('news.show', $p->slug));
        $this->assertSame(1, $p->fresh()->views);
    }

    // ---------- Izohlar ----------

    public function test_guest_comment_is_pending_then_approved_and_shown(): void
    {
        $p = $this->mkPost();
        $this->sendComment($p)->assertSessionHasNoErrors()->assertRedirect();
        $c = PostComment::firstOrFail();
        $this->assertSame('pending', $c->status);
        $this->get(route('news.show', $p->slug))->assertDontSee('Juda yaxshi post');

        $this->actingAs($this->admin());
        $this->post(route('admin.comments.approve', $c))->assertRedirect();
        auth()->logout();
        $this->get(route('news.show', $p->slug))->assertSee('Juda yaxshi post')->assertSee('Mehmon');
    }

    public function test_comment_html_is_escaped_in_output(): void
    {
        $p = $this->mkPost();
        $this->sendComment($p, ['body' => '<b>qalin</b> <script>alert(1)</script> salom'])->assertSessionHasNoErrors();
        $c = PostComment::firstOrFail();
        $this->assertStringNotContainsString('<', $c->body);
    }

    public function test_honeypot_and_fast_submit_are_rejected(): void
    {
        $p = $this->mkPost();
        $this->sendComment($p, ['website' => 'http://spam.com'])->assertRedirect();
        $this->assertSame(0, PostComment::count());
        $this->sendComment($p, [], 0)->assertSessionHasErrors('body');
        $this->sendComment($p, ['t' => 'garbage'])->assertSessionHasErrors('body');
        $this->assertSame(0, PostComment::count());
    }

    public function test_duplicate_and_link_spam_rejected(): void
    {
        $p = $this->mkPost();
        $this->sendComment($p)->assertSessionHasNoErrors();
        $this->sendComment($p)->assertSessionHasErrors('body');
        $this->sendComment($p, ['body' => 'http://a.com http://b.com http://c.com'])->assertSessionHasErrors('body');
        $this->assertSame(1, PostComment::count());
    }

    public function test_comment_throttle(): void
    {
        RateLimiter::clear('post-comment');
        $p = $this->mkPost();
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = $this->sendComment($p, ['body' => 'Izoh raqami '.$i.' yaxshi'])->getStatusCode();
        }
        $this->assertContains(429, $codes);
    }

    public function test_comments_disabled_blocks_submit(): void
    {
        $p = $this->mkPost(['comments_enabled' => false]);
        $this->sendComment($p)->assertForbidden();
    }

    public function test_notifies_super_admin_on_new_pending_comment(): void
    {
        $p = $this->mkPost();
        $this->sendComment($p)->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, $this->admin()->notifications()->count());
    }

    public function test_admin_hides_deletes_bulk_and_replies(): void
    {
        $p = $this->mkPost();
        $mk = fn ($n, $s = 'approved') => PostComment::create(['post_id' => $p->id, 'author_name' => $n, 'body' => 'Izoh '.$n, 'status' => $s]);
        $a = $mk('Anvar'); $b = $mk('Bobur'); $c = $mk('Cyrus', 'pending'); $d = $mk('Dilya');
        $this->actingAs($this->admin());

        $this->post(route('admin.comments.hide', $a))->assertRedirect();
        $this->assertSame('hidden', $a->fresh()->status);
        $this->get(route('news.show', $p->slug))->assertDontSee('Izoh Anvar')->assertSee('Izoh Bobur');

        $this->delete(route('admin.comments.destroy', $b))->assertRedirect();
        $this->assertNull(PostComment::find($b->id));

        $this->post(route('admin.comments.bulk'), ['action' => 'approve', 'ids' => [$c->id]])->assertSessionHasNoErrors();
        $this->assertSame('approved', $c->fresh()->status);

        $this->post(route('admin.comments.reply', $c), ['body' => 'Rahmat!'])->assertSessionHasNoErrors();
        $reply = PostComment::where('parent_id', $c->id)->firstOrFail();
        $this->assertTrue($reply->is_staff);
        $this->get(route('news.show', $p->slug))->assertSee('Rahmat!');

        // izoh o'chsa, javoblari ham o'chadi
        $this->delete(route('admin.comments.destroy', $c));
        $this->assertNull(PostComment::find($reply->id));

        $this->post(route('admin.comments.bulk'), ['action' => 'delete', 'ids' => [$a->id, $d->id]]);
        $this->assertSame(0, PostComment::count());
    }

    public function test_non_admin_cannot_moderate(): void
    {
        $p = $this->mkPost();
        $c = PostComment::create(['post_id' => $p->id, 'author_name' => 'X', 'body' => 'Izoh', 'status' => 'pending']);
        $this->post(route('admin.comments.approve', $c))->assertRedirect();
        $this->assertSame('pending', $c->fresh()->status);
        $this->delete(route('admin.comments.destroy', $c));
        $this->assertNotNull(PostComment::find($c->id));
    }

    public function test_logged_in_user_comment_auto_approved(): void
    {
        $p = $this->mkPost();
        $this->actingAs($this->admin());
        $this->sendComment($p, ['name' => ''])->assertSessionHasNoErrors();
        $c = PostComment::firstOrFail();
        $this->assertSame('approved', $c->status);
        $this->assertTrue($c->is_staff);
    }

    public function test_home_page_survives_without_posts(): void
    {
        $this->get('/')->assertOk()->assertDontSee('So\'nggi yangiliklar');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Phase11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Carbon::setTestNow('2026-10-19 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mkPost(array $o = []): Post
    {
        $p = new Post(array_merge(['type' => 'text', 'title_uz' => 'SEO sinov posti', 'title_ru' => 'SEO тест', 'body_uz' => '<p>Bog\'cha uchun matn</p>', 'status' => 'published', 'published_at' => now()->subDay(), 'comments_enabled' => true], $o));
        $p->slug = Post::makeSlug($p->title_uz);
        $p->save();

        return $p;
    }

    public function test_lang_param_switches_locale_for_crawlers(): void
    {
        $this->get('/?lang=uz')->assertOk()->assertSee('<html lang="uz">', false);
        $this->flushSession();
        $this->get('/?lang=ru')->assertOk()->assertSee('<html lang="ru">', false);
    }

    public function test_home_has_full_head_markup(): void
    {
        $html = $this->get('/?lang=uz')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<title>[^<]{20,110}</title>~', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/').'?lang=uz"', $html);
        $this->assertStringContainsString('hreflang="uz"', $html);
        $this->assertStringContainsString('hreflang="ru"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('og-uz.png', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('index,follow', $html);
        // description uzunligi
        preg_match('~name="description" content="([^"]*)"~u', $html, $m);
        $this->assertLessThanOrEqual(165, mb_strlen(html_entity_decode($m[1])));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_russian_head_uses_russian_texts_and_image(): void
    {
        $html = $this->get('/?lang=ru')->assertOk()->getContent();
        $this->assertStringContainsString('Программа для детского сада', $html);
        $this->assertStringContainsString('og-ru.png', $html);
        $this->assertStringContainsString('rel="canonical" href="'.url('/').'?lang=ru"', $html);
    }

    public function test_json_ld_is_valid_json_and_has_expected_types(): void
    {
        $html = $this->get('/?lang=uz')->getContent();
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $this->assertGreaterThanOrEqual(2, count($m[1]));
        $types = [];
        foreach ($m[1] as $json) {
            $d = json_decode($json, true);
            $this->assertIsArray($d, 'JSON-LD noto\'g\'ri: '.$json);
            foreach ($d['@graph'] ?? [$d] as $n) {
                $types[] = $n['@type'];
            }
        }
        foreach (['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'] as $t) {
            $this->assertContains($t, $types);
        }
    }

    public function test_private_pages_are_noindex(): void
    {
        foreach (['/kirish', '/royxatdan-otish', '/parolni-tiklash'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('noindex,nofollow', $html, $url);
            $this->assertStringNotContainsString('rel="canonical"', $html, $url);
        }
        $admin = User::where('is_super_admin', true)->firstOrFail();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('noindex,nofollow', false);
    }

    public function test_post_page_has_article_markup_and_noindex_for_drafts(): void
    {
        $p = $this->mkPost();
        $html = $this->get($p->url().'?lang=uz')->assertOk()->getContent();
        $this->assertStringContainsString('og:type" content="article"', $html);
        $this->assertStringContainsString('BlogPosting', $html);
        $this->assertStringContainsString('BreadcrumbList', $html);
        $this->assertStringContainsString('index,follow', $html);

        $draft = $this->mkPost(['title_uz' => 'Qoralama post', 'status' => 'draft', 'published_at' => null]);
        $this->actingAs(User::where('is_super_admin', true)->firstOrFail());
        $this->get($draft->url())->assertOk()->assertSee('noindex,nofollow', false);
    }

    public function test_post_title_cannot_break_out_of_json_ld(): void
    {
        $p = $this->mkPost(['title_uz' => 'Hujum </script><script>alert(1)</script>']);
        $html = $this->get($p->url())->getContent();
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        foreach ($m[1] as $json) {
            $this->assertIsArray(json_decode($json, true));
        }
    }

    public function test_robots_txt_blocks_private_and_lists_sitemap(): void
    {
        $r = $this->get('/robots.txt')->assertOk();
        $this->assertStringContainsString('text/plain', $r->headers->get('Content-Type'));
        $body = $r->getContent();
        foreach (['/app', '/admin', '/kirish', '/api'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $body);
        }
        $this->assertStringNotContainsString('Disallow: /yangiliklar', $body);
        $this->assertStringContainsString('Sitemap: '.url('sitemap.xml'), $body);
    }

    public function test_sitemap_lists_public_pages_and_only_visible_posts(): void
    {
        $pub = $this->mkPost();
        $draft = $this->mkPost(['title_uz' => 'Yashirin qoralama', 'status' => 'draft', 'published_at' => null]);
        $future = $this->mkPost(['title_uz' => 'Kelajak post', 'published_at' => now()->addWeek()]);

        $r = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $r->headers->get('Content-Type'));
        $xml = $r->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'Sitemap XML noto\'g\'ri');
        $this->assertStringContainsString(route('home').'?lang=uz', $xml);
        $this->assertStringContainsString(route('home').'?lang=ru', $xml);
        $this->assertStringContainsString(route('news.index'), $xml);
        $this->assertStringContainsString(route('legal', 'oferta'), $xml);
        $this->assertStringContainsString($pub->url(), $xml);
        $this->assertStringNotContainsString($draft->slug, $xml);
        $this->assertStringNotContainsString($future->slug, $xml);
        $this->assertStringContainsString('hreflang="x-default"', $xml);
        $this->assertStringContainsString('<lastmod>', $xml);
    }

    public function test_language_switch_links_are_crawlable_on_public_pages(): void
    {
        $this->get('/?lang=uz')->assertSee('href="'.url('/').'?lang=ru"', false);
    }
}

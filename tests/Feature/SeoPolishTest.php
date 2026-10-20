<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO: til aniqlash, RSS, huquqiy sahifalar, ikonka va JSON-LD. */
class SeoPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_language_negotiation_for_new_visitors(): void
    {
        // Til tanlanmagan: Accept-Language yo'q → o'zbekcha
        $this->get('/')->assertOk()->assertSee('<html lang="uz"', false);
        // Rus brauzeri → ruscha
        $this->withHeaders(['Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.5'])->get('/')->assertSee('<html lang="ru"', false);
        // Boshqa til (ingliz) → o'zbekcha
        $this->flushSession();
        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/')->assertSee('<html lang="uz"', false);
        // Aniq ?lang tanlovi ustun
        $this->flushSession();
        $this->withHeaders(['Accept-Language' => 'uz'])->get('/?lang=ru')->assertSee('<html lang="ru"', false);
    }

    public function test_home_has_offer_icons_and_sitemap_link(): void
    {
        $html = $this->get('/?lang=uz')->assertOk()->getContent();
        $this->assertStringContainsString('"offers":{"@type":"Offer","price":"0"', $html);
        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('rel="sitemap"', $html);
        $this->assertStringContainsString('og:image:width', $html);
        $this->assertFileExists(public_path('site.webmanifest'));
        $this->assertNotNull(json_decode(file_get_contents(public_path('site.webmanifest')), true));
    }

    public function test_legal_pages_have_unique_meta_and_breadcrumbs(): void
    {
        $a = $this->get(route('legal', 'oferta').'?lang=uz')->assertOk()->getContent();
        $b = $this->get(route('legal', 'privacy').'?lang=uz')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Ommaviy oferta | KinderDesk</title>', $a);
        $this->assertStringContainsString('<title>Maxfiylik siyosati | KinderDesk</title>', $b);
        $this->assertStringContainsString('BreadcrumbList', $a);
        $this->assertStringContainsString('content="index,follow', $a);
    }

    public function test_rss_feed_lists_visible_posts_only(): void
    {
        Post::create(['type' => 'text', 'slug' => 'birinchi-post', 'title_uz' => 'Birinchi post & test', 'body_uz' => 'Matn', 'status' => 'published', 'published_at' => now()->subHour()]);
        Post::create(['type' => 'text', 'slug' => 'qoralama', 'title_uz' => 'Yashirin qoralama', 'body_uz' => 'Matn', 'status' => 'draft']);

        $res = $this->get(route('news.feed').'?lang=uz')->assertOk();
        $this->assertStringContainsString('application/rss+xml', $res->headers->get('Content-Type'));
        $xml = $res->getContent();
        $this->assertNotFalse(simplexml_load_string($xml)); // yaroqli XML (& belgisi ham)
        $this->assertStringContainsString('Birinchi post &amp; test', $xml);
        $this->assertStringNotContainsString('Yashirin qoralama', $xml);
        // Yangiliklar sahifasida RSS havolasi bor
        $this->get(route('news.index').'?lang=uz')->assertSee('application/rss+xml', false);
    }

    public function test_sitemap_has_lastmod_for_static_pages_and_robots_points_to_it(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<lastmod>', $xml);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:', false)->assertSee('Disallow: /app', false);
    }
}

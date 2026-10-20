<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\NotificationService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Sotuv sahifasi: yangiliklar ro'yxati, post, muqova rasmi va izoh yuborish. */
class NewsController extends Controller
{
    public function index()
    {
        $posts = Post::visible()->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')
            ->withCount(['comments as approved_count' => fn ($q) => $q->where('status', 'approved')])
            ->paginate(9);

        return view('public.news.index', ['posts' => $posts]);
    }

    /** RSS: qidiruv tizimlari va o'quvchilar uchun so'nggi postlar. */
    public function feed()
    {
        $lang = app()->getLocale();
        $items = Post::visible()->orderByDesc('published_at')->limit(30)->get();
        $esc = fn (string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>',
            '<title>KinderDesk — '.$esc(__('Yangiliklar')).'</title>', '<link>'.$esc(route('news.index')).'</link>',
            '<description>'.$esc(__("KinderDesk yangilanishlari, bog'cha boshqaruvi bo'yicha foydali maslahatlar va video yo'riqnomalar.")).'</description>',
            '<language>'.$lang.'</language>', '<atom:link href="'.$esc(route('news.feed')).'" rel="self" type="application/rss+xml"/>'];
        foreach ($items as $p) {
            $xml[] = '<item><title>'.$esc($p->title()).'</title><link>'.$esc($p->url()).'</link><guid isPermaLink="true">'.$esc($p->url()).'</guid>'
                .($p->published_at ? '<pubDate>'.$p->published_at->toRfc2822String().'</pubDate>' : '')
                .'<description>'.$esc($p->excerpt(300)).'</description></item>';
        }
        $xml[] = '</channel></rss>';

        return response(implode("\n", $xml)."\n", 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=900']);
    }

    public function show(Request $request, string $slug)
    {
        $post = $this->find($request, $slug);
        $isAdmin = (bool) $request->user()?->is_super_admin;

        // Ko'rishlar: bir sessiyada bir marta; super admin hisobga olinmaydi
        if ($post->isVisible() && ! $isAdmin) {
            $seen = (array) session('viewed_posts', []);
            if (! in_array($post->id, $seen, true)) {
                $post->increment('views');
                session(['viewed_posts' => array_slice([...$seen, $post->id], -100)]);
            }
        }

        $comments = $post->comments_enabled || $isAdmin
            ? $post->comments()->whereNull('parent_id')->approved()->with(['replies' => fn ($q) => $q->approved()])->orderBy('id')->get()
            : collect();
        $related = Post::visible()->where('id', '!=', $post->id)->orderByDesc('published_at')->limit(3)->get();

        return view('public.news.show', [
            'post' => $post,
            'comments' => $comments,
            'related' => $related,
            'formToken' => Crypt::encryptString((string) time()),
        ]);
    }

    /** Muqova rasmi yopiq storage'dan beriladi (uzoq muddatli kesh bilan). */
    public function cover(Request $request, string $slug)
    {
        $post = $this->find($request, $slug);
        abort_unless($post->cover_path && Storage::disk('local')->exists($post->cover_path), 404);
        $mime = str_ends_with($post->cover_path, '.webp') ? 'image/webp' : 'image/jpeg';

        return Storage::disk('local')->response($post->cover_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => $post->isVisible() ? 'public, max-age=31536000, immutable' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function comment(Request $request, string $slug)
    {
        $post = $this->find($request, $slug);
        abort_unless($post->comments_enabled && $post->isVisible(), 403);
        $back = redirect()->to($post->url().'#izohlar');

        // 1) Yashirin tuzoq maydon: odam ko'rmaydi, bot to'ldiradi. Jim «muvaffaqiyat» ko'rsatamiz.
        if (filled($request->input('website'))) {
            return $back->with('success', "Rahmat! Izohingiz moderatsiyadan so'ng ko'rinadi.");
        }
        // 2) Forma ochilgandan kamida 3 soniya o'tishi kerak
        try {
            $openedAt = (int) Crypt::decryptString((string) $request->input('t'));
        } catch (DecryptException) {
            $openedAt = 0;
        }
        if ($openedAt === 0 || (time() - $openedAt) < 3 || (time() - $openedAt) > 86400) {
            throw ValidationException::withMessages(['body' => __("Forma eskirgan yoki juda tez yuborildi. Sahifani yangilab, qayta urinib ko'ring.")]);
        }

        $user = $request->user();
        $data = $request->validate([
            'name' => $user ? 'nullable|string|max:60' : 'required|string|min:2|max:60',
            'body' => 'required|string|min:3|max:1000',
        ]);
        $body = $this->plain($data['body']);
        $name = $user ? $user->name : $this->plain($data['name']);
        if (mb_strlen($body) < 3 || mb_strlen($name) < 2) {
            throw ValidationException::withMessages(['body' => __("Izoh juda qisqa")]);
        }
        $links = preg_match_all('~(https?://|www\.|t\.me/|[a-z0-9\-]+\.(?:com|uz|ru|net|org|xyz|info|top|site|online)\b)~iu', $body.' '.$name);
        if ($links > 2) {
            throw ValidationException::withMessages(['body' => __("Izohda havolalar juda ko'p")]);
        }

        $ipHash = PostComment::hashIp($request->ip());
        $dup = PostComment::where('post_id', $post->id)->where('ip_hash', $ipHash)->where('body', $body)->where('created_at', '>=', now()->subMinutes(10))->exists();
        if ($dup) {
            throw ValidationException::withMessages(['body' => __("Bu izoh allaqachon yuborilgan")]);
        }

        // Tizimga kirgan foydalanuvchi izohi darrov chiqadi (havola bo'lmasa); mehmon izohi — moderatsiyadan keyin
        $approved = $user && $links === 0;
        $comment = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $user?->id,
            'author_name' => $name,
            'body' => $body,
            'status' => $approved ? 'approved' : 'pending',
            'is_staff' => (bool) $user?->is_super_admin,
            'ip_hash' => $ipHash,
        ]);

        if (! $approved) {
            app(NotificationService::class)->toSuperAdmins(
                'Yangi izoh moderatsiya kutmoqda',
                $name.': '.Str::limit($body, 80).' — «'.$post->title_uz.'»',
                route('admin.comments.index'),
                'info',
                'sms',
            );
        }

        return $back->with('success', $approved ? "Izoh qo'shildi" : "Rahmat! Izohingiz moderatsiyadan so'ng ko'rinadi.");
    }

    /** Ko'rinadigan post; qoralama/rejalashtirilgan postni faqat super admin ko'ra oladi. */
    private function find(Request $request, string $slug): Post
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        abort_unless($post->isVisible() || $request->user()?->is_super_admin, 404);

        return $post;
    }

    /** Teglar, boshqaruv belgilari va ortiqcha bo'shliqlarsiz oddiy matn. */
    private function plain(string $s): string
    {
        $s = strip_tags($s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $s);
        $s = preg_replace("/[ \t]+/u", ' ', $s);
        $s = preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $s));

        return trim($s);
    }
}

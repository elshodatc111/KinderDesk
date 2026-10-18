<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuideVideo;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\News\CoverImage;
use App\Support\Site\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Super admin: sotuv sahifasidagi yangiliklar (postlar). */
class PostController extends Controller
{
    public function index(Request $request)
    {
        $filter = in_array($request->query('f'), ['published', 'scheduled', 'draft'], true) ? $request->query('f') : 'all';
        $posts = Post::query()
            ->withCount([
                'comments as approved_count' => fn ($q) => $q->where('status', 'approved'),
                'comments as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when($filter === 'published', fn ($q) => $q->visible())
            ->when($filter === 'scheduled', fn ($q) => $q->where('status', 'published')->where('published_at', '>', now()))
            ->when($filter === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'filter' => $filter,
            'pending' => PostComment::where('status', 'pending')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.posts.form', ['post' => new Post(['type' => 'text', 'status' => 'published', 'comments_enabled' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $post = new Post($data);
        $post->slug = Post::makeSlug($data['title_uz']);
        $post->save();
        AuditLog::record('post.created', $post, ['title' => $post->title_uz, 'status' => $post->status]);

        return redirect()->route('admin.posts.edit', $post)->with('success', "Post saqlandi");
    }

    public function edit(Post $post)
    {
        return view('admin.posts.form', ['post' => $post]);
    }

    public function update(Request $request, Post $post)
    {
        $wasVisible = $post->isVisible();
        $data = $this->validated($request, $post);
        $post->fill($data);
        if ($wasVisible) {
            $post->edited_at = now();
        }
        $post->save();
        AuditLog::record('post.updated', $post, ['title' => $post->title_uz, 'status' => $post->status]);

        return redirect()->route('admin.posts.edit', $post)->with('success', "Post saqlandi");
    }

    public function destroy(Post $post)
    {
        CoverImage::delete($post->cover_path);
        AuditLog::record('post.deleted', null, ['title' => $post->title_uz]);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', "Post o'chirildi");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Post $post): array
    {
        $d = $request->validate([
            'type' => 'required|in:text,image,video',
            'title_uz' => 'required|string|max:200',
            'title_ru' => 'nullable|string|max:200',
            'body_uz' => 'nullable|string|max:60000',
            'body_ru' => 'nullable|string|max:60000',
            'cover' => 'nullable|file|max:3072',
            'video_url' => 'nullable|string|max:300',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ], [
            'cover.max' => "Rasm 3 MB dan oshmasin",
        ]);

        $bodyUz = HtmlSanitizer::clean($d['body_uz'] ?? '');
        $bodyRu = HtmlSanitizer::clean($d['body_ru'] ?? '');
        $hasText = fn (string $h) => trim(strip_tags($h)) !== '';
        $errors = [];

        if ($d['type'] === 'text' && ! $hasText($bodyUz)) {
            $errors['body_uz'] = "Matn kiritilmagan (o'zbekcha)";
        }

        // Media
        $cover = $post?->cover_path;
        $youtube = null;
        if ($d['type'] === 'image') {
            if ($request->hasFile('cover')) {
                try {
                    $new = CoverImage::store($request->file('cover'));
                    CoverImage::delete($cover);
                    $cover = $new;
                } catch (ValidationException $e) {
                    $errors += $e->errors();
                }
            } elseif (! $cover) {
                $errors['cover'] = "Rasm yuklang";
            }
        } elseif ($cover) {
            CoverImage::delete($cover);   // tur o'zgargan: eski rasm kerak emas
            $cover = null;
        }
        if ($d['type'] === 'video') {
            $youtube = GuideVideo::extractId((string) ($d['video_url'] ?? ''));
            if (! $youtube) {
                $errors['video_url'] = "YouTube havolasi noto'g'ri";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $publishedAt = filled($d['published_at'] ?? null) ? Carbon::parse($d['published_at'], config('app.timezone')) : null;
        if ($d['status'] === 'published' && ! $publishedAt) {
            $publishedAt = $post?->published_at ?? now();
        }

        return [
            'type' => $d['type'],
            'title_uz' => trim($d['title_uz']),
            'title_ru' => filled($d['title_ru'] ?? null) ? trim($d['title_ru']) : null,
            'body_uz' => $bodyUz ?: null,
            'body_ru' => $bodyRu ?: null,
            'cover_path' => $cover,
            'youtube_id' => $youtube,
            'status' => $d['status'],
            'published_at' => $publishedAt,
            'is_pinned' => $request->boolean('is_pinned'),
            'comments_enabled' => $request->boolean('comments_enabled'),
        ];
    }
}

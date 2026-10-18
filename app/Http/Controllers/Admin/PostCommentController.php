<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Http\Request;

/** Super admin: yangilik izohlarini moderatsiya qilish (tasdiqlash, yashirish, o'chirish, javob yozish). */
class PostCommentController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('s'), ['pending', 'approved', 'hidden', 'all'], true) ? $request->query('s') : 'pending';
        $postId = $request->integer('post') ?: null;

        $comments = PostComment::query()
            ->whereNull('parent_id')
            ->with(['post:id,title_uz,slug', 'replies'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($postId, fn ($q) => $q->where('post_id', $postId))
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.posts.comments', [
            'comments' => $comments,
            'status' => $status,
            'postId' => $postId,
            'posts' => Post::orderByDesc('id')->get(['id', 'title_uz']),
            'counts' => PostComment::whereNull('parent_id')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
        ]);
    }

    public function approve(PostComment $comment)
    {
        $comment->update(['status' => 'approved']);

        return back()->with('success', "Izoh tasdiqlandi");
    }

    public function hide(PostComment $comment)
    {
        $comment->update(['status' => 'hidden']);

        return back()->with('success', "Izoh yashirildi");
    }

    public function destroy(PostComment $comment)
    {
        AuditLog::record('post.comment_deleted', null, ['post_id' => $comment->post_id, 'author' => $comment->author_name]);
        $comment->delete();   // javoblari ham o'chadi (cascade)

        return back()->with('success', "Izoh o'chirildi");
    }

    /** Bir nechta izohga birdan: approve | hide | delete. */
    public function bulk(Request $request)
    {
        $data = $request->validate(['action' => 'required|in:approve,hide,delete', 'ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer']);
        $q = PostComment::whereIn('id', $data['ids']);
        $n = (clone $q)->count();
        match ($data['action']) {
            'approve' => $q->update(['status' => 'approved']),
            'hide' => $q->update(['status' => 'hidden']),
            'delete' => $q->delete(),
        };
        if ($data['action'] === 'delete') {
            AuditLog::record('post.comments_bulk_deleted', null, ['count' => $n]);
        }

        return back()->with('success', __(':n ta izoh yangilandi', ['n' => $n]));
    }

    /** «KinderDesk jamoasi» nomidan javob (bitta daraja). */
    public function reply(Request $request, PostComment $comment)
    {
        abort_if($comment->parent_id, 404);
        $data = $request->validate(['body' => 'required|string|min:2|max:1000']);
        PostComment::create([
            'post_id' => $comment->post_id,
            'parent_id' => $comment->id,
            'user_id' => $request->user()->id,
            'author_name' => 'KinderDesk',
            'body' => trim($data['body']),
            'status' => 'approved',
            'is_staff' => true,
        ]);
        if ($comment->status !== 'approved') {
            $comment->update(['status' => 'approved']);   // javob berilgan izoh ko'rinadi
        }

        return back()->with('success', "Javob yuborildi");
    }
}

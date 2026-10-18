<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Qo'ng'iroqcha va bildirishnomalar sahifasi (bog'cha va super admin uchun umumiy). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = $user->visibleNotifications()->latest()->paginate(25);
        $layout = $user->is_super_admin ? 'layouts.admin' : 'layouts.app';

        return view('shared.notifications', compact('items', 'layout'));
    }

    public function recent(Request $request)
    {
        $user = $request->user();
        $items = $user->visibleNotifications()->latest()->limit(12)->get()->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'url' => $n->data['url'] ?? null,
            'level' => $n->data['level'] ?? 'info',
            'read' => $n->read_at !== null,
            'time' => $n->created_at->diffForHumans(),
        ]);

        return response()->json(['items' => $items, 'unread' => $user->unreadVisibleNotificationCount()]);
    }

    public function read(Request $request)
    {
        $request->user()->visibleNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function poll(Request $request)
    {
        return response()->json(['unread' => $request->user()->unreadVisibleNotificationCount(), 'time' => now()->toIso8601String()]);
    }
}

@extends($layout)
@section('title', 'Bildirishnomalar')
@section('actions')
<form method="post" action="{{ auth()->user()->is_super_admin ? route('admin.notifications.read') : route('app.notifications.read') }}">@csrf
    <button class="btn btn-sm">Hammasini o'qilgan qilish</button></form>
@endsection
@section('content')
<div class="card divide-y divide-line max-w-3xl">
    @forelse ($items as $n)
        <a href="{{ $n->data['url'] ?? '#' }}" class="flex gap-3 p-4 hover:bg-cloud {{ $n->read_at ? 'opacity-70' : '' }}">
            <span class="nav-block shrink-0" style="{{ \App\Support\Navigation::colors(match($n->data['level'] ?? 'info') { 'success' => 'leaf', 'warning' => 'marigold', 'danger' => 'berry', default => 'teal' }) }}"><x-icon :name="$n->data['icon'] ?? 'bell'" /></span>
            <span class="min-w-0">
                <span class="block font-semibold">{{ $n->data['title'] ?? '' }}</span>
                @if (!empty($n->data['body']))<span class="block text-sm font-semibold text-ink-soft">{{ $n->data['body'] }}</span>@endif
                <span class="block text-xs font-medium text-ink-mute mt-0.5">{{ $n->created_at->format('d.m.Y H:i') }}</span>
            </span>
        </a>
    @empty
        <div class="empty"><x-icon name="bell" class="size-10" /><p class="font-semibold mt-2">Hozircha xabar yo'q</p></div>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection

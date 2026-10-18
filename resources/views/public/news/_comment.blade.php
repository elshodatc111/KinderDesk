<div class="flex flex-wrap items-center gap-x-3 gap-y-1">
    <span class="font-semibold {{ $c->is_staff ? 'text-teal-700' : '' }}">{{ $c->author_name }}</span>
    @if($c->is_staff)<span class="badge badge-sky">{{ __('Jamoa') }}</span>@endif
    <time class="text-xs font-medium text-ink-mute" datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->created_at->format('d.m.Y, H:i') }}</time>
    @if($isAdmin)
        <span class="ml-auto flex gap-3 text-xs font-semibold">
            <button type="submit" form="pc-hide-{{ $c->id }}" class="text-ink-soft hover:underline">{{ __('Yashirish') }}</button>
            <button type="submit" form="pc-del-{{ $c->id }}" class="text-danger hover:underline" onclick="return confirm('{{ __("Izoh o'chiriladi. Davom etasizmi?") }}')">{{ __("O'chirish") }}</button>
        </span>
        <form id="pc-hide-{{ $c->id }}" method="post" action="{{ route('admin.comments.hide', $c) }}">@csrf</form>
        <form id="pc-del-{{ $c->id }}" method="post" action="{{ route('admin.comments.destroy', $c) }}">@csrf @method('delete')</form>
    @endif
</div>
<p class="mt-1.5 text-sm font-medium text-ink-soft whitespace-pre-line break-words">{{ $c->body }}</p>

@php $crumbs = \App\Support\Navigation::breadcrumbs(auth()->user(), kd_title(), $admin ?? false); @endphp
@if (count($crumbs) > 1)
<nav aria-label="{{ __('Sahifa yo\'li') }}" class="breadcrumbs">
    <ol>
        @foreach ($crumbs as $i => $c)
            <li @if ($loop->last) aria-current="page" @endif>
                @if ($c['url'] && ! $loop->last)<a href="{{ $c['url'] }}">{{ __($c['label']) }}</a>@else<span>{{ __($c['label']) }}</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
@endif

@extends('layouts.public')
@section('title', 'Yangiliklar')
@section('meta_title', __("Yangiliklar va maslahatlar | KinderDesk"))
@section('description', __("KinderDesk yangilanishlari, bog'cha boshqaruvi bo'yicha foydali maslahatlar va video yo'riqnomalar."))
@section('content')
<section class="max-w-6xl mx-auto px-4 py-10 sm:py-14">
    <h1 class="font-display text-3xl sm:text-4xl font-semibold text-balance">Yangiliklar</h1>
    <p class="mt-2 max-w-2xl font-medium text-ink-soft">KinderDesk yangilanishlari, foydali maslahatlar va video yo'riqnomalar.</p>

    @if($posts->isEmpty())
        <div class="card card-pad mt-8"><div class="empty">
            <x-icon name="report" class="size-10" />
            <h3>Hozircha yangilik yo'q</h3>
            <p>Birinchi postlar tez orada shu yerda chiqadi.</p>
        </div></div>
    @else
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)@include('public.news._card', ['post' => $post])@endforeach
        </div>
        <div class="mt-8">{{ $posts->links() }}</div>
    @endif
</section>
@endsection

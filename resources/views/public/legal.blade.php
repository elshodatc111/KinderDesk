@extends('layouts.public')
@php $titles = \App\Support\Site\Legal::PAGES; @endphp
@section('title', $titles[$page])
@section('meta_title', __($titles[$page]).' | KinderDesk')
@section('description', __($titles[$page]).' — '.__("KinderDesk xizmatidan foydalanish shartlari va ma'lumotlarni himoya qilish tartibi."))
@push('head')
<script type="application/ld+json">{!! \App\Support\Site\Seo::json(\App\Support\Site\Seo::breadcrumbs([['KinderDesk', route('home')], [__($titles[$page]), route('legal', $page)]])) !!}</script>
@endpush
@section('content')
<article class="max-w-3xl mx-auto px-4 py-10 sm:py-12 prose-kd">
    <h1>{{ __($titles[$page]) }}</h1>
    {!! \App\Support\Site\Legal::html($page) !!}
</article>
@endsection

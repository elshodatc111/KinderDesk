@extends('layouts.public')
@php $titles = \App\Support\Site\Legal::PAGES; @endphp
@section('title', $titles[$page])
@section('content')
<article class="max-w-3xl mx-auto px-4 py-10 sm:py-12 prose-kd">
    <h1>{{ __($titles[$page]) }}</h1>
    {!! \App\Support\Site\Legal::html($page) !!}
</article>
@endsection

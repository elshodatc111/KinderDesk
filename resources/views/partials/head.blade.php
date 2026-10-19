<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#008878">
<title>{{ trim(html_entity_decode(\Illuminate\Support\Facades\View::yieldContent('meta_title'), ENT_QUOTES)) ?: kd_title('KinderDesk').' — KinderDesk' }}</title>
@include('partials.seo')
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])

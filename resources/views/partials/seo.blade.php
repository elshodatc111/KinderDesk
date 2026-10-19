@php
    use App\Support\Site\Seo;
    use Illuminate\Support\Facades\View;

    $seoIndex = Seo::indexable() && ! View::hasSection('noindex');
    $seoLocale = app()->getLocale();
    $seoDesc = trim(html_entity_decode(View::yieldContent('description'), ENT_QUOTES)) ?: __('Xususiy bog\'chalar uchun boshqaruv dasturi: bolalar davomati, to\'lovlar, qarzdorlar, xodimlar ish haqi va ota-onalarga Telegram xabarlari. 14 kun bepul sinov.');
    $seoTitle = trim(html_entity_decode(View::yieldContent('meta_title'), ENT_QUOTES)) ?: (kd_title('KinderDesk').' — KinderDesk');
    $seoImage = trim(html_entity_decode(View::yieldContent('og_image'), ENT_QUOTES)) ?: Seo::ogImage();
    $seoType = trim(View::yieldContent('og_type')) ?: 'website';
@endphp
@if ($seoIndex)
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
<meta name="description" content="{{ $seoDesc }}">
<link rel="canonical" href="{{ Seo::urlFor($seoLocale) }}">
@foreach (Seo::alternates() as $hl => $href)
<link rel="alternate" hreflang="{{ $hl }}" href="{{ $href }}">
@endforeach
<meta property="og:site_name" content="KinderDesk">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDesc }}">
<meta property="og:url" content="{{ Seo::urlFor($seoLocale) }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:locale" content="{{ Seo::LOCALES[$seoLocale] ?? 'uz_UZ' }}">
@foreach (Seo::LOCALES as $l => $ogl)@if ($l !== $seoLocale)<meta property="og:locale:alternate" content="{{ $ogl }}">@endif @endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDesc }}">
<meta name="twitter:image" content="{{ $seoImage }}">
<script type="application/ld+json">{!! Seo::json(Seo::siteGraph()) !!}</script>
@else
<meta name="robots" content="noindex,nofollow">
@endif

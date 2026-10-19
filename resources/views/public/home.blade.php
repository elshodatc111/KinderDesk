@extends('layouts.public')
@section('title', "Bog'changiz uchun yagona boshqaruv")
@section('meta_title', __("Bog'cha boshqaruv dasturi — davomat va to'lovlar | KinderDesk"))
@section('description', __("Xususiy bog'chalar uchun boshqaruv dasturi: bolalar davomati, to'lovlar, qarzdorlar, xodimlar ish haqi va ota-onalarga Telegram xabarlari. 14 kun bepul sinov."))
@push('head')
@php
    $ld = [
        '@context' => 'https://schema.org',
        '@graph' => array_values(array_filter([
            [
                '@type' => 'SoftwareApplication',
                'name' => 'KinderDesk',
                'url' => route('home'),
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => ['uz', 'ru'],
                'description' => __("Xususiy bog'chalar uchun boshqaruv dasturi: bolalar davomati, to'lovlar, qarzdorlar, xodimlar ish haqi va ota-onalarga Telegram xabarlari. 14 kun bepul sinov."),
                'image' => asset('og-uz.png'),
                'publisher' => ['@id' => route('home').'#organization'],
            ],
            count($faq) ? [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($r) => [
                    '@type' => 'Question',
                    'name' => \App\Support\Site\Faq::pick($r['q']),
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => \App\Support\Site\Faq::pick($r['a'])],
                ], $faq),
            ] : null,
        ])),
    ];
@endphp
<script type="application/ld+json">{!! \App\Support\Site\Seo::json($ld) !!}</script>
@endpush
@section('content')

{{-- ============ Hero: ilova oynasi ============ --}}
<section class="relative overflow-hidden bg-white">
    <div class="absolute inset-x-0 top-0 h-full opacity-[.55] pointer-events-none" style="background:radial-gradient(60rem 28rem at 85% 10%, var(--color-teal-50), transparent 70%), radial-gradient(40rem 22rem at 0% 90%, var(--color-marigold-50), transparent 70%)"></div>
    <div class="relative max-w-6xl mx-auto px-4 pt-10 sm:pt-14 pb-14 sm:pb-20 grid lg:grid-cols-[1fr_1.05fr] gap-10 lg:gap-14 items-center">
        <div>
            <h1 class="font-display text-[2.15rem] sm:text-5xl lg:text-[3.4rem] font-semibold leading-[1.06] tracking-tight text-balance">{{ __("Excel va daftar o'rniga — bir ekran") }}</h1>
            <p class="mt-5 text-base sm:text-lg font-medium text-ink-soft max-w-xl text-pretty">{{ __("Bolalar, davomad, to'lovlar va ish haqi bitta joyda. Qarzdorlar avtomatik aniqlanadi, maosh avtomatik hisoblanadi, ota-onalar esa davomad va to'lovdan Telegramda xabardor bo'ladi.") }}</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="btn btn-accent btn-lg">{{ $trialDays }} kun bepul sinab ko'ring</a>
                <a href="#nashrlar" class="btn btn-lg">Nashrlarni ko'rish</a>
            </div>
            <p class="mt-4 text-sm font-medium text-ink-mute max-w-md">Karta shart emas. Sinov tugagach ma'lumotlaringiz saqlanadi, tizim ko'rish rejimiga o'tadi.</p>
        </div>

        {{-- Ilova oynasi (bezak) --}}
        <div class="relative mx-auto w-full max-w-[34rem] pb-4 sm:pb-0" aria-hidden="true">
            <div class="rounded-2xl border border-line bg-white shadow-[0_24px_60px_-24px_rgba(0,70,62,.35)] overflow-hidden">
                <div class="flex items-center gap-1.5 px-4 h-9 bg-cloud border-b border-line">
                    <span class="size-2.5 rounded-full bg-berry-500/70"></span><span class="size-2.5 rounded-full bg-sun-500/80"></span><span class="size-2.5 rounded-full bg-leaf-500/70"></span>
                    <span class="ml-3 text-[11px] font-semibold text-ink-mute">Davomad · Kaptarlar</span>
                </div>
                <div class="grid grid-cols-[3.2rem_1fr]">
                    <div class="hidden sm:flex flex-col items-center gap-2.5 py-4 border-r border-line bg-white">
                        @foreach (['teal', 'berry', 'leaf', 'marigold', 'sky', 'grape'] as $c)<span class="size-7 rounded-lg" style="background:var(--color-{{ $c }}-100)"><span class="block size-3 m-2 rounded" style="background:var(--color-{{ $c }}-500)"></span></span>@endforeach
                    </div>
                    <div class="col-span-2 sm:col-span-1 p-4 space-y-3">
                        <div class="grid grid-cols-3 gap-2.5">
                            @foreach ([['Keldi', 18, 'leaf'], ['Kelmadi', 1, 'berry'], ['Kasal', 1, 'sun']] as [$l, $n, $c])
                                <div class="rounded-xl p-2.5" style="background:var(--color-{{ $c }}-50);border:1px solid var(--color-{{ $c }}-100)"><p class="text-[11px] font-semibold" style="color:var(--color-{{ $c }}-700)">{{ $l }}</p><p class="text-xl font-semibold leading-tight">{{ $n }}</p></div>
                            @endforeach
                        </div>
                        <div class="rounded-xl border border-line divide-y divide-line">
                            @foreach ([['leaf', 70, 'Keldi'], ['leaf', 54, 'Keldi'], ['berry', 62, 'Kelmadi'], ['leaf', 48, 'Keldi'], ['sun', 58, 'Kasal']] as [$c, $w, $st])
                                <div class="flex items-center gap-3 px-3 py-2">
                                    <span class="size-7 rounded-full shrink-0" style="background:var(--color-{{ $c }}-100)"></span>
                                    <span class="h-2 rounded-full bg-ink/15" style="width:{{ $w }}%"></span>
                                    <span class="ml-auto text-[11px] font-semibold rounded-full px-2.5 py-1" style="background:var(--color-{{ $c }}-100);color:var(--color-{{ $c }}-700)">{{ $st }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 sm:mt-0 sm:absolute sm:-left-6 sm:-bottom-6 flex items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3 shadow-lg w-fit">
                <span class="grid place-items-center size-9 rounded-full bg-leaf-100 text-leaf-700"><x-icon name="check" class="size-5" /></span>
                <div><p class="text-[11px] font-semibold text-ink-mute leading-none">To'lov qabul qilindi</p><p class="font-semibold leading-tight mt-1">+ 450 000 so'm</p></div>
            </div>
            <div class="hidden sm:flex absolute -right-4 -top-5 items-end gap-1 rounded-2xl border border-line bg-white px-4 py-3 shadow-lg">
                @foreach ([14, 22, 17, 28, 24, 34] as $h)<span class="w-2.5 rounded-t {{ $loop->last ? 'bg-teal-500' : 'bg-teal-500/50' }}" style="height:{{ $h }}px"></span>@endforeach
            </div>
        </div>
    </div>
</section>

{{-- ============ Imkoniyatlar: rasm + matn ============ --}}
<section id="imkoniyatlar" class="max-w-6xl mx-auto px-4 py-14 sm:py-20 scroll-mt-20 space-y-14 sm:space-y-20">
    <div class="max-w-2xl">
        <h2 class="font-display text-3xl sm:text-4xl font-semibold text-balance">Kundalik ishning hammasi — bitta oynada</h2>
        <p class="mt-3 font-medium text-ink-soft">Tarbiyachi telefondan davomad belgilaydi, kassir to'lov oladi, direktor esa butun bog'chani bir qarashda ko'radi.</p>
    </div>
    @php
        $rows = [
            ['attendance', 'leaf', 'Davomad', "Guruh kartalari, bir bosishda belgilash", "Har bir guruh o'z kartasida: nechta bola keldi, kelmadi, kasal. Davomat faqat bugungi kun uchun olinadi, o'tgan kunlar o'zgarmaydi — hisob-kitob ishonchli bo'ladi.", ["Kun, hafta va oy bo'yicha jadval", "Sababli kelmagan kunlar uchun chegirma", "Internet uzilsa ham saqlanadi"]],
            ['finance', 'marigold', "Kassa, to'lov va moliya", "Har bir so'm o'z hisobida", "Ota-onalardan to'lov kassada qabul qilinadi, pul direktorga topshiriladi va tasdiqlanadi. Moliya bo'limida hisoblar, xarajat, daromad va subsidiya alohida yuritiladi.", ["Qarzdorlar va oldindan to'lovlar", "Aka-uka chegirmasi avtomatik", "Naqd, karta va bank hisobi alohida"]],
            ['payroll', 'sky', 'Ish haqi', "Qat'iy oylik, davomad bo'yicha yoki aralash", "Tarbiyachi, yordamchi, oshpaz va administrator uchun alohida sxemalar. Qoralama oy boshida o'zi tayyorlanadi, direktor tasdiqlaydi.", ["Premiya, jarima va avanslar", "Almashtirish va tadbir bonuslari", "Hisobot topshirilmasa bonus qo'shilmaydi"]],
            ['sms', 'grape', 'SMS xabarlar', "Ota-onalar xabardor bo'lib turadi", "To'lov, qarz va tug'ilgan kun haqida xabarlar bog'chaning o'z Eskiz hisobidan ketadi. Qaysi holatda yuborilishini o'zingiz belgilaysiz.", ["Tayyor shablonlar", "Har bir vasiy uchun SMS yoqish/o'chirish", "Yuborish tarixi"]],
            ['telegram', 'sky', 'Telegram bot', "Ota-onalar Telegramda xabardor", "Har bir bog'cha o'z botini ulaydi. Bola kelganda yoki kelmaganda, to'lov qabul qilinganda ota-onaga xabar boradi. Tarbiyachi kun davomida rasm va videoni botga yuboradi — guruh ota-onalariga o'zi tarqaladi.", ["Ota-ona shaxsiy havola orqali ulanadi, telefon raqam so'ralmaydi", "Oshpaz kunlik ovqatni, direktor e'lonni bir bosishda yuboradi", "Rasm va videolar serverda saqlanmaydi, 30 daqiqa ichida o'chirish mumkin"]],
            ['leads', 'sun', 'Arizalar', "Yangi ota-onalarni yo'qotmang", "Ochiq forma havolasini ulashing — arizalar voronkaga o'zi tushadi. Keyingi qo'ng'iroq sanasi, mas'ul xodim va manbalar bo'yicha konversiya ko'rinib turadi.", ["Qora ro'yxat ogohlantirishi", "Bir bosishda bolaga aylantirish", "24 soatdan oshgan arizalar belgilanadi"]],
            ['insights', 'teal', 'Statistika va AI', "Raqamlar va tushuntirish bir joyda", "Kunlik, haftalik, oylik va yillik diagrammalar. Premium'da AI direktor uchun har kuni mustaqil hisobot tayyorlaydi — ism va telefonlarsiz, faqat raqamlar asosida.", ["Davomad, tushum va xarajat tendensiyalari", "AI yordamchi tizim bo'yicha savollarga javob beradi", "Kamchiliklar va ehtimoliy sabablar"]],
        ];
    @endphp
    @foreach ($rows as $i => [$illus, $color, $kicker, $title, $text, $points])
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-14 items-center">
            <div class="{{ $i % 2 ? 'lg:order-2' : '' }}">
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold" style="background:var(--color-{{ $color }}-100);color:var(--color-{{ $color }}-700)">{{ __($kicker) }}</span>
                <h3 class="mt-3 text-2xl sm:text-3xl font-semibold tracking-tight text-balance">{{ __($title) }}</h3>
                <p class="mt-3 font-medium text-ink-soft text-pretty">{{ __($text) }}</p>
                <ul class="mt-4 space-y-2">@foreach ($points as $pt)<li class="flex items-start gap-2.5 text-[15px] font-medium"><x-icon name="check" class="size-5 mt-px shrink-0 text-leaf-700" />{{ __($pt) }}</li>@endforeach</ul>
            </div>
            <div class="{{ $i % 2 ? 'lg:order-1' : '' }} rounded-3xl border border-line bg-white p-3 sm:p-4 shadow-[0_18px_40px_-26px_rgba(0,70,62,.4)]">@include('public.illus.'.$illus)</div>
        </div>
    @endforeach
</section>

{{-- ============ Maxfiylik ============ --}}
<section id="maxfiylik" class="max-w-6xl mx-auto px-4 py-14 sm:py-16 scroll-mt-20">
    <div class="max-w-2xl">
        <h2 class="font-display text-3xl sm:text-4xl font-semibold text-balance">{{ __("Bolalar va xodimlar ma'lumoti himoyada") }}</h2>
        <p class="mt-3 font-medium text-ink-soft">{{ __("Bog'cha ma'lumoti — bu oilalar ishonchi. Shuning uchun maxfiylik tizimning asosiga qo'yilgan.") }}</p>
    </div>
    <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ([
            ['lock', "Ma'lumot O'zbekiston serverida", "Barcha ma'lumotlar O'zbekistondagi serverda saqlanadi."],
            ['shield', 'Shifrlangan maydonlar', "Ism, telefon, sog'liq va olib ketuvchilar ma'lumoti bazada shifrlangan holda turadi."],
            ['eye', "Super admin bolalarni ko'rmaydi", "Platforma xodimlari bog'chadagi bolalar va xodimlar ma'lumotiga kira olmaydi."],
            ['sparkles', "AI ismlarni ko'rmaydi", "Sun'iy intellektga faqat umumiy raqamlar yuboriladi: ism, telefon va sog'liq ma'lumoti hech qachon emas."],
            ['check', 'Rozilik jurnali', "Har bir foydalanuvchi oferta va maxfiylik shartlariga roziligini beradi; kim, qachon va qaysi versiyaga rozi bo'lgani yoziladi."],
            ['sms', 'Kirishda Telegram kodi', "Direktor va platforma boshqaruvchisi hisobi parol bilan birga Telegramdagi bir martalik kod bilan himoyalanadi."],
        ] as [$ic, $t, $d])
            <div class="card card-pad"><span class="grid place-items-center size-10 rounded-xl bg-teal-50 text-teal-700"><x-icon :name="$ic" class="size-5" /></span>
                <h3 class="mt-3 font-semibold">{{ __($t) }}</h3><p class="mt-1 text-sm font-medium text-ink-soft">{{ __($d) }}</p></div>
        @endforeach
    </div>
</section>

{{-- ============ Qanday boshlanadi ============ --}}
<section class="bg-teal-50 border-y border-line">
    <div class="max-w-6xl mx-auto px-4 py-14 sm:py-16">
        <h2 class="font-display text-3xl sm:text-4xl font-semibold">Uch qadamda ishga tushadi</h2>
        <ol class="mt-9 grid md:grid-cols-3 gap-5">
            @foreach ([["Ro'yxatdan o'ting", "Telefon raqamingizni SMS kod bilan tasdiqlang va nashrni tanlang."], ["Bog'chani moslang", "Guruhlar, xodimlar, narxlar va kerakli bo'limlarni sozlang."], ["Ishlashni boshlang", "Bolalarni kiriting, davomad va to'lovlarni yuriting."]] as $i => [$t, $d])
                <li class="card p-6 relative">
                    <span class="absolute -top-4 left-5 grid place-items-center size-9 rounded-full bg-marigold-500 text-white font-semibold shadow">{{ $i + 1 }}</span>
                    <h3 class="font-semibold text-lg mt-2">{{ __($t) }}</h3><p class="font-medium text-ink-soft mt-1">{{ __($d) }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ============ Nashrlar va narxlar ============ --}}
<section id="nashrlar" class="max-w-6xl mx-auto px-4 py-14 sm:py-20 scroll-mt-20" x-data="{ months: 3 }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="font-display text-3xl sm:text-4xl font-semibold">Bog'changizga mos nashrni tanlang</h2>
            <p class="mt-2 font-medium text-ink-soft">Hammasida {{ $trialDays }} kunlik bepul sinov. Nashrni keyin almashtirish mumkin.</p>
        </div>
        <div class="tabs" id="narxlar" role="tablist" aria-label="Obuna muddati">
            @foreach ([1 => '1 oy', 3 => '3 oy', 12 => '1 yil'] as $m => $l)
                <button type="button" role="tab" class="tab" :class="months === {{ $m }} && 'is-active'" @click="months = {{ $m }}" :aria-selected="months === {{ $m }}">{{ $l }}</button>
            @endforeach
        </div>
    </div>

    <div class="mt-9 grid md:grid-cols-2 lg:grid-cols-3 gap-5 items-start">
        @foreach ($editions as $ed)
            @php $featured = $ed->key === 'standard'; @endphp
            <div class="card-sticker p-5 sm:p-6 flex flex-col relative {{ $featured ? 'ring-2 ring-marigold-400' : '' }}">
                @if ($featured)<span class="badge badge-orange absolute -top-3 right-5">Ko'pchilik tanlaydi</span>@endif
                <h3 class="text-xl font-semibold">{{ $ed->name }}</h3>
                <p class="text-sm font-medium text-ink-soft mt-1">{{ $ed->tagline }}</p>
                <div class="mt-4">
                    @foreach ($ed->plans as $plan)
                        <div x-show="months === {{ $plan->months }}" @if(!$loop->first) x-cloak @endif>
                            <p class="text-3xl font-semibold tracking-tight">{{ money($plan->price) }} <span class="text-sm font-medium text-ink-mute">so'm</span></p>
                            <p class="text-xs font-medium text-ink-soft mt-1">{{ $plan->label() }} uchun @if ($plan->months > 1) · oyiga {{ money($plan->monthlyEquivalent()) }} so'm @endif</p>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('register', ['edition' => $ed->key]) }}" class="btn {{ $featured ? 'btn-accent' : 'btn-primary' }} btn-block mt-5">Bepul boshlash</a>
                @include('partials.plan-features')
            </div>
        @endforeach
    </div>
    <p class="text-sm font-medium text-ink-mute mt-6">To'lov Payme yoki karta orqali (chek yuborib). Summa to'liq bog'cha balansiga tushadi. Obuna o'zi yechilmaydi — tarifni o'zingiz tanlaysiz.</p>
</section>

{{-- ============ So'nggi yangiliklar ============ --}}
@if (isset($latestPosts) && $latestPosts->count())
<section id="yangiliklar" class="max-w-6xl mx-auto px-4 py-10 sm:py-14 scroll-mt-20">
    <div class="flex items-end justify-between gap-4 mb-6">
        <h2 class="font-display text-3xl font-semibold">So'nggi yangiliklar</h2>
        <a href="{{ route('news.index') }}" class="text-sm font-semibold text-teal-700 hover:underline whitespace-nowrap">Hammasi</a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($latestPosts as $post)@include('public.news._card', ['post' => $post])@endforeach
    </div>
</section>
@endif

{{-- ============ Savollar (super admin sozlamalaridan) ============ --}}
@if (count($faq))
<section id="savollar" class="max-w-3xl mx-auto px-4 py-10 sm:py-12 scroll-mt-20">
    <h2 class="font-display text-3xl font-semibold mb-6">Ko'p beriladigan savollar</h2>
    <div class="space-y-3" x-data="{ open: 0 }">
        @foreach ($faq as $i => $row)
            <div class="card">
                <button type="button" class="w-full flex items-center justify-between gap-4 text-left px-5 py-4 font-semibold" @click="open = open === {{ $i + 1 }} ? 0 : {{ $i + 1 }}" :aria-expanded="open === {{ $i + 1 }}">
                    {{ \App\Support\Site\Faq::pick($row['q']) }} <x-icon name="chevron-down" class="size-5 shrink-0 transition" ::class="open === {{ $i + 1 }} && 'rotate-180'" />
                </button>
                <p x-show="open === {{ $i + 1 }}" x-cloak class="px-5 pb-4 font-medium text-ink-soft">{{ \App\Support\Site\Faq::pick($row['a']) }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="max-w-4xl mx-auto px-4 py-10">
    <div class="rounded-3xl p-8 sm:p-12 text-center text-white" style="background:linear-gradient(135deg,var(--color-teal-600),var(--color-teal-800))">
        <h2 class="font-display text-3xl sm:text-4xl font-semibold text-balance !text-white">Bugun boshlang — {{ $trialDays }} kun hammasi bepul</h2>
        <a href="{{ route('register') }}" class="btn btn-accent btn-lg mt-6">Ro'yxatdan o'tish</a>
    </div>
</section>
@endsection

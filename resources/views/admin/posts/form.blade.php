@extends('layouts.admin')
@section('title', $post->exists ? __('Postni tahrirlash') : __('Yangi post'))
@section('actions')
    <a href="{{ route('admin.posts.index') }}" class="btn btn-sm"><x-icon name="arrow-left" /> Yangiliklar</a>
    @if($post->exists)<a href="{{ $post->url() }}" target="_blank" rel="noopener" class="btn btn-sm"><x-icon name="eye" /> Saytda ko'rish</a>@endif
@endsection
@section('content')
@php
    use App\Support\Site\HtmlSanitizer;
    $cfg = [
        'type' => old('type', $post->type ?: 'text'),
        'title' => ['uz' => old('title_uz', $post->title_uz), 'ru' => old('title_ru', $post->title_ru)],
        'body' => ['uz' => HtmlSanitizer::clean(old('body_uz', $post->body_uz)), 'ru' => HtmlSanitizer::clean(old('body_ru', $post->body_ru))],
        'status' => old('status', $post->status ?: 'published'),
        'publishedAt' => old('published_at', ($post->published_at ?? now())->format('Y-m-d\TH:i')),
        'videoUrl' => old('video_url', $post->youtube_id ? 'https://youtu.be/'.$post->youtube_id : ''),
        'cover' => $post->cover_path ? $post->coverUrl() : null,
        'comments' => (bool) old('comments_enabled', $post->exists ? $post->comments_enabled : true),
        'pinned' => (bool) old('is_pinned', $post->is_pinned),
        'ru' => ['read' => __('daq. o\'qish'), 'draft' => __('Qoralama'), 'scheduled' => __('Rejalashtirilgan'), 'noTitle' => __('Sarlavha shu yerda ko\'rinadi'), 'big' => __('Rasm 3 MB dan oshmasin')],
    ];
    $types = ['text' => ['Matn', 'Faqat sarlavha va matn'], 'image' => ['Rasm + matn', 'Muqova rasmi bilan'], 'video' => ['Video + matn', 'YouTube videosi bilan']];
@endphp

<script>
function postEditorPage(cfg) {
    return {
        type: cfg.type, lang: 'uz', title: cfg.title, body: cfg.body, status: cfg.status, publishedAt: cfg.publishedAt,
        videoUrl: cfg.videoUrl, cover: cfg.cover, comments: cfg.comments, pinned: cfg.pinned, t: cfg.ru,
        device: 'desktop', pane: 'edit', linkOpen: false, linkUrl: '', range: null, coverError: '',
        get videoId() {
            const m = (this.videoUrl || '').trim().match(/^[A-Za-z0-9_-]{11}$|(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/))([A-Za-z0-9_-]{11})/);
            return m ? (m[1] || m[0]) : null;
        },
        hasText(h) { return !!(h || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim(); },
        get shownTitle() { return (this.lang === 'ru' && this.title.ru && this.title.ru.trim()) ? this.title.ru : (this.title.uz || ''); },
        get shownBody() { return (this.lang === 'ru' && this.hasText(this.body.ru)) ? this.body.ru : this.body.uz; },
        get words() { return (this.shownBody || '').replace(/<[^>]*>/g, ' ').split(/\s+/).filter(Boolean).length; },
        get minutes() { return Math.max(1, Math.ceil(this.words / 180)); },
        get isFuture() { return this.status === 'published' && !!this.publishedAt && new Date(this.publishedAt) > new Date(); },
        get dateLabel() {
            if (this.status === 'draft') return this.t.draft;
            const p = (this.publishedAt || '').split(/[-T:]/);
            if (p.length < 5) return '';
            return p[2] + '.' + p[1] + '.' + p[0] + ', ' + p[3] + ':' + p[4];
        },
        cmd(name, value) { document.execCommand(name, false, value || null); },
        block(tag) { document.execCommand('formatBlock', false, '<' + tag + '>'); },
        initEditor(el, lang) {
            el.innerHTML = this.body[lang] || '';
            try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
        },
        paste(e) {
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        },
        openLink() {
            const sel = window.getSelection();
            this.range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
            this.linkUrl = '';
            this.linkOpen = true;
            this.$nextTick(() => this.$refs.linkInput && this.$refs.linkInput.focus());
        },
        applyLink() {
            let url = (this.linkUrl || '').trim();
            if (!url) { this.linkOpen = false; return; }
            if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)) url = 'https://' + url;
            const sel = window.getSelection();
            sel.removeAllRanges();
            if (this.range) sel.addRange(this.range);
            document.execCommand('createLink', false, url);
            this.linkOpen = false;
        },
        pickCover(e) {
            const f = e.target.files && e.target.files[0];
            this.coverError = '';
            if (!f) return;
            if (f.size > 3 * 1024 * 1024) { this.coverError = this.t.big; e.target.value = ''; return; }
            this.cover = URL.createObjectURL(f);
        },
    };
}
</script>

<form method="post" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}"
      x-data="postEditorPage(@js($cfg))" class="space-y-4">
    @csrf
    @if($post->exists)@method('put')@endif

    {{-- Telefonda: Tahrirlash / Ko'rinish tablari --}}
    <div class="tabs xl:hidden" role="tablist">
        <button type="button" class="tab" :class="pane === 'edit' && 'is-active'" @click="pane = 'edit'">Tahrirlash</button>
        <button type="button" class="tab" :class="pane === 'preview' && 'is-active'" @click="pane = 'preview'">Jonli ko'rinish</button>
    </div>

    <div class="grid xl:grid-cols-2 gap-6 items-start">
        {{-- ============ Chap: tahrirlash ============ --}}
        <div class="space-y-4 min-w-0" :class="pane === 'preview' && 'hidden xl:block'">

            <section class="card card-pad">
                <span class="label">Post turi</span>
                <div class="grid sm:grid-cols-3 gap-2">
                    @foreach($types as $k => [$l, $d])
                        <label class="relative cursor-pointer rounded-xl border p-3 transition-colors has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 border-line hover:border-teal-200">
                            <input type="radio" name="type" value="{{ $k }}" x-model="type" class="absolute opacity-0 inset-0 cursor-pointer">
                            <span class="block font-semibold text-sm">{{ $l }}</span>
                            <span class="block text-xs font-medium text-ink-mute mt-0.5">{{ $d }}</span>
                        </label>
                    @endforeach
                </div>
                @error('type')<p class="field-error">{{ $message }}</p>@enderror
            </section>

            <section class="card card-pad space-y-4">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="seg" role="tablist" aria-label="Til">
                        <label><input type="radio" name="_lang" value="uz" x-model="lang"><span>O'zbekcha</span></label>
                        <label><input type="radio" name="_lang" value="ru" x-model="lang"><span>Русский <i class="not-italic text-marigold-600" x-show="!title.ru && !hasText(body.ru)" title="Hali yozilmagan">•</i></span></label>
                    </div>
                    <p class="help !mt-0" x-show="lang === 'ru'" x-cloak>Rus tilidagi matn bo'sh bo'lsa, saytda o'zbekcha ko'rsatiladi.</p>
                </div>

                <div x-show="lang === 'uz'">
                    <label class="label" for="title_uz">Sarlavha (UZ) *</label>
                    <input id="title_uz" name="title_uz" class="input @error('title_uz') is-invalid @enderror" x-model="title.uz" maxlength="200" required autocomplete="off">
                    @error('title_uz')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div x-show="lang === 'ru'" x-cloak>
                    <label class="label" for="title_ru">Заголовок (RU)</label>
                    <input id="title_ru" name="title_ru" class="input" x-model="title.ru" maxlength="200" autocomplete="off">
                </div>

                {{-- Rasm --}}
                <div x-show="type === 'image'" x-cloak>
                    <label class="label" for="cover">Muqova rasmi *</label>
                    <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" @change="pickCover($event)"
                           class="block w-full text-sm font-medium text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:font-semibold file:text-teal-700 hover:file:bg-teal-100">
                    <p class="help">JPG, PNG yoki WebP, 3 MB gacha. Avtomatik kichraytiriladi va tezlashtiriladi.</p>
                    <p class="field-error" x-show="coverError" x-text="coverError"></p>
                    @error('cover')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                {{-- Video --}}
                <div x-show="type === 'video'" x-cloak>
                    <label class="label" for="video_url">YouTube havolasi *</label>
                    <input id="video_url" name="video_url" class="input @error('video_url') is-invalid @enderror" x-model="videoUrl" placeholder="https://youtu.be/..." maxlength="300" autocomplete="off">
                    <p class="help" x-show="videoUrl && !videoId" x-cloak><span class="text-danger font-semibold">YouTube havolasi tanilmadi.</span> To'liq havola yoki 11 belgili video ID kiriting.</p>
                    @error('video_url')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                {{-- Matn: vizual muharrir (har til uchun alohida) --}}
                <div>
                    <span class="label">Matn <span x-show="type === 'text' && lang === 'uz'">*</span></span>
                    <div class="rounded-xl border border-line bg-white focus-within:border-teal-500 focus-within:ring-[3px] focus-within:ring-teal-100">
                        <div class="flex flex-wrap items-center gap-1 border-b border-line bg-cloud/60 px-2 py-1.5 rounded-t-xl" @mousedown.prevent>
                            <button type="button" class="btn btn-sm !px-2.5 font-bold" title="Qalin" aria-label="Qalin" @click="cmd('bold')">B</button>
                            <button type="button" class="btn btn-sm !px-2.5 italic" title="Qiya" aria-label="Qiya" @click="cmd('italic')">I</button>
                            <button type="button" class="btn btn-sm !px-2.5 underline" title="Tagiga chizilgan" aria-label="Tagiga chizilgan" @click="cmd('underline')">U</button>
                            <span class="w-px h-5 bg-line mx-1"></span>
                            <button type="button" class="btn btn-sm !px-2.5 font-bold" title="Katta sarlavha" aria-label="Katta sarlavha" @click="block('h2')">H2</button>
                            <button type="button" class="btn btn-sm !px-2.5 font-bold" title="Kichik sarlavha" aria-label="Kichik sarlavha" @click="block('h3')">H3</button>
                            <button type="button" class="btn btn-sm !px-2.5" title="Oddiy matn" aria-label="Oddiy matn" @click="block('p')">¶</button>
                            <span class="w-px h-5 bg-line mx-1"></span>
                            <button type="button" class="btn btn-sm !px-2.5" title="Markerli ro'yxat" aria-label="Markerli ro'yxat" @click="cmd('insertUnorderedList')">• —</button>
                            <button type="button" class="btn btn-sm !px-2.5" title="Raqamli ro'yxat" aria-label="Raqamli ro'yxat" @click="cmd('insertOrderedList')">1. —</button>
                            <button type="button" class="btn btn-sm !px-2.5" title="Iqtibos" aria-label="Iqtibos" @click="block('blockquote')">❝</button>
                            <span class="w-px h-5 bg-line mx-1"></span>
                            <button type="button" class="btn btn-sm !px-2.5" title="Havola" aria-label="Havola" @click="openLink()"><x-icon name="link" /></button>
                            <button type="button" class="btn btn-sm !px-2.5" title="Havolani olib tashlash" aria-label="Havolani olib tashlash" @click="cmd('unlink')"><x-icon name="ban" /></button>
                            <button type="button" class="btn btn-sm !px-2.5" title="Formatni tozalash" aria-label="Formatni tozalash" @click="cmd('removeFormat'); block('p')"><x-icon name="refresh" /></button>
                        </div>
                        <div class="flex gap-2 border-b border-line bg-teal-50/50 px-2 py-1.5" x-show="linkOpen" x-cloak>
                            <input type="text" class="input !py-1" x-ref="linkInput" x-model="linkUrl" placeholder="https://..." @keydown.enter.prevent="applyLink()" @keydown.escape.prevent="linkOpen = false">
                            <button type="button" class="btn btn-sm btn-primary" @click="applyLink()">Qo'yish</button>
                            <button type="button" class="btn btn-sm" @click="linkOpen = false">Bekor</button>
                        </div>

                        <div x-show="lang === 'uz'">
                            <div contenteditable="true" role="textbox" aria-multiline="true" aria-label="Matn (UZ)" spellcheck="true"
                                 class="prose-kd min-h-[16rem] max-h-[34rem] overflow-y-auto px-4 py-3 outline-none before:pointer-events-none before:text-ink-mute data-[empty=true]:before:content-[attr(data-placeholder)]"
                                 data-placeholder="Matnni shu yerga yozing…" :data-empty="!hasText(body.uz)"
                                 x-init="initEditor($el, 'uz')" @input="body.uz = $el.innerHTML" @paste.prevent="paste($event)"></div>
                        </div>
                        <div x-show="lang === 'ru'" x-cloak>
                            <div contenteditable="true" role="textbox" aria-multiline="true" aria-label="Текст (RU)" spellcheck="true"
                                 class="prose-kd min-h-[16rem] max-h-[34rem] overflow-y-auto px-4 py-3 outline-none before:pointer-events-none before:text-ink-mute data-[empty=true]:before:content-[attr(data-placeholder)]"
                                 data-placeholder="Введите текст здесь…" :data-empty="!hasText(body.ru)"
                                 x-init="initEditor($el, 'ru')" @input="body.ru = $el.innerHTML" @paste.prevent="paste($event)"></div>
                        </div>
                    </div>
                    <textarea name="body_uz" x-model="body.uz" class="sr-only" tabindex="-1" aria-hidden="true"></textarea>
                    <textarea name="body_ru" x-model="body.ru" class="sr-only" tabindex="-1" aria-hidden="true"></textarea>
                    @error('body_uz')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="help">Tahrirlash oynasi saytdagi ko'rinishga yaqin. Boshqa joydan nusxalangan matn oddiy matn bo'lib qo'yiladi; keyin sarlavha, ro'yxat va havolalarni shu yerda belgilang.</p>
                </div>
            </section>

            <section class="card card-pad space-y-4">
                <h2 class="section-title">Nashr qilish</h2>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="status">Holati</label>
                        <select id="status" name="status" class="select" x-model="status">
                            <option value="published">Nashr qilish</option>
                            <option value="draft">Qoralama (saytda ko'rinmaydi)</option>
                        </select>
                    </div>
                    <div x-show="status === 'published'" x-cloak>
                        <label class="label" for="published_at">Nashr vaqti (Toshkent)</label>
                        <input id="published_at" type="datetime-local" name="published_at" class="input" x-model="publishedAt">
                        @error('published_at')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="help !mt-0" x-show="isFuture" x-cloak>Rejalashtirilgan: post belgilangan vaqtda saytda o'zi paydo bo'ladi.</p>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="check" name="comments_enabled" value="1" x-model="comments"> Izohlar yoqilgan</label>
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="check" name="is_pinned" value="1" x-model="pinned"> Ro'yxat tepasiga mahkamlash</label>
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button class="btn btn-primary">Saqlash</button>
                    <a href="{{ route('admin.posts.index') }}" class="btn">Bekor qilish</a>
                </div>
            </section>

            @if($post->exists)
                <section class="card card-pad">
                    <h2 class="section-title">Postni o'chirish</h2>
                    <p class="help">Post, uning rasmi va barcha izohlari butunlay o'chadi. Qaytarib bo'lmaydi.</p>
                    <button type="submit" class="btn btn-sm mt-3 !text-danger" form="post-delete" onclick="return confirm('{{ __("Post va uning izohlari o'chiriladi. Davom etasizmi?") }}')"><x-icon name="trash" /> O'chirish</button>
                </section>
            @endif
        </div>

        {{-- ============ O'ng: jonli ko'rinish ============ --}}
        <aside class="min-w-0 xl:sticky xl:top-20" :class="pane === 'edit' && 'hidden xl:block'" aria-label="Jonli ko'rinish">
            <div class="flex items-center justify-between gap-3 mb-2">
                <span class="label !mb-0">Jonli ko'rinish <span class="font-medium text-ink-mute">· saytda shunday chiqadi</span></span>
                <div class="seg text-xs" role="group" aria-label="Ekran">
                    <label><input type="radio" name="_device" value="desktop" x-model="device"><span>Kompyuter</span></label>
                    <label><input type="radio" name="_device" value="phone" x-model="device"><span>Telefon</span></label>
                </div>
            </div>
            <div class="mx-auto w-full rounded-2xl border border-line bg-cloud shadow-[var(--shadow-soft)] overflow-hidden transition-[max-width] duration-300 motion-reduce:transition-none"
                 :class="device === 'phone' ? 'max-w-[23rem]' : 'max-w-full'">
                <div class="max-h-[calc(100vh-9rem)] overflow-y-auto">
                    <article class="px-5 py-7 sm:px-8" :class="device === 'phone' && '!px-4'">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-ink-mute">
                            <span class="badge badge-sky" x-text="type === 'image' ? @js(__('Rasm + matn')) : (type === 'video' ? @js(__('Video + matn')) : @js(__('Matn')))"></span>
                            <time x-text="dateLabel"></time>
                            <span x-show="isFuture" x-cloak class="badge badge-sun" x-text="t.scheduled"></span>
                            <span x-text="minutes + ' ' + t.read"></span>
                        </div>
                        <h1 class="mt-3 font-display text-2xl sm:text-3xl font-semibold leading-tight text-balance" :class="!shownTitle && 'text-ink-mute'" x-text="shownTitle || t.noTitle"></h1>

                        <template x-if="type === 'image'">
                            <div class="mt-5">
                                <img x-show="cover" :src="cover" alt="" class="w-full rounded-xl border border-line object-cover aspect-[16/9] bg-white">
                                <div x-show="!cover" class="grid place-items-center aspect-[16/9] rounded-xl border-2 border-dashed border-line text-sm font-medium text-ink-mute">Muqova rasmi shu yerda ko'rinadi</div>
                            </div>
                        </template>
                        <template x-if="type === 'video'">
                            <div class="mt-5">
                                <template x-if="videoId"><div class="aspect-video overflow-hidden rounded-xl border border-line bg-ink"><iframe class="size-full" :src="'https://www.youtube-nocookie.com/embed/' + videoId + '?rel=0'" title="YouTube" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div></template>
                                <template x-if="!videoId"><div class="grid place-items-center aspect-video rounded-xl border-2 border-dashed border-line text-sm font-medium text-ink-mute">YouTube videosi shu yerda ko'rinadi</div></template>
                            </div>
                        </template>

                        <div class="prose-kd mt-5" x-html="shownBody || '<p class=&quot;!text-ink-mute&quot;>…</p>'"></div>

                        <div class="mt-7 pt-5 border-t border-line text-sm font-medium text-ink-soft">
                            <span x-show="comments" x-cloak class="inline-flex items-center gap-2"><x-icon name="sms" class="size-4" /> Izohlar yoqilgan: mehmonlar ism bilan yozadi, siz tasdiqlaysiz</span>
                            <span x-show="!comments" x-cloak class="inline-flex items-center gap-2"><x-icon name="ban" class="size-4" /> Izohlar o'chirilgan</span>
                        </div>
                    </article>
                </div>
            </div>
        </aside>
    </div>
</form>
@if($post->exists)
    <form id="post-delete" method="post" action="{{ route('admin.posts.destroy', $post) }}">@csrf @method('delete')</form>
@endif
@endsection

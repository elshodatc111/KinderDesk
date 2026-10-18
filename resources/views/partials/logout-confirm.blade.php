{{-- Chiqishni tasdiqlash oynasi --}}
<div x-data="{ show: false }" @kd:confirm-logout.window="show = true" @keydown.escape.window="show = false">
    <div x-show="show" x-cloak class="modal-backdrop" @click.self="show = false" role="dialog" aria-modal="true" aria-labelledby="logout-title">
        <form method="post" action="{{ route('logout') }}" class="modal-panel !max-w-sm text-center space-y-4">
            @csrf
            <span class="mx-auto grid place-items-center size-12 rounded-full bg-marigold-50 text-marigold-600"><x-icon name="logout" class="size-6" /></span>
            <div><h3 id="logout-title" class="text-lg font-semibold">{{ __('Tizimdan chiqasizmi?') }}</h3>
                <p class="text-sm text-ink-soft mt-1">{{ __("Qayta kirish uchun telefon raqam va parol kerak bo'ladi.") }}</p></div>
            <div class="flex gap-3 justify-center"><button type="button" class="btn" @click="show = false">{{ __('Qolish') }}</button><button class="btn btn-primary">{{ __('Ha, chiqish') }}</button></div>
        </form>
    </div>
</div>

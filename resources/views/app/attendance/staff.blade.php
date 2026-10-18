@extends('layouts.app')
@section('title', 'Xodimlar davomadi')
@section('content')
<form method="get" class="mb-5"><label class="label">Sana</label><input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="input !w-44" onchange="this.form.submit()"></form>
@if($locked)<div class="banner banner-info mb-4"><x-icon name="lock" /><p>O'tgan kun davomadi faqat ko'rish uchun. Xodimlar davomadi faqat bugungi kun uchun belgilanadi.</p></div>@endif
<div x-data="staffAtt(@js($rows), '{{ $date->toDateString() }}', '{{ route('app.staff-attendance.store') }}')" class="w-full">
    <p x-show="error" x-text="error" class="banner banner-warn mb-3" x-cloak></p>
    <div class="card divide-y divide-line">
        <template x-for="r in rows" :key="r.id">
            <div class="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div><p class="font-semibold" x-text="r.name"></p><p class="text-xs font-medium text-ink-soft" x-text="r.position"></p></div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <template x-for="s in [['present','Keldi','leaf'],['late','Kechikdi','marigold'],['absent','Kelmadi','berry'],['leave',`Ta'til`,'sky']]" :key="s[0]">
                        <button type="button" @click="!locked && set(r, s[0])" :disabled="locked" class="px-3 py-1.5 rounded-full border font-semibold text-sm transition"
                            :class="r.status === s[0] ? 'border-teal-500 text-ink' : 'border-line text-ink-soft hover:border-line'"
                            :style="r.status === s[0] ? 'background: var(--color-'+s[2]+'-100)' : ''" x-text="s[1]"></button>
                    </template>
                    <input type="time" :disabled="locked" x-model="r.check_in" x-show="r.status==='present' || r.status==='late'" @change="dirty[r.id]=true; schedule()" class="input !w-28 !py-1" title="Kelish vaqti">
                </div>
                <div class="w-full flex flex-wrap items-center gap-2" x-show="['late','absent','leave'].includes(r.status)" x-cloak>
                    <input type="text" :disabled="locked" x-model="r.note" maxlength="255" @input="dirty[r.id]=true; schedule()" class="input !py-1 flex-1 min-w-48"
                        :placeholder="r.status==='leave' ? `Izoh (ixtiyoriy)` : `Sababini yozing (majburiy)`">
                    @if ($canExcuse)
                    <template x-if="['late','absent'].includes(r.status)">
                        <select :disabled="locked" x-model="r.excuse" @change="dirty[r.id]=true; schedule()" class="input !w-44 !py-1">
                            <option value="">Sabab: belgilanmagan</option>
                            <option value="excused">Sababli</option>
                            <option value="unexcused">Sababsiz (jarima)</option>
                        </select>
                    </template>
                    @else
                    <span class="badge" x-show="r.excuse" x-text="r.excuse==='excused' ? `Sababli` : `Sababsiz`"></span>
                    @endif
                </div>
            </div>
        </template>
    </div>
    <p class="help mt-3">Kechikdi va Kelmadi uchun sabab (izoh) yozilishi shart. @if($canExcuse)Sababli/sababsiz belgisini siz qo'yasiz: jarima faqat sababsiz kunlarga yoziladi.@endif Kelish vaqti <b>{{ $lateAfter }}</b> dan keyin kiritilsa, "Kechikdi" deb belgilanadi. <span x-text="status"></span></p>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    const canExcuse = @js($canExcuse);
    Alpine.data('staffAtt', (rows, date, url) => ({
        locked: @js($locked), rows, date, status: '', error: '', dirty: {}, timer: null,
        set(r, s) { r.status = s; if (['present','late'].includes(s) && !r.check_in) r.check_in = new Date().toTimeString().slice(0,5); this.dirty[r.id] = true; this.schedule(); },
        schedule() { clearTimeout(this.timer); this.status = 'Saqlanmoqda...'; this.timer = setTimeout(() => this.save(), 600); },
        async save() {
            const marks = {};
            this.rows.forEach(r => { if (this.dirty[r.id] && r.status) marks[r.id] = { status: r.status, check_in: r.check_in || null, note: r.note || '', ...(canExcuse ? { excuse: r.excuse || null } : {}) }; });
            this.dirty = {};
            if (!Object.keys(marks).length) return;
            const body = { date, marks };
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': window.KD.csrf }, body: JSON.stringify(body) });
                if (res.status === 422) { const j = await res.json(); this.error = j.message || ''; this.status = ''; rows.forEach(r => { if (r.id === j.employee) this.dirty[r.id] = true; }); return; }
                if (!res.ok) throw new Error('x'); this.error = ''; this.status = 'Saqlandi';
            } catch (e) { window.kdQueue.push({ url, body }); this.status = "Internet yo'q — qurilmada saqlandi."; }
        },
    }));
});
</script>
@endpush

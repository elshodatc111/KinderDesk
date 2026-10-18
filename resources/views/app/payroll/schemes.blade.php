@extends('layouts.app')
@section('title', 'Ish haqi sxemalari')
@section('content')
<form method="post" action="{{ route('app.payroll.schemes.update') }}" class="w-full space-y-4">@csrf @method('put')
    <div class="banner banner-info"><x-icon name="info" /><p>Bir bolaning asos oyligi <b>b = F × p</b>. F — guruh o'lchamidagi narx (Sozlamalar → Guruh narxlari). Bolalar soni n ≤ L bo'lsa yoki n 5 ga bo'linsa: <b>T(n) = n × b</b>; aks holda oxirgi 5 talikdan keyingi har bola <b>s</b> so'mdan. 30 dan keyin har bola <b>c</b> so'mdan (0 — o'chiq). Bitta plusning narxi <b>r = T(n) ÷ (n × W)</b>.</p></div>
    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>Sxema</th><th>F (so'm)</th><th>p (%)</th><th>b</th><th>L</th><th>M</th><th>s (so'm)</th><th>31+ qadami c (so'm)</th></tr></thead><tbody>
        @foreach($schemes as $key => $sc) @php $pr = $sc->params; @endphp
            <tr><td class="font-semibold">{{ __($sc->name()) }}</td><td>{{ money($sc->base()) }}</td>
                <td><input name="schemes[{{ $key }}][p]" type="number" step="0.1" min="0" max="100" value="{{ $pr['p'] }}" class="input !w-24 !py-1"></td>
                <td>{{ money($sc->perKid()) }}</td>
                <td><input name="schemes[{{ $key }}][L]" type="number" min="0" value="{{ $pr['L'] }}" class="input !w-20 !py-1"></td>
                <td><input name="schemes[{{ $key }}][M]" type="number" min="1" value="{{ $pr['M'] }}" class="input !w-20 !py-1"></td>
                <td><input name="schemes[{{ $key }}][s]" value="{{ $pr['s'] }}" inputmode="numeric" class="input !w-32 !py-1"></td>
                <td><input name="schemes[{{ $key }}][c]" value="{{ $pr['c'] }}" inputmode="numeric" class="input !w-32 !py-1"></td></tr>
        @endforeach
    </tbody></table></div></div>
    <p class="help">Administrator va KPI summalari Sozlamalar → Ish haqi bo'limida. O'zgarish faqat qayta hisoblangan (qoralama) varaqalarga ta'sir qiladi.</p>
    <button class="btn btn-primary">Saqlash</button>
</form>
@endsection

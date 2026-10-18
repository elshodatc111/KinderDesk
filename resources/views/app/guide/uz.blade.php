@extends('layouts.app')
@section('title', "Yo'riqnoma")
@section('content')
<x-guide-frame :sections="[
    'start' => 'Qayerdan boshlash', 'groups' => 'Guruhlar: asosiy, kechki, shanba', 'kids' => 'Bolalar', 'clubs' => 'To‘garaklar',
    'attendance' => 'Davomad', 'billing' => 'Bolalar to‘lovi: hisoblash usullari', 'desk' => 'Kassa va Moliya',
    'roles' => 'Lavozimlar va ruxsatlar', 'payroll' => 'Ish haqi: hisoblash usullari', 'sms' => 'SMS xabarlar', 'more' => 'Qo‘shimcha imkoniyatlar', 'plan' => 'Obuna va balans', 'faq' => 'Ko‘p beriladigan savollar',
]">
<section id="start" data-guide>
    <h2>Qayerdan boshlash</h2>
    <p>Dastlabki sozlash 10–15 daqiqa oladi. Ketma-ket bajaring:</p>
    <ol>
        <li><b>Bog‘cha ma’lumoti</b> — Sozlamalar → «Bog‘cha ma’lumoti»: nomi, telefon, manzil, logotip.</li>
        <li><b>Lavozimlar</b> — Sozlamalar → «Lavozimlar»: lavozimlarni yarating (tarbiyachi, kassir, hisobchi…) va har biri nima qila olishini belgilang.</li>
        <li><b>Guruhlar</b> — «Guruhlar» bo‘limi: nomi, oylik narx, yosh, sig‘im.</li>
        <li><b>Xodimlar</b> — «Xodimlar» bo‘limi: lavozimini tanlang, oylik kiriting. Tizimga kirishi kerak bo‘lsa, «Tizimga kirish huquqi»ni yoqing.</li>
        <li><b>Bolalar</b> — «Bolalar» bo‘limi: bola va ota-ona ma’lumoti, guruh. Oylik hisob o‘zi yuritiladi.</li>
        <li><b>To‘lov sozlamalari</b> — Sozlamalar → «Bolalar to‘lovi»: hisoblash usuli, aka-uka chegirmasi, karta/bank.</li>
    </ol>
    <div class="callout"><p>Keraksiz bo‘limni yashirish mumkin: Sozlamalar → «Bo‘limlar». Ma’lumotlar o‘chmaydi, bo‘limni istalgan vaqt qayta yoqasiz.</p></div>
</section>

<section id="groups" data-guide>
    <h2>Guruhlar: asosiy, kechki, shanba</h2>
    <p>Har bir guruhning turi bor. U ota-onadan pul qanday olinishini belgilaydi.</p>
    <table class="t">
        <tr><th>Turi</th><th>Vaqti</th><th>Pul qanday yechiladi</th></tr>
        <tr><td><b>Asosiy guruh</b></td><td>09:00–17:00, ish kunlari</td><td>Sozlamadagi hisoblash usuli bo‘yicha (oyiga bir marta, proporsional yoki davomadga qarab).</td></tr>
        <tr><td><b>Kechki guruh</b></td><td>17:00–20:00</td><td>Bola qolgan har bir kun uchun qat’iy narx. Faqat «keldi» belgilansa, bolaning hisobidan yechiladi.</td></tr>
        <tr><td><b>Shanba guruhi</b></td><td>shanba</td><td>Bir kunlik narx. Faqat bola kelgan shanba uchun yechiladi.</td></tr>
    </table>
    <p>Bola bir vaqtning o‘zida asosiy guruhda va kechki/shanba guruhida bo‘lishi mumkin. Kechki va shanba bolalarini «Davomad» sahifasidagi «Bola qo‘shish» tugmasi orqali qo‘shasiz.</p>
    <div class="callout"><p>Adashib «keldi» belgilab, keyin «kelmadi»ga o‘zgartirsangiz, o‘sha kun uchun yechilgan pul avtomatik bekor bo‘ladi. Yana «keldi» qilsangiz, qayta yechiladi.</p></div>
</section>

<section id="kids" data-guide>
    <h2>Bolalar</h2>
    <ul>
        <li><b>Bola balansi</b>: plyus — oldindan to‘langan, minus — qarz. To‘lovlar va hisoblangan summalardan hosil bo‘ladi.</li>
        <li><b>Shaxsiy narx</b> bola kartasida guruh narxini almashtiradi.</li>
        <li><b>Boshqa guruhga o‘tkazish</b> tarixni saqlaydi. Hisob qachon o‘tganiga qarab qayta hisoblanadi.</li>
        <li><b>Arxiv</b>: bola ro‘yxatdan chiqadi, tarix va qarzlar saqlanadi.</li>
        <li>Ota-onaga to‘lov va qarz haqida SMS boradi (SMS bo‘limida yoqilgan bo‘lsa).</li>
    </ul>
</section>

<section id="clubs" data-guide>
    <h2>To‘garaklar</h2>
    <p>To‘garak — alohida pullik xizmat (ingliz tili, rasm, shaxmat…). Har bir to‘garak uchun o‘qituvchi, jadval va narx belgilanadi, qatnashuvchilar alohida yig‘iladi.</p>
    <ul>
        <li><b>Oyiga bir marta</b> — yozilgan har bir bola uchun oyiga qat’iy summa. Oyning 1-sanasida va yozilganda yechiladi.</li>
        <li><b>Mashg‘ulot uchun</b> — har bir dars uchun summa. Kim kelganini belgilaysiz, faqat kelganlardan yechiladi.</li>
    </ul>
    <p>To‘garak puli bolaning balansidan alohida qator bo‘lib yechiladi, shuning uchun asosiy guruh va to‘garak summasi alohida ko‘rinadi. Ota-ona kassaga bitta summa to‘laydi.</p>
</section>

<section id="attendance" data-guide>
    <h2>Davomad</h2>
    <p>Tarbiyachi «Davomad»ni ochadi, guruh va sanani tanlaydi va belgilaydi: keldi / kelmadi / kasal. Internet bo‘lmasa, belgilar telefonda saqlanadi va aloqa paydo bo‘lganda yuboriladi.</p>
    <ul>
        <li>Shanba guruhini faqat shanba kuni belgilash mumkin.</li>
        <li>Bir bolani bir kunda turli guruhlarda belgilash mumkin (masalan, ertalab va kechqurun).</li>
    </ul>
</section>

<section id="billing" data-guide>
    <h2>Bolalar to‘lovi: hisoblash usullari</h2>
    <p>Usulni Sozlamalar → «Bolalar to‘lovi» → «Hisoblash usuli»da tanlaysiz.</p>
    <table class="t">
        <tr><th>Usul</th><th>Qanday hisoblanadi</th></tr>
        <tr><td><b>Oylik (har oy to‘liq)</b></td><td>Har oy guruhning to‘liq narxi yechiladi. Oddiy va aniq.</td></tr>
        <tr><td><b>Proporsional</b></td><td>Bola oy o‘rtasida kelsa, faqat qolgan ish kunlari uchun yechiladi.</td></tr>
        <tr><td><b>Kunlik (davomadga qarab)</b></td><td>Guruh narxi oydagi ish kunlariga bo‘linadi — bir kunlik narx chiqadi. Faqat «keldi» belgilangan kunlar yechiladi. O‘tgan oy hisobi keyingi oyning belgilangan kunida bajariladi.</td></tr>
    </table>
    <div class="example"><p><b>«Davomadga qarab» misoli.</b> Guruh narxi 600 000 so‘m, oyda 24 ish kuni → bir kun 25 000 so‘m. Bola 18 kun kelgan → 18 × 25 000 = 450 000 so‘m.</p></div>
    <p><b>Aka-uka chegirmasi</b> bir ota-onaning (telefon bo‘yicha) 2-bolasidan boshlab qo‘llanadi.</p>
</section>

<section id="desk" data-guide>
    <h2>Kassa va Moliya</h2>
    <p>Bular ikki alohida hisob. Direktorning pullari toza, kassirning kunlik tushumi esa alohida turishi uchun ajratilgan.</p>
    <table class="t">
        <tr><th></th><th>Kassa</th><th>Moliya (direktor hisobi)</th></tr>
        <tr><td>Kim ishlaydi</td><td>Kassir, administrator</td><td>Direktor, hisobchi</td></tr>
        <tr><td>Nima bor</td><td>Ota-onadan to‘lovlar, kassadan mayda xarajatlar, direktorga pul topshirish</td><td>Direktor qabul qilgan pul, xarajat, daromad, ish haqi, hisoblar o‘rtasida o‘tkazma</td></tr>
    </table>
    <h3>Pul Kassadan Moliyaga qanday o‘tadi</h3>
    <ol>
        <li>Kassir ota-onadan to‘lovni qabul qiladi: «Kassa» → «To‘lov qabul qilish». Pul kassada qoladi.</li>
        <li>Kun oxirida kassir «Direktorga topshirish»ni bosadi va summani kiritadi.</li>
        <li>Direktor «Moliya» → «Kassadan kelgan pul»da so‘rovni ko‘radi va «Pulni qabul qildim»ni bosadi (yoki sabab yozib «Rad etish»).</li>
        <li>Faqat shundan keyin summa kassadan chiqib, direktor hisobiga o‘tadi. Direktor tasdiqlamaguncha pul kassada hisoblanadi.</li>
    </ol>
    <p>Karta va bank to‘lovlari tasdiq talab qilishi mumkin (Sozlamalar → «Moliya»): kassir yoki hisobchi pul tushganini tekshirib tasdiqlaydi.</p>
    <div class="callout warn"><p>To‘lovni «o‘chirib» bo‘lmaydi. Xato to‘lov bekor qilinadi yoki qaytariladi va jurnalda saqlanadi.</p></div>
</section>

<section id="roles" data-guide>
    <h2>Lavozimlar va ruxsatlar</h2>
    <p>Lavozimlarni direktorning o‘zi yaratadi: Sozlamalar → «Lavozimlar» → «Yangi lavozim». Har bir lavozim uchun nimani ko‘rish va nimani o‘zgartirish mumkinligi katakchalar bilan belgilanadi.</p>
    <ol>
        <li>Lavozim yarating (masalan, «Metodist») va ruxsatlarni belgilang. «Hammasi» va «Hech biri» tugmalari butun blokni bir bosishda belgilaydi.</li>
        <li>Xodim qo‘shayotganda uning lavozimini tanlang.</li>
        <li>Xodim tizimga kirishi kerak bo‘lsa, «Tizimga kirish huquqi»ni yoqing, telefon va parol kiriting. Ruxsatlar uning lavozimida belgilanganidek bo‘ladi.</li>
        <li>Lavozim ruxsatlarini o‘zgartirsangiz, shu lavozimdagi barcha xodimlarda darhol o‘zgaradi.</li>
    </ol>
    <p>Kirish huquqisiz lavozimlar (oshpaz, farrosh) faqat hisob va ish haqi uchun. «Direktor» lavozimida doim hamma ruxsat bor. Xodimi bor lavozimni o‘chirib bo‘lmaydi.</p>
</section>

<section id="payroll" data-guide>
    <h2>Ish haqi: hisoblash usullari</h2>
    <table class="t">
        <tr><th>Usul</th><th>Qanday hisoblanadi</th></tr>
        <tr><td><b>Belgilangan</b></td><td>Har oy bir xil oylik.</td></tr>
        <tr><td><b>Davomadga qarab</b></td><td>Kunlik stavka × ishlagan kunlar. Stavka kiritilmasa, oylik ÷ ish kunlari olinadi.</td></tr>
        <tr><td><b>Aralash</b></td><td>Oylik + xodim biriktirilgan guruhlardagi har bir bola uchun qo‘shimcha.</td></tr>
    </table>
    <div class="example"><p><b>«Aralash» misoli.</b> Oylik 3 000 000, tarbiyachining guruhida 15 bola, har bir bola uchun 50 000 → 3 000 000 + 15 × 50 000 = 3 750 000 so‘m.</p></div>
    <p><b>Aralash usul qachon ochiladi?</b> Bu Premium nashr imkoniyati. Boshqa nashrda bo‘lsangiz va kerak bo‘lsa, qo‘llab-quvvatlashga murojaat qiling — alohida ochib berish mumkin. Usulni butun bog‘cha uchun (Sozlamalar → «Ish haqi») yoki bitta xodim kartasida alohida belgilash mumkin.</p>
    <p>Xodimlar davomadi yoqilgan bo‘lsa, kechikish uchun jarima ushlanishi mumkin (miqdori sozlamada).</p>
</section>

<section id="sms" data-guide>
    <h2>SMS xabarlar</h2>
    <p>SMS sizning Eskiz hisobingiz orqali yuboriladi. Xabar matnlari Eskizda tasdiqlanishi kerak (SMS → shablonlar → «Tasdiqlashga yuborish»). Shablon tasdiqlanmaguncha xabar ketmaydi.</p>
    <ul>
        <li>Har bir hodisa (to‘lov, qarz, tug‘ilgan kun…) uchun yuborishni yoqing va shablonni tanlang.</li>
        <li>SMS ketishi uchun serverda navbat ishchisi ishlab turishi kerak (<code>php artisan queue:work</code>).</li>
    </ul>
</section>

<section id="plan" data-guide>
    <h2>Obuna va balans</h2>
    <ul>
        <li>Yangi bog‘cha <b>14 kun bepul</b> foydalanadi. Keyin tizim «faqat ko‘rish» rejimiga o‘tadi — ma’lumotlar saqlanadi.</li>
        <li>Tariflar: 1, 3 va 12 oy. Balansni Payme orqali yoki karta rekvizitlariga o‘tkazib, chek yuborib to‘ldirasiz.</li>
        <li>Tugashiga 3 kun qolganda eslatma keladi. Obuna avtomatik yechilmaydi: muddat tugasa tizim «faqat ko‘rish» rejimiga o‘tadi, davom etish uchun direktor balansni to‘ldirib, tarifni o‘zi tanlab sotib oladi.</li>
        <li>Obuna 90 kundan ortiq uzaytirilmasa, ma’lumotlar o‘chirilishi mumkin — oldindan ogohlantiriladi.</li>
    </ul>
</section>

<section id="more" data-guide>
    <h2>Qo‘shimcha imkoniyatlar (Standard va Premium)</h2>
    <ul>
        <li><b>Ish kalendari</b> — bayram va dam olish kunlari; direktor oyni tasdiqlaydi. Barcha hisob-kitoblar shu kalendarga tayanadi.</li>
        <li><b>Oilalar va chegirmalar</b> — aka-uka bog‘lamasini administrator tasdiqlaydi; sababli kelmagan kunlar uchun chegirmani administrator kiritadi, direktor tasdiqlaydi.</li>
        <li><b>Vasiylar</b> — bola kartasida 2 tagacha vasiy: munosabat, telefonlar, asosiy to‘lovchi, SMS va olib ketish ruxsati. Ism va telefonlar shifrlab saqlanadi.</li>
        <li><b>Qora ro‘yxat</b> — yangi bola, ariza yoki xodim qo‘shilganda telefon/guvohnoma mos kelsa ogohlantirish chiqadi. Davom etish uchun «qora ro‘yxatdan o‘tish» ruxsati va sabab kerak; jurnalga yoziladi.</li>
        <li><b>Arizalar</b> — keyingi qo‘ng‘iroq sanasi, mas’ul xodim, 24 soatdan oshgan arizalar bosh sahifada qizil ogohlantirish bilan chiqadi; rad etishda sabab majburiy; manbalar bo‘yicha konversiya jadvali.</li>
        <li><b>Premium:</b> ish haqi sxemalari, almashtirish, fanlar va pedagoglar, tadbirlar, hisobot zanjiri (hisobot o‘tkazib yuborilsa oylik bonuslar qo‘shilmaydi), administrator bonusi.</li>
        <li><b>AI yordamchi va AI hisobot</b> — AI faqat tizimdan foydalanishni tushuntiradi; hisobotga faqat sonlar va foizlar yuboriladi, ism va telefon yuborilmaydi.</li>
    </ul>
</section>

<section id="faq" data-guide>
    <h2>Ko‘p beriladigan savollar</h2>
    <h3>Menyuda kerakli bo‘limni ko‘rmayapman</h3>
    <p>Yoki lavozimingizda bu ruxsat yo‘q (Sozlamalar → «Lavozimlar»ni tekshiring), yoki bo‘lim Sozlamalar → «Bo‘limlar»da yashirilgan, yoki nashringizda mavjud emas.</p>
    <h3>Ota-ona bir necha oyga oldindan to‘ladi</h3>
    <p>Butun summani kiriting. Bola balansi plyusda bo‘ladi va oylik hisoblar shundan yopiladi.</p>
    <h3>Bola asosiy guruhda ham, to‘garakda ham</h3>
    <p>To‘garak sahifasida uni yozing. To‘lov bolaning o‘sha balansidan alohida qator bo‘lib yechiladi.</p>
    <h3>Tilni qanday almashtiraman?</h3>
    <p>RU / UZ tugmasi har bir sahifaning yuqori o‘ng burchagida.</p>
</section>
</x-guide-frame>
@endsection

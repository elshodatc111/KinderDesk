# KinderDesk — bog'chalar uchun CRM (SaaS)

Bitta platforma, ko'p bog'cha (single-DB multi-tenancy, subdomen yo'q). Laravel 13 · PHP 8.3 · MySQL · Blade + Alpine + Tailwind v4 · Reverb (real-time).

> **PHP versiyasi:** PHP 8.2 da `composer install` avtomatik Laravel 12 ni, PHP 8.3+ da Laravel 13 ni o'rnatadi. Kod ikkalasida ishlaydi.

> **Eslatma:** loyiha mahalliy muhitda Laravel 12.52 bilan sinovdan o'tgan (11 ta test yashil, barcha sahifalar 200). `composer.json` Laravel 13 ni talab qiladi — `composer install`da xato chiqsa, xabarini yuboring.

## O'rnatish (Windows + XAMPP)
1. XAMPP'da Apache va MySQL ni yoqing, phpMyAdmin'da `kinderdesk` bazasini yarating (utf8mb4_unicode_ci).
2. Shu papkada:
   ```
   composer install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   npm install
   npm run build
   ```
3. Ishga tushirish: `composer dev` (server + navbat + jadval + Reverb) yoki alohida:
   `php artisan serve`, `php artisan queue:work`, `php artisan schedule:work`, `php artisan reverb:start`.
   Reverb ishlamasa tizim avtomatik 20 soniyalik "polling"ga o'tadi.
4. Productionda cron: `* * * * * php artisan schedule:run`

## Kirish
- Super admin: `998901112233` / `ChangeMe!2026` (**darhol almashtiring**) — `/admin`
- Namunaviy bog'cha: `php artisan kd:demo` → **998900000001** / `Demo12345`
- `SMS_DRIVER=log` va `APP_DEBUG=true` bo'lsa OTP kodi sahifada ko'rinadi. Haqiqiy SMS uchun `.env` va Admin → Sozlamalar'da Eskiz ma'lumotlarini kiriting.

## Asosiy qoidalar
14 kunlik sinov → so'ng faqat ko'rish; 1/3/12 oylik tariflar; Payme yoki karta + chek (tasdiqlanganda to'liq summa balansga); tugashiga 3 kun qolganda ogohlantirish; tugaganda balansdan avtomatik yechish; 90 kundan keyin o'chirish xabari (o'chirish faqat qo'lda, qaytarib bo'lmaydi); telefon/email/STIR bilan ikkinchi sinov berilmaydi.

## Tekshirish kerak bo'lgan joylar
- Narxlar — vaqtincha (Admin → Tariflar).
- Eskiz shablon holati maydoni va Payme sandbox — oflayn tekshirib bo'lmadi, o'z hisobingiz bilan sinang.
- Yuridik matnlar (oferta, maxfiylik) va soliq ma'lumotlari — o'zingiz tasdiqlang.
- Rus tili hozircha yo'q (tarjima fayli yozilmagan).
- Logotip PNG'larida shaxmat fon bor edi, shuning uchun logo SVG qilib qayta chizilgan.

## Testlar
`php artisan test`

## Yangilanishlar (v2)

- **Til:** asosiy til — rus (`APP_LOCALE=ru`), ikkinchisi — o'zbek. Tilni almashtirish: yuqoridagi `RU | UZ` tugmasi. Tarjimalar: `lang/ru.json` (kalit — o'zbekcha matn). Yangi matn qo'shgach: `php artisan kd:i18n --missing`.
- **Lavozimlar:** Sozlamalar → Lavozimlar. Direktor lavozim yaratadi va har bir ruxsatni belgilaydi.
- **Kassa va Moliya:** Kassa — kunlik tushum (kassir). Pul direktorga "topshirish" so'rovi bilan o'tadi; direktor Moliya bo'limida tasdiqlaydi yoki rad etadi.
- **Kechki va shanba guruhlari:** davomadda "keldi" belgilangan kun uchun kunlik narx yechiladi.
- **To'garaklar:** alohida to'lov (oylik yoki mashg'ulot uchun).
- **Server holati (super admin):** `/admin/tizim` — real vaqtda.
- **Yo'riqnoma:** `/app/yoriqnoma` (RU va UZ).

### Ishga tushirish
```
composer install && npm install && npm run build
php artisan migrate:fresh --seed
php artisan kd:demo            # namuna ma'lumot (ixtiyoriy)
php artisan serve
php artisan queue:work         # alohida oynada
php artisan schedule:work      # alohida oynada
```
`.env`: `DB_PASSWORD=...`, `SMS_DRIVER=log` (Eskiz shablonlari tasdiqlanguncha), `BROADCAST_CONNECTION=log`, `APP_LOCALE=ru`.

## v3 — 1-bosqich (TZ 22-bo'lim)
- **Ish kalendari** (`/app/kalendar`): bayram/dam olish kunlari, direktor oyni tasdiqlaydi; tasdiqlanmagan oyda shanba-yakshanba avtomatik dam olish. W — barcha hisoblarning asosi (`WorkCalendar`).
- **Qat'iy oylik to'lov**: asosiy guruh narxi to'liq yechiladi; faqat yangi bolaning birinchi oyi sozlama bo'yicha proporsional bo'lishi mumkin (`billing.newcomer_mode`).
- **Aka-uka chegirmasi** (barcha tariflar): oila bog'lamasini administrator tasdiqlaydi (`/app/oilalar`), chegirma keyingi oydan; foizlar/summalar sozlamada; asosiy guruh, to'garak va kechki guruhga; shaxsiy narxli bolaga yo'q.
- **Sababli kelmagan chegirmasi** (`/app/chegirmalar`): administrator kiritadi, direktor tasdiqlaydi; (oylik − aka-uka) ÷ W × kun.
- **Xodim davomati**: kechikdi/kelmadi uchun izoh majburiy; administrator sababli/sababsiz belgilaydi; jarima faqat sababsiz kunlarga, summalar sozlamada.
- Yangi migratsiya: `php artisan migrate`. Testlar: `php artisan test` (27 ta).

## v3 — 2-bosqich
- **Guruh turi (katta/kichik)**, tarbiyachi/yordamchi roli, **ish haqi sxemalari** (`/app/ish-haqi/sxemalar`): bolalar soniga (plus) bog'liq pog'onali maosh, oshpaz va administrator hisobi.
- **Almashtirish** (`/app/almashtirish`): kelmagan xodim o'rniga ishlagan xodimga o'sha kunning ulushi yoziladi.
- **Ish haqi aylanishi**: har oyning birinchi ish kuni qoralama (`payroll:draft`, 06:00), direktor tasdiqlaydi; premiya, jarima, avans, shikoyatlar.

## v3 — 3-bosqich
- **Fanlar va pedagoglar** (`/app/fanlar`, Premium): fan (pullik to'garak yoki bog'cha ichidagi dars), dars jurnali, pedagog ish haqi (har bir dars uchun yoki oylik; bir dars bir nechta guruhga o'tilsa — bitta), pullik to'garak foydasi (tushum − pedagog haqi). Xodim kartasida sxema «Pedagog» yoki lavozim «Pedagog».
- **Bir martalik tadbirlar** (`/app/tadbirlar`, Premium): qoralama → smeta (transport, chipta, ovqat, xodim kunlik haqi) → direktor tasdig'i → ro'yxatga olish → narx qotirish (jami / bolalar, yuqoriga yaxlitlanadi + ustama %) → davomat va yechim → o'tkazildi → yakun (xodim haqi ish haqiga o'tadi, farq hisoboti). Tasdiqdan keyin smeta oshsa — qayta tasdiqlash. «Pul oldindan yig'iladi» sozlamasi.
- **Guruh tadbiri bonusi** (`/app/guruh-tadbirlari`): qatnashgan xodimlar belgilanadi, direktor tasdiqlaydi; bonus faqat shu guruh xodimlariga (tarbiyachi/yordamchi uchun alohida summa). KPI «tadbirlarda ishtirok» shu yozuvlardan to'ldiriladi.
- Yangi migratsiya: `php artisan migrate`. Testlar: `php artisan test` (45 ta).

## v3 — 4-bosqich
- **Hisobot zanjiri** (`/app/hisobot-zanjiri`, Premium): direktor hisobot turlarini yaratadi (kim topshiradi, kunlik/haftalik/oylik, muddat 23:59, matn/fayl/foto-video/avtomatik); xodim topshiradi → administrator qabul qiladi yoki izoh bilan qaytaradi → administrator o'z kunlik hisobotini topshiradi (1-daraja natijalari va «topshirmaganlar» ro'yxati avtomatik biriktiriladi) → direktor qabul qiladi.
- **Bonusga ta'siri**: hisobot topshirilgan va qabul qilingan bo'lsagina hisoblanadi; oyda bitta ishchi kun o'tkazib yuborilsa, shu xodimning oy bonuslari (KPI, tadbir bonusi, administrator bonus qismlari) qo'shilmaydi, asosiy maosh saqlanadi. Direktor sabab yozib kechiradi (jurnalga). Dam olish va bayram kunlari talab qilinmaydi. Administrator xodim hisobotini `report.review_days` kundan ortiq ko'rmay qoldirsa ham bonusi to'xtaydi.
- Eslatmalar: `reports:remind` (21:55 xodimlarga ilova ichida; 00:10 direktorga kechikkanlar haqida). SMS-eslatma 7-bosqichda SMS shablonlari bilan ulanadi.
- Fayllar yopiq saqlanadi (`storage/app/reports/{bog'cha}`), faqat ruxsati bor xodimlarga beriladi.
- Yangi migratsiya: `php artisan migrate`. Testlar: `php artisan test` (54 ta).

## v3 — 5-bosqich
- **Qo'lda «faqat ko'rish»** (`/admin/bogchalar/{id}`): sabab majburiy, muddat ixtiyoriy; direktor sababni ko'radi; avtomatik holatdan ustun — to'lov kelsa ham super admin olib tashlamaguncha ochilmaydi.
- **Umrbod rejim**: tarif to'lovi olinmaydi, obuna eslatmalari/avto-yechish/90 kunlik o'chirish xabari o'tkazib yuboriladi, barcha imkoniyatlar ochiq.
- **Demo'siz bog'cha yaratish** (`/admin/bogchalar/yangi`): nom, direktor, tarif, boshlang'ich muddat yoki umrbod; telefon/email/STIR sinov reestriga yozilmaydi.
- Barcha holat o'zgarishlari sabab va kim bilan `audit_logs`'ga yoziladi. Yangi migratsiya: `php artisan migrate`. Testlar: 59 ta.

## v3 — 6-bosqich (AI)
- **AI yordamchi** (`/app/ai`, Standard va Premium): faqat tizimdan foydalanish bo'yicha savol-javob (yo'riqnoma asosida). Bog'cha ma'lumotlariga kirmaydi; savoldagi telefon/karta/hujjat/email avtomatik yashiriladi. Kunlik savol limiti va oylik token limiti bor.
- **AI hisobot** (`/app/ai/hisobotlar`): Premium — kunlik, Standard — haftalik (dushanba). Faqat PII'siz faktlar paketi yuboriladi (sonlar, foizlar, «Xodim-{id}»). Javobdagi raqamlar paket bilan tekshiriladi; mos kelmasa bir marta qayta so'raladi, so'ng «ishonchsiz» belgilanadi. `ai:daily` 00:05 da ishlaydi.
- **Super admin** (`/admin/ai`): API kalit shifrlangan holda saqlanadi va ko'rsatilmaydi; modellar, narxlar, limitlar, xarajat statistikasi, bog'cha bo'yicha AI o'chirish/limit. Har chaqiruv (yuborilgan matn bilan) `ai_usage`'ga yoziladi.
- Yangi migratsiya: `php artisan migrate`; mavjud o'rnatishda tariflar uchun: `php artisan db:seed --class=EditionSeeder`. Testlar: 71 ta.

## v3 — 7-bosqich (yakuniy)
- **Vasiylar** (`guardians2`, Standard/Premium): bola kartasida 2 tagacha vasiy — munosabat, telefonlar, asosiy to'lovchi, SMS, olib ketish. Ism va telefonlar shifrlangan (`kid_guardians`), qidiruv uchun xesh. Eski `parent_*` maydonlari 1-vasiy bilan sinxron saqlanadi (bir versiya o'qish uchun). Start tarifida bitta vasiy.
- **Qora ro'yxat** (`/app/qora-royxat`, `blacklist`): oila va xodim; telefon va guvohnoma xeshi bo'yicha tekshiruv (bola, ariza, xodim qo'shishda). Natija — ogohlantirish; davom etish `blacklist.override` ruxsati + sabab bilan, `audit_logs`'ga yoziladi. Ro'yxat faqat shu bog'chada. Xodim arizasi rad etilganda qora ro'yxatga qo'shish tanlovi.
- **Arizalar**: keyingi qo'ng'iroq sanasi, mas'ul xodim, 24 soatdan oshgan «Yangi» arizalar bosh sahifada qizil ogohlantirish, rad etishda sabab majburiy, manbalar bo'yicha konversiya jadvali, «bolaga/xodimga aylantirish» arizani avtomatik «qabul qilindi»ga o'tkazadi.
- **Administrator bonusi** endi alohida `admin_bonus` imkoniyati (Premium). Eskirgan `billing_prorate`/`billing_per_day` olib tashlandi.
- **Tariflar matritsasi** `config/kinderdesk.php` da; mavjud o'rnatishda `php artisan migrate` yangi kalitlarni tariflarga qo'shadi (super admin qo'lda o'zgartirganlari saqlanadi).
- Yangi migratsiyalar: `php artisan migrate`. Testlar: 80 ta.

### Ma'lum cheklovlar
- Hisobot muddati eslatmalari SMS orqali yuborilmaydi (faqat ilova ichida); SMS shablonlariga ulash keyingi qadam.
- Eskiz va Payme sandbox oflayn tekshirilmagan; Claude API kalitini Admin → AI'da kiriting va «Ulanishni sinab ko'rish» ni bosing.

## v3 — 8-bosqich (tuzatishlar va yangi bo'limlar)
- **Ulanishlar** (Eskiz, Payme) `.env` ga yozilmaydi: super admin → Sozlamalar → «Ulanishlar». Kalitlar bazada shifrlangan, qayta ko'rsatilmaydi. `.env` qiymatlari faqat zaxira sifatida ishlaydi.
- **Qo'llab-quvvatlash**: bitta telefon va bitta Telegram (Sozlamalar → «To'lov va aloqa»). Sotuv sahifasi footer'i va bog'chalar yon menyusida ko'rinadi.
- **Sayt matnlari**: «Ko'p beriladigan savollar» (qo'shish, tahrirlash, o'chirish, tartib) va Ommaviy oferta / Maxfiylik / Foydalanish shartlari (UZ+RU, namuna matn tayyor, «namunani qaytarish» tugmasi) — Sozlamalar → «Sayt».
- **Super admin profili**: ism, email, til, parolni almashtirish (`/admin/profil`).
- **Obuna avtomatik yechilmaydi**. Muddat tugasa tizim «faqat ko'rish» rejimiga o'tadi; direktor balansni to'ldirib, tarifni o'zi sotib oladi.
- **Ochiq ariza havolasi** bog'cha ochilganda avtomatik yaratiladi, o'zgartirib bo'lmaydi.
- **Davomad**: «Bugun» (umumiy natija + guruh kartalari) va «Kalendar» (oylik jadval, kunni bossangiz — guruhlar kesimida). Davomad **faqat bugungi kun** uchun belgilanadi (bolalar, xodimlar, to'garak); o'tgan kunlar faqat ko'rish uchun.
- **Moliya**: «Qo'shimcha mablag' kiritish», «Hisob raqamlari» (direktorning karta/bank raqamlari), «Kassaga naqd pul berish» (Moliya → Kassa, faqat naqd), «Subsidiya» (alohida hisoblanadi, olinganda moliyaga kirim bo'ladi). **Kassadan xarajat faqat naqd** pul bilan; karta/bank tushumi direktorga topshiriladi.
- **AI yordamchi jonli**: javob oqim (stream) bilan so'z-so'z chiqadi.
- **Bildirishnomalar** foydalanuvchi ruxsatlariga qarab ko'rsatiladi (ruxsati yo'q bildirishnoma ko'rinmaydi va hisoblanmaydi).
- **Statistika** bo'limi (`stats.view` ruxsati): kunlik / haftalik / oylik / yillik diagrammalar — davomad, kirim-chiqim, to'lov usullari, xarajat toifalari, bolalar harakati, guruhlar to'lishi, xodimlar davomadi, arizalar, subsidiya.
- Yangilash: `php artisan migrate` (yangi jadvallar: `finance_accounts`, `subsidies`; `stats.view` ruxsati lavozimlarga qo'shiladi), `npm run build`.

## v3 — 9-bosqich (Telegram, xavfsizlik, sog'liq, import, zaxira API)
- **Telegram bot (har bir bog'cha o'zinikini)**: direktor `Telegram bot` → «Sozlamalar»da @BotFather tokenini kiritadi (bazada shifrlangan). Ota-onaga har bir vasiy uchun shaxsiy havola/QR beriladi (guruh sahifasida); bir vasiyga bir nechta akkaunt ulanishi mumkin, keraklisini o'chirish mumkin. Telefon raqam so'ralmaydi. Rozilik botda `/start` dan keyin olinadi, rozilikdan oldin xabar yuborilmaydi.
- **Avtomatik xabarlar**: davomad «keldi» / «kelmadi» (barcha ulangan vasiylarga; «ketdi» yo'q) va to'lov qabul qilindi (faqat to'lovchi vasiyga). Hodisalar va matnlar direktor tomonidan yoqiladi/tahrirlanadi. «Ko'chirish va ulashishni taqiqlash» (`protect_content`) sozlamasi bor.
- **Tarbiyachi/oshpaz**: shaxsiy havola orqali botga ulanadi, rasm/video/matn yuboradi → guruh tanlaydi → tasdiqlaydi → `copyMessage` bilan ota-onalarga boradi (fayl serverda saqlanmaydi). Yuborilgandan keyin 30 daqiqa ichida o'chirish mumkin. Admin/direktor veb orqali ham e'lon yuboradi.
- **Rejim (super admin → Telegram)**: `getUpdates` (XAMPP: `telegram-worker.bat` ni oching yoki `php artisan tg:run`) yoki `Webhook` (Linux, ochiq HTTPS `APP_URL`; sarlavhadagi maxfiy kalit tekshiriladi). Navbat soniyasiga ~25 xabar. Worker o'chiq bo'lsa ham `schedule:run` (har daqiqa) xabarlarni yuboradi. Serverda: `php artisan tg:run` ni supervisor/systemd bilan doim yoqiq tuting (webhook rejimida ham navbat uchun).
- **2 bosqichli tasdiq (Telegram)**: direktor va super admin uchun. Super admin → Telegram bo'limida *platforma boti* tokeni kiritilgach majburiy bo'ladi. 6 xonali kod (5 daqiqa, 3 urinish), 8 ta zaxira kod, qurilmani 30 kunga eslab qolish. Direktorni super admin tenant sahifasidan tiklaydi; super admin uchun serverda: `php artisan kd:2fa-reset 998901112233`. Super admin bog'cha kabinetiga kirganda (impersonation) talab qilinmaydi.
- **Rozilik jurnali** (super admin → Rozilik jurnali, CSV): ro'yxatdan o'tishda va kirishda yozib boriladi. Yangi versiya faqat Sozlamalar → Sayt → matn saqlashda «qayta rozilik talab qilish» belgilansa chiqadi.
- **Qo'llab-quvvatlash ish vaqti** (Sozlamalar → To'lov va aloqa): sotuv sahifasi va yon menyuda «Hozir ishlayapmiz» holati (Asia/Tashkent). **Video yo'riqnoma**: super admin YouTube havolalarini boshqaradi, bog'cha `Video yo'riqnoma` sahifasida ko'radi (youtube-nocookie).
- **Sog'liq va xavfsizlik** (bola kartasi → «Sog'liq»): allergiya, kasallik, dori, shifokor — bazada shifrlangan, AI'ga yuborilmaydi; barcha xodimlar ko'radi, `kids.manage` tahrirlaydi. Davomadda allergiya belgisi. Olib ketishga ruxsat etilgan shaxslar ro'yxati.
- **Excel/CSV eksport faqat direktor (owner)** va super admin uchun; boshqa lavozimga berib bo'lmaydi. **Import** (Import bo'limi) faqat direktor: shablon yoki boshqa tizimdan CSV, oldindan ko'rish, tasdiqlash, telefon bo'yicha takror va qora ro'yxat tekshiruvi. Eski SMART_KIDS_WEB bazasidan to'g'ridan-to'g'ri import yo'q (jadval tuzilmasi ma'lum emas) — uni CSV'ga eksport qilib «Boshqa tizimdan» rejimida yuklang.
- **Zaxira API**: super admin → Zaxira. `POST /api/backup/request` (login+parol, kuniga bitta ish) → `GET /api/backup/{job}/download` (tayyor bo'lguncha 202; fayl 24 soatdan keyin o'chadi). Alohida xeshlangan login/parol, IP ro'yxati (CIDR), cheklov, audit. `mysqldump` bo'lmasa PHP dumper ishlaydi. **Muhim:** `APP_KEY` nusxaga kirmaydi — uni alohida va xavfsiz saqlang, usiz shifrlangan maydonlarni tiklab bo'lmaydi.
- **Dashboard** rolga qarab kuchaytirildi (davomad belgilanmagan guruhlar, tasdiq kutayotgan to'lovlar, qarzdorlar, Telegram holati).
- Yangilash: `php artisan migrate` (3 ta yangi migratsiya), `npm install`, `npm run build`, `.env`: `KD_ENFORCE_SECURITY=true` (standart yoqiq).
- Qilinmagan (ataylab): ota-onadan onlayn to'lov, soliq/fiskal, ko'p filial, QR/NFC/yuz bilan davomad, mobil ilova, «ketdi» xabari.

## v3 — 9.1 (qo'shimcha tuzatishlar)
- **Guruh xodimlari**: tarbiyachi va yordamchi tarbiyachi faqat bitta guruhda bo'ladi (band bo'lsa ro'yxatda chiqmaydi, server ham rad etadi); guruhga faqat shu ikki lavozim biriktiriladi.
- **To'garak o'qituvchisi**: faqat tarbiyachi, yordamchi tarbiyachi va pedagog lavozimidagilar.
- **Jadvallar**: hammasida avtomatik «№» ustuni (CSS hisoblagich; o'chirish uchun jadvalga `table-plain` klassi).
- **Menyu**: 9 ta yig'iladigan bo'lim (Bolalar, Faoliyat, Xodimlar, Moliya, Tahlil, Aloqa, Tizim, Yordam); faol bo'lim ochiq turadi, qolganlari brauzerda eslab qolinadi. Yangilash: `npm run build`.

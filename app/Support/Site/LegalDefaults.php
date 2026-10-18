<?php

namespace App\Support\Site;

/** Huquqiy matnlar namunasi (Markdown). [Kvadrat qavs] ichidagilarni o'z rekvizitlaringiz bilan almashtiring. Yurist bilan kelishing. */
class LegalDefaults
{
    public static function get(string $page, string $locale): string
    {
        return self::TEXTS[$page][$locale] ?? self::TEXTS[$page]['uz'] ?? '';
    }

    private const TEXTS = [
        'oferta' => [
            'uz' => <<<'MD'
Ushbu hujjat O'zbekiston Respublikasi Fuqarolik kodeksining shartnoma tuzish, ommaviy taklif (oferta) va uni qabul qilish (aksept) to'g'risidagi normalariga muvofiq rasmiy ommaviy oferta hisoblanadi.

## 1. Umumiy qoidalar va atamalar
1.1. Ushbu ommaviy oferta (keyingi o'rinlarda — «Oferta») o'zini o'zi band qilgan shaxs sifatida ro'yxatdan o'tgan [ISM FAMILIYA OTASINING ISMI] (JShShIR: [JShShIR], o'zini o'zi band qilganlik guvohnomasi №: [GUVOHNOMA RAQAMI]; keyingi o'rinlarda — «Ijrochi») tomonidan «KinderDesk» onlayn xizmatidan foydalanishni istagan har qanday shaxsga shartnoma tuzish taklifini bildiradi.
1.2. **Xizmat (KinderDesk)** — Ijrochiga tegishli, internet orqali masofadan taqdim etiladigan bulutli dasturiy ta'minot (SaaS). U xususiy bog'cha va maktabgacha ta'lim tashkilotlariga bolalar, guruhlar, davomad, to'lovlar, ish haqi, arizalar va shu kabi ichki hisob-kitob ishlarini yuritishda yordam beradi.
1.3. **Buyurtmachi** — Ofertani qabul qilgan xususiy bog'cha, maktabgacha ta'lim tashkiloti (yuridik shaxs yoki yakka tartibdagi tadbirkor) yoki uning vakili. Xizmat tadbirkorlik faoliyati uchun mo'ljallangan; jismoniy shaxs shaxsiy, oilaviy ehtiyojlar uchun foydalanuvchi sifatida ro'yxatdan o'tmaydi.
1.4. **Aksept** — Buyurtmachining Xizmatda ro'yxatdan o'tishi, sinov muddatini faollashtirishi yoki balansni to'ldirishi orqali Oferta shartlarini to'liq va so'zsiz qabul qilishi. Ro'yxatdan o'tishda Oferta va Maxfiylik siyosati bilan tanishganlik va ularga rozilik belgisi qo'yiladi; rozilik vaqti, versiyasi va foydalanuvchi Xizmatda yozib boriladi.
1.5. **Shaxsiy kabinet** — Buyurtmachi uchun ajratilgan, login va parol orqali kiriladigan, faqat shu Buyurtmachi ma'lumotlari saqlanadigan bo'lim.
1.6. **Obuna** — tanlangan nashr (Start, Standard, Premium) va davr uchun Xizmatdan foydalanish huquqi.
1.7. **Balans** — Buyurtmachi Ijrochiga to'lagan va hali obunaga sarflanmagan mablag'.

## 2. Shartnoma predmeti
2.1. Ijrochi Buyurtmachiga Xizmatdan tanlangan tarif bo'yicha masofadan foydalanish uchun oddiy (eksklyuziv bo'lmagan), boshqa shaxsga berib bo'lmaydigan huquqni beradi. Buyurtmachi esa Xizmat uchun to'lovni amalga oshiradi.
2.2. Imkoniyatlar to'plami tanlangan nashr bilan belgilanadi. Nashrlar, ularning tarkibi va amaldagi narxlar Ijrochining veb-saytida e'lon qilinadi.
2.3. Xizmat «Ijrochi tomonidan taqdim etilgan holda» ko'rsatiladi. Ijrochi uning barqaror ishlashi uchun oqilona texnik choralarni ko'radi; rejali texnik ishlar haqida iloji boricha oldindan Xizmat ichida xabar beradi.
2.4. Xizmat bog'cha ichidagi hisob-kitob vositasidir. U davlat, soliq yoki fiskal tizimlariga ulanmagan, fiskal chek chiqarmaydi va soliq hisobotini topshirmaydi. Bu majburiyatlar Buyurtmachining o'zida qoladi.

## 3. Shartnomaning tuzilishi
3.1. Shartnoma Aksept qilingan paytdan boshlab tuzilgan hisoblanadi va yozma shaklga teng elektron shaklda amal qiladi. Oferta matni Ijrochining veb-saytida doimiy ochiq turadi.
3.2. Buyurtmachi ro'yxatdan o'tishda to'g'ri va to'liq ma'lumot berishi, hamda hisobni ochish uchun vakolatga ega ekanini tasdiqlaydi.
3.3. Ijrochi quyidagi hollarda hisobni ro'yxatdan o'tkazishni rad etishi yoki bloklashi mumkin: yolg'on ma'lumot berilganda; Xizmat qonunga zid maqsadda ishlatilganda; to'lov bo'yicha muammo bo'lganda; Ofertaning muhim shartlari buzilganda.

## 4. Sinov muddati va obuna
4.1. Yangi Buyurtmachiga Xizmat bilan tanishish uchun 14 (o'n to'rt) kunlik bepul sinov beriladi. Sinov bir telefon raqami va bir STIR/JShShIR uchun bir marta beriladi.
4.2. Sinov yoki to'langan obuna muddati tugagach, Xizmat avtomatik «faqat ko'rish» rejimiga o'tadi: kiritilgan ma'lumotlar saqlanadi va ko'rish mumkin, yangi yozuv kiritish va tahrirlash cheklanadi.
4.3. Obuna tugashiga 3 (uch) kun qolganda Ijrochi Buyurtmachining mas'ul shaxsini (direktorni) Xizmat ichidagi bildirishnoma va SMS orqali ogohlantiradi.
4.4. Obuna avtomatik yangilanmaydi, bank kartasidan mablag' avtomatik yechilmaydi. Buyurtmachi tarifni o'zi tanlaydi va balansdagi mablag' hisobidan xarid qiladi.

## 5. To'lov va hisob-kitoblar
5.1. Hisob-kitoblar O'zbekiston Respublikasi milliy valyutasi — so'mda amalga oshiriladi. Ijrochi o'zini o'zi band qilgan shaxs bo'lib, qo'shilgan qiymat solig'i to'lovchisi emas; narxlar QQSsiz ko'rsatiladi.
5.2. Balans Xizmatda ko'rsatilgan Payme to'lov tizimi orqali yoki Ijrochining bank kartasiga / hisobvarag'iga o'tkazma qilib va to'lov chekini Xizmat orqali yuborish yo'li bilan to'ldiriladi. Boshqa to'lov usullari qo'shilsa, ular veb-saytda e'lon qilinadi. O'tkazma chekini Ijrochi tasdiqlagach, mablag' balansga to'liq summada qo'shiladi.
5.3. Buyurtmachining so'roviga ko'ra Ijrochi to'lov tasdig'ini qonunchilikda belgilangan tartibda (jumladan, o'zini o'zi band qilgan shaxsning soliq ilovasi orqali elektron chek yoki hisob-faktura) taqdim etadi.
5.4. Ijrochi tarif narxlarini o'zgartirish huquqiga ega. O'zgarish veb-saytda e'lon qilingan paytdan boshlab kuchga kiradi va avval to'langan obuna davri uchun narx o'zgarmaydi.
5.5. Xizmat ko'rsatilgan (foydalanilgan) davr uchun to'lov qaytarilmaydi, qonunda nazarda tutilgan hollar bundan mustasno.
5.6. Obunaga sarflanmagan balans qoldig'i Buyurtmachining yozma so'roviga ko'ra, so'rov kelgan kundan boshlab 15 (o'n besh) bank kuni ichida u ko'rsatgan hisobvaraqqa qaytariladi. Bank xizmat haqi (bo'lsa) qaytariladigan summadan ushlanishi mumkin.
5.7. Buyurtmachi to'lov rekvizitlarini to'g'ri ko'rsatishi va chekda summa, sana hamda to'lovchi aniq ko'rinishini ta'minlashi kerak. Noto'g'ri o'tkazilgan to'lovni aniqlash uchun Ijrochi qo'shimcha ma'lumot so'rashi mumkin.

## 6. Tomonlarning huquq va majburiyatlari
6.1. Ijrochi quyidagilarga majbur: Xizmatni Ofertada belgilangan hajmda taqdim etish; Buyurtmachi ma'lumotlarini ushbu Oferta va qonunchilikka muvofiq himoya qilish; Buyurtmachining murojaatlariga Xizmatda ko'rsatilgan ish vaqtida va muddatda javob berish.
6.2. Ijrochi quyidagi huquqlarga ega: Xizmatni takomillashtirish, yangilash va rejali texnik ishlarni o'tkazish; Ofertani 13-bo'limda belgilangan tartibda o'zgartirish; Ofertani buzgan Buyurtmachining hisobini cheklash yoki bloklash.
6.3. Buyurtmachi quyidagilarga majbur: Xizmatni faqat qonuniy maqsadlarda ishlatish; hisob (login, parol) va 2 bosqichli tasdiq vositalarining sir saqlanishini ta'minlash; xodimlariga ruxsatlarni to'g'ri berish; Xizmatga kiritilgan ma'lumotlarning to'g'riligi va qonuniyligi uchun javob berish.
6.4. Buyurtmachi Xizmatga zarar yetkazadigan harakatlarni (kodni buzish, ruxsatsiz kirish, ommaviy so'rov bilan tizimni ishdan chiqarish, boshqa Buyurtmachilar ma'lumotiga kirishga urinish) qilmaydi va Xizmatni uchinchi shaxslarga qayta sotmaydi.
6.5. Buyurtmachi o'zining hisobi orqali amalga oshirilgan barcha harakatlar uchun, jumladan xodimlari harakati uchun, javobgar.

## 7. Shaxsga doir ma'lumotlar va maxfiylik
7.1. **Rollar.** Buyurtmachi Xizmatga kiritgan bolalar, ota-onalar (vasiylar) va xodimlar shaxsga doir ma'lumotlarini qayta ishlash maqsad va vositalarini belgilaydi, shuning uchun u ma'lumotlar operatori hisoblanadi. Ijrochi esa ma'lumotlarni faqat Buyurtmachi nomidan va uning ko'rsatmalariga muvofiq, Xizmatni taqdim etish doirasida qayta ishlaydi (ishlov beruvchi) va ularni o'z manfaatlari yoki boshqa maqsadlarda ishlatmaydi.
7.2. **Buyurtmachining majburiyatlari.** Buyurtmachi O'zbekiston Respublikasining «Shaxsga doir ma'lumotlar to'g'risida»gi qonuniga muvofiq: ma'lumotlar sub'yektlaridan (kichik yoshdagi bolalar uchun — ularning ota-onasi yoki qonuniy vakilidan) qayta ishlashga rozilikni oladi va uni isbotlay oladigan shaklda saqlaydi; sog'liq haqidagi ma'lumotlar uchun rozilik yozma shaklda bo'lishi kerakligini hisobga oladi; talab qilingan hollarda ma'lumotlar bazasini qonunchilikda belgilangan tartibda ro'yxatdan o'tkazadi; faqat zarur ma'lumotlarni kiritadi.
7.3. **Ijrochining majburiyatlari.** Ijrochi: (a) ma'lumotlarni maxfiy saqlaydi va qonunda nazarda tutilgan hollardan tashqari uchinchi shaxslarga bermaydi; (b) har bir Buyurtmachi ma'lumotini boshqalardan mantiqan ajratilgan holda saqlaydi; (c) nozik maydonlarni (jumladan sog'liq, olib ketuvchilar, kontakt ma'lumotlari) bazada shifrlangan holda saqlaydi; (d) kirishni ruxsatlar bilan cheklaydi, direktor va platforma boshqaruvchisi hisobini Telegram orqali 2 bosqichli tasdiq bilan himoyalaydi; (e) jurnal (log) yozuvlarida shaxsiy ma'lumotlarni niqoblaydi; (f) biometrik ma'lumotlarni (yuz, barmoq izi va shu kabi) yig'maydi va saqlamaydi; (g) ma'lumotlar xavfsizligiga zarar yetgan holat aniqlansa, Buyurtmachini iloji boricha tezroq, har holda 72 soat ichida xabardor qiladi.
7.4. **Saqlash joyi.** Ijrochi Buyurtmachi ma'lumotlarini qonunchilik talablariga muvofiq O'zbekiston Respublikasi hududida joylashgan serverlarda saqlaydi.
7.5. **Ijrochi xodimlarining kirishi.** Ijrochi (platforma boshqaruvchisi) Buyurtmachining bolalar va xodimlar ma'lumotlarini odatiy tartibda ko'ra olmaydi. Texnik yordam ko'rsatish uchun Buyurtmachi roziligi bilan vaqtinchalik «Yordam rejimi» ishlatilishi mumkin; bunday kirishlar jurnalga yoziladi.
7.6. **Sun'iy intellekt.** Xizmatdagi sun'iy intellekt yordamchisiga bolalar va xodimlarning ismi, telefon raqami, hujjat va sog'liq ma'lumotlari yuborilmaydi; unga faqat umumlashtirilgan raqamlar beriladi, xodimlar shartli belgi bilan ko'rsatiladi.
7.7. **Uchinchi shaxslar.** Xizmat ishlashi uchun quyidagi tashqi xizmatlar ishlatilishi mumkin: SMS provayderi (Eskiz yoki boshqa), Telegram (Buyurtmachi o'zi ulagan bot orqali ota-onalarga xabar yuborish), to'lov tizimi (Payme). Ularga faqat shu xizmatni bajarish uchun zarur ma'lumot uzatiladi. Telegram orqali xabar olish ota-onaning o'z roziligi bilan boshlanadi va u istalgan vaqtda botni to'xtatib, bog'lanishni uzishi mumkin. Buyurtmachi ota-onalarni Telegram orqali ma'lumot uzatilishi haqida xabardor qilishi va tegishli rozilikni olishi kerak.
7.8. **Ma'lumotlarni ko'chirish va o'chirish** 10-bo'limda belgilangan.

## 8. SMS va Telegram xabarlari
8.1. SMS xabarlar Buyurtmachining o'zi ulagan yoki platformaga ulangan SMS provayder orqali yuboriladi. Matn shablonlari provayder tasdig'idan o'tgach ishlaydi, SMS xarajati amaldagi shartlarga muvofiq to'lanadi.
8.2. Telegram bot Buyurtmachi tomonidan o'z tokeni bilan ulanadi va Buyurtmachi nomidan ishlaydi. Xabar mazmuni, yuboriladigan hodisalar va matnlar uchun Buyurtmachi javobgar. Ijrochi xabarlarning Telegram tomonidan yetkazilishiga kafolat bermaydi.
8.3. Buyurtmachi xabarlarda qonunga zid, haqoratli yoki reklama xarakteridagi ruxsatsiz mazmun yubormaydi.

## 9. Intellektual mulk
9.1. Xizmat, uning dasturiy kodi, dizayni, nomi va hujjatlariga bo'lgan barcha huquqlar Ijrochiga tegishli. Ushbu Oferta Buyurtmachiga ularga egalik huquqini bermaydi, faqat foydalanish huquqini beradi.
9.2. Buyurtmachi Xizmatga kiritgan ma'lumotlar (bolalar, xodimlar, moliyaviy yozuvlar va h.k.) Buyurtmachiga tegishli bo'lib qoladi.
9.3. Buyurtmachi Xizmatni nusxalash, o'zgartirish, dekompilyatsiya qilish yoki undan o'xshash mahsulot yaratish uchun foydalana olmaydi.

## 10. Ma'lumotlarni saqlash, ko'chirish va o'chirish
10.1. Obuna muddati tugagandan keyin Buyurtmachi ma'lumotlari Xizmat serverlarida 90 (to'qson) kalendar kun saqlanadi va bu davrda «faqat ko'rish» rejimida mavjud bo'ladi.
10.2. Agar 90 kun ichida obuna qayta faollashtirilmasa, Ijrochi Buyurtmachi ma'lumotlarini serverlardan qayta tiklab bo'lmaydigan tarzda o'chirishga haqli. Zaxira nusxalar belgilangan muddatda (24 soatdan ortiq bo'lmagan ichki zaxira fayllari) avtomatik o'chiriladi.
10.3. Buyurtmachi istalgan vaqtda o'z ma'lumotlarini Xizmatda mavjud bo'lgan eksport imkoniyati (Excel/CSV) orqali ko'chirib olishi yoki hisobini yopib, barcha ma'lumotlarni o'chirishni Ijrochidan yozma so'rashi mumkin. So'rov bajarilgach, ma'lumotlarni qaytarib bo'lmaydi.
10.4. Eksport imkoniyati Buyurtmachining direktori (hisob egasi) uchun ochiq; ushbu ma'lumotlarni keyinchalik saqlash va himoya qilish uchun Buyurtmachi javobgar.

## 11. Tomonlarning javobgarligi
11.1. Tomonlar Ofertani buzganlik uchun O'zbekiston Respublikasi qonunchiligiga muvofiq javobgar bo'ladilar.
11.2. Ijrochi quyidagilar uchun javobgar emas: internet provayderlari, hosting, SMS provayderi, Telegram, to'lov tizimi va boshqa uchinchi tomon xizmatlaridagi uzilishlar; Buyurtmachi yoki uning xodimlarining noto'g'ri harakati, noto'g'ri kiritilgan ma'lumot yoki parol oshkor bo'lishi; Buyurtmachi Xizmat asosida qabul qilgan qarorlarning moliyaviy yoki boshqa natijalari; Xizmat hisob-kitoblarini (ish haqi, qarz, chegirma) Buyurtmachi tekshirmasdan qo'llashi.
11.3. Ijrochining Ofertadan kelib chiqadigan umumiy javobgarligi, qasddan yoki o'ta beparvolik bilan yetkazilgan zarar hamda qonun bilan chegaralab bo'lmaydigan hollardan tashqari, Buyurtmachi oxirgi 3 (uch) oy ichida Xizmat uchun amalda to'lagan summadan oshmaydi.
11.4. Buyurtmachi o'zi kiritgan ma'lumotlarning qonuniyligi va ota-onalar, xodimlar roziligi yuzasidan uchinchi shaxslar da'volari uchun javobgar bo'lib, Ijrochini bunday da'volardan ozod qiladi.

## 12. Fors-major
12.1. Tomonlar o'z nazoratidan tashqaridagi favqulodda holatlar (tabiiy ofat, urush, davlat organlarining qarorlari, keng ko'lamli energiya yoki aloqa uzilishlari, kiberhujumlar va h.k.) tufayli majburiyatni bajara olmasalar, shu holat davom etgan muddatga javobgarlikdan ozod bo'ladilar va ikkinchi tomonni iloji boricha tezroq xabardor qiladilar.

## 13. Ofertani o'zgartirish
13.1. Ijrochi Ofertani bir tomonlama o'zgartirishi mumkin. Yangi tahrir veb-saytda e'lon qilinadi va Xizmat ichida foydalanuvchilarga ko'rsatiladi; u e'lon qilingandan 10 (o'n) kun o'tgach kuchga kiradi, narx o'zgarishi esa 5.4-bandga muvofiq.
13.2. Yangi tahrir kuchga kirgach, foydalanuvchidan yangi versiyaga qayta rozilik so'ralishi mumkin. Rozi bo'lmagan Buyurtmachi Xizmatdan foydalanishni to'xtatib, 10.3-bandga muvofiq ma'lumotlarini olishi va 5.6-bandga muvofiq balans qoldig'ini qaytarib olishi mumkin.

## 14. Muddat, bekor qilish va ijrochi o'zgarishi
14.1. Shartnoma Aksept paytidan kuchga kiradi va Buyurtmachi Xizmatdan foydalanishni to'xtatguncha yoki hisobi yopilguncha amal qiladi.
14.2. Buyurtmachi istalgan vaqtda Xizmatdan foydalanishni to'xtatishi mumkin. Ijrochi Ofertani jiddiy buzgan Buyurtmachining hisobini bloklashi va shartnomani bekor qilishi mumkin.
14.3. Ijrochi faoliyat shakli o'zgarsa (masalan, yakka tartibdagi tadbirkor yoki yuridik shaxsga o'tsa), Oferta bo'yicha huquq va majburiyatlar yangi shaklga o'tadi; bu haqida Buyurtmachilarga veb-saytda e'lon qilinadi. Buyurtmachining ma'lumotlari va to'langan obunasi saqlanadi.

## 15. Nizolarni hal qilish va qo'llaniladigan huquq
15.1. Oferta O'zbekiston Respublikasi qonunchiligi bo'yicha tushuniladi va qo'llaniladi.
15.2. Nizolar avval muzokaralar yo'li bilan hal etiladi. Da'vo (shikoyat) yozma yoki elektron shaklda yuboriladi va 15 (o'n besh) kalendar kun ichida ko'rib chiqiladi.
15.3. Kelishuvga erishilmasa, nizo O'zbekiston Respublikasi qonunchiligiga muvofiq vakolatli sudda (iqtisodiy sud yoki fuqarolik ishlari bo'yicha sud) hal etiladi.

## 16. Yakuniy qoidalar
16.1. Ofertaning biror bandi haqiqiy emas deb topilsa, bu boshqa bandlarning kuchiga ta'sir qilmaydi.
16.2. Oferta o'zbek va rus tillarida e'lon qilinadi; tafovut bo'lsa, o'zbekcha matn asosiy hisoblanadi.
16.3. Ijrochi bilan aloqa: telefon, Telegram va elektron pochta ma'lumotlari veb-saytning «Aloqa» bo'limida ko'rsatilgan.

## 17. Ijrochi rekvizitlari
- Ijrochi: o'zini o'zi band qilgan shaxs [ISM FAMILIYA OTASINING ISMI]
- JShShIR: [14 xonali JShShIR]
- O'zini o'zi band qilganlik guvohnomasi №: [GUVOHNOMA RAQAMI]
- Bank: [BANK NOMI]
- Bank kartasi: [KARTA RAQAMI]
- Hisobvaraq (bo'lsa): [HISOBVARAQ RAQAMI]
- Telefon: [TELEFON]
- Elektron pochta: [E-MAIL]
- Veb-sayt: [SAYT MANZILI]
- Oferta tahriri sanasi: [SANA]
MD,
            'ru' => <<<'MD'
Настоящий документ является официальной публичной офертой в соответствии с нормами Гражданского кодекса Республики Узбекистан о заключении договора, публичном предложении (оферте) и его принятии (акцепте).

## 1. Общие положения и термины
1.1. Настоящая публичная оферта (далее — «Оферта») является предложением заключить договор, адресованным любому лицу, желающему пользоваться онлайн-сервисом «KinderDesk», от лица [ФИО], зарегистрированного как самозанятое лицо (ПИНФЛ: [ПИНФЛ], свидетельство самозанятого №: [НОМЕР СВИДЕТЕЛЬСТВА]; далее — «Исполнитель»).
1.2. **Сервис (KinderDesk)** — принадлежащее Исполнителю облачное программное обеспечение (SaaS), предоставляемое удалённо через интернет. Оно помогает частным детским садам и дошкольным организациям вести внутренний учёт: дети, группы, посещаемость, платежи, зарплата, заявки и т. п.
1.3. **Заказчик** — принявший Оферту частный детский сад или дошкольная организация (юридическое лицо или индивидуальный предприниматель) либо его представитель. Сервис предназначен для предпринимательской деятельности; физическое лицо не регистрируется в качестве пользователя для личных, семейных нужд.
1.4. **Акцепт** — полное и безоговорочное принятие условий Оферты Заказчиком путём регистрации в Сервисе, активации пробного периода или пополнения баланса. При регистрации ставится отметка об ознакомлении и согласии с Офертой и Политикой конфиденциальности; время, версия согласия и пользователь фиксируются в Сервисе.
1.5. **Личный кабинет** — выделенный для Заказчика раздел с входом по логину и паролю, где хранятся только данные этого Заказчика.
1.6. **Подписка** — право пользоваться Сервисом по выбранной редакции (Start, Standard, Premium) и периоду.
1.7. **Баланс** — средства, оплаченные Заказчиком Исполнителю и ещё не израсходованные на подписку.

## 2. Предмет договора
2.1. Исполнитель предоставляет Заказчику простое (неисключительное), непередаваемое право удалённого использования Сервиса по выбранному тарифу, а Заказчик оплачивает Сервис.
2.2. Набор возможностей определяется выбранной редакцией. Редакции, их состав и действующие цены публикуются на сайте Исполнителя.
2.3. Сервис предоставляется «в том виде, в каком он предоставлен Исполнителем». Исполнитель принимает разумные технические меры для стабильной работы и по возможности заранее сообщает в Сервисе о плановых работах.
2.4. Сервис является инструментом внутреннего учёта детского сада. Он не подключён к государственным, налоговым или фискальным системам, не выдаёт фискальные чеки и не сдаёт налоговую отчётность. Эти обязанности остаются за Заказчиком.

## 3. Заключение договора
3.1. Договор считается заключённым с момента Акцепта и действует в электронной форме, равной письменной. Текст Оферты постоянно доступен на сайте Исполнителя.
3.2. Заказчик подтверждает, что предоставил достоверные и полные данные при регистрации и вправе открывать учётную запись.
3.3. Исполнитель вправе отказать в регистрации или заблокировать учётную запись: при ложных данных; при использовании Сервиса в противоправных целях; при проблемах с оплатой; при существенном нарушении Оферты.

## 4. Пробный период и подписка
4.1. Новому Заказчику предоставляется бесплатный пробный период 14 (четырнадцать) дней. Пробный период даётся один раз на один номер телефона и один ИНН/ПИНФЛ.
4.2. По окончании пробного периода или оплаченной подписки Сервис автоматически переходит в режим «только просмотр»: введённые данные сохраняются и доступны для просмотра, добавление и редактирование записей ограничивается.
4.3. За 3 (три) дня до окончания подписки Исполнитель уведомляет ответственное лицо Заказчика (директора) уведомлением в Сервисе и по SMS.
4.4. Подписка не продлевается автоматически, средства с банковской карты автоматически не списываются. Заказчик сам выбирает тариф и приобретает его за счёт средств на балансе.

## 5. Оплата и расчёты
5.1. Расчёты производятся в национальной валюте Республики Узбекистан — сумах. Исполнитель является самозанятым лицом и не является плательщиком налога на добавленную стоимость; цены указываются без НДС.
5.2. Баланс пополняется через платёжную систему Payme, указанную в Сервисе, либо переводом на банковскую карту / счёт Исполнителя с отправкой чека оплаты через Сервис. Если будут добавлены другие способы оплаты, они публикуются на сайте. После подтверждения Исполнителем чека перевода средства зачисляются на баланс в полном объёме.
5.3. По запросу Заказчика Исполнитель выдаёт подтверждение оплаты в порядке, установленном законодательством (в том числе электронный чек или счёт-фактуру через налоговое приложение самозанятого).
5.4. Исполнитель вправе изменять цены тарифов. Изменение вступает в силу с момента публикации на сайте; цена за уже оплаченный период подписки не меняется.
5.5. Оплата за период, в котором Сервис был предоставлен (использован), не возвращается, кроме случаев, предусмотренных законом.
5.6. Неизрасходованный остаток баланса возвращается по письменному запросу Заказчика в течение 15 (пятнадцати) банковских дней со дня получения запроса на указанный им счёт. Банковская комиссия (при наличии) может удерживаться из возвращаемой суммы.
5.7. Заказчик обязан правильно указывать платёжные реквизиты и обеспечивать, чтобы в чеке были чётко видны сумма, дата и плательщик. Для идентификации ошибочного платежа Исполнитель может запросить дополнительные сведения.

## 6. Права и обязанности сторон
6.1. Исполнитель обязан: предоставлять Сервис в объёме, указанном в Оферте; защищать данные Заказчика в соответствии с Офертой и законодательством; отвечать на обращения Заказчика в рабочее время и в сроки, указанные в Сервисе.
6.2. Исполнитель вправе: совершенствовать и обновлять Сервис, проводить плановые технические работы; изменять Оферту в порядке, указанном в разделе 13; ограничивать или блокировать учётную запись Заказчика, нарушившего Оферту.
6.3. Заказчик обязан: использовать Сервис только в законных целях; сохранять в тайне логин, пароль и средства двухэтапного подтверждения; правильно выдавать права сотрудникам; отвечать за достоверность и законность вводимых данных.
6.4. Заказчик не совершает действий, причиняющих вред Сервису (взлом, несанкционированный доступ, вывод системы из строя массовыми запросами, попытки доступа к данным других Заказчиков) и не перепродаёт Сервис третьим лицам.
6.5. Заказчик отвечает за все действия, совершённые через его учётную запись, в том числе действия его сотрудников.

## 7. Персональные данные и конфиденциальность
7.1. **Роли.** Заказчик определяет цели и средства обработки персональных данных детей, родителей (опекунов) и сотрудников, вносимых в Сервис, и поэтому является оператором данных. Исполнитель обрабатывает данные только от имени Заказчика и по его указаниям в рамках предоставления Сервиса (обработчик) и не использует их в собственных интересах или иных целях.
7.2. **Обязанности Заказчика.** В соответствии с Законом Республики Узбекистан «О персональных данных» Заказчик: получает согласие субъектов данных на обработку (для несовершеннолетних детей — от их родителей или законных представителей) и хранит его в доказуемой форме; учитывает, что для сведений о здоровье согласие должно быть в письменной форме; в случаях, требуемых законом, регистрирует базу данных в установленном порядке; вносит только необходимые данные.
7.3. **Обязанности Исполнителя.** Исполнитель: (а) хранит данные конфиденциально и не передаёт третьим лицам, кроме случаев, предусмотренных законом; (б) хранит данные каждого Заказчика логически отдельно от других; (в) хранит чувствительные поля (в том числе сведения о здоровье, лицах, которым разрешено забирать ребёнка, контактные данные) в базе в зашифрованном виде; (г) ограничивает доступ правами и защищает учётную запись директора и управляющего платформой двухэтапным подтверждением через Telegram; (д) маскирует персональные данные в журналах; (е) не собирает и не хранит биометрические данные (лицо, отпечатки пальцев и т. п.); (ж) при выявлении нарушения безопасности данных уведомляет Заказчика как можно скорее, в любом случае в течение 72 часов.
7.4. **Место хранения.** Исполнитель хранит данные Заказчика на серверах, расположенных на территории Республики Узбекистан, в соответствии с требованиями законодательства.
7.5. **Доступ работников Исполнителя.** Исполнитель (управляющий платформой) в обычном порядке не видит данные Заказчика о детях и сотрудниках. Для технической поддержки с согласия Заказчика может использоваться временный «Режим помощи»; такие входы фиксируются в журнале.
7.6. **Искусственный интеллект.** ИИ-помощнику в Сервисе не передаются имена, телефоны, документы и сведения о здоровье детей и сотрудников; ему передаются только обобщённые цифры, сотрудники обозначаются условными метками.
7.7. **Третьи лица.** Для работы Сервиса могут использоваться внешние сервисы: SMS-провайдер (Eskiz или другой), Telegram (отправка сообщений родителям через бот, подключённый самим Заказчиком), платёжная система (Payme). Им передаются только данные, необходимые для выполнения этой услуги. Получение сообщений в Telegram начинается с согласия самого родителя, который в любой момент может остановить бот и отключить привязку. Заказчик обязан уведомить родителей о передаче данных через Telegram и получить соответствующее согласие.
7.8. Порядок выгрузки и удаления данных определён в разделе 10.

## 8. SMS и сообщения Telegram
8.1. SMS отправляются через SMS-провайдера, подключённого самим Заказчиком или к платформе. Шаблоны начинают работать после одобрения провайдера; стоимость SMS оплачивается на условиях провайдера.
8.2. Telegram-бот подключается Заказчиком по собственному токену и работает от имени Заказчика. За содержание сообщений, выбранные события и тексты отвечает Заказчик. Исполнитель не гарантирует доставку сообщений со стороны Telegram.
8.3. Заказчик не отправляет через Сервис незаконные, оскорбительные сообщения или несогласованную рекламу.

## 9. Интеллектуальная собственность
9.1. Все права на Сервис, его программный код, дизайн, название и документацию принадлежат Исполнителю. Оферта не передаёт Заказчику права собственности, а лишь право использования.
9.2. Данные, внесённые Заказчиком в Сервис (о детях, сотрудниках, финансовые записи и т. п.), остаются принадлежностью Заказчика.
9.3. Заказчик не вправе копировать, изменять, декомпилировать Сервис или использовать его для создания аналогичного продукта.

## 10. Хранение, выгрузка и удаление данных
10.1. После окончания подписки данные Заказчика хранятся на серверах Сервиса 90 (девяносто) календарных дней и в этот период доступны в режиме «только просмотр».
10.2. Если в течение 90 дней подписка не возобновлена, Исполнитель вправе безвозвратно удалить данные Заказчика с серверов. Внутренние резервные файлы удаляются автоматически в установленные сроки (не более 24 часов).
10.3. Заказчик вправе в любое время выгрузить свои данные через имеющуюся в Сервисе функцию экспорта (Excel/CSV) либо письменно запросить у Исполнителя закрытие учётной записи и полное удаление данных. После исполнения запроса данные восстановить нельзя.
10.4. Функция экспорта доступна директору (владельцу учётной записи) Заказчика; за дальнейшее хранение и защиту выгруженных данных отвечает Заказчик.

## 11. Ответственность сторон
11.1. За нарушение Оферты стороны несут ответственность в соответствии с законодательством Республики Узбекистан.
11.2. Исполнитель не отвечает за: сбои у интернет-провайдеров, хостинга, SMS-провайдера, Telegram, платёжной системы и иных третьих лиц; неправильные действия Заказчика или его сотрудников, неверно введённые данные, раскрытие пароля; финансовые и иные последствия решений, принятых Заказчиком на основании Сервиса; применение расчётов Сервиса (зарплата, долг, скидка) без проверки Заказчиком.
11.3. Общая ответственность Исполнителя по Оферте, за исключением вреда, причинённого умышленно или по грубой неосторожности, а также случаев, когда закон не допускает ограничения, не превышает суммы, фактически уплаченной Заказчиком за Сервис за последние 3 (три) месяца.
11.4. Заказчик отвечает за законность вносимых им данных и за согласие родителей и сотрудников, и освобождает Исполнителя от претензий третьих лиц в связи с этим.

## 12. Форс-мажор
12.1. Если сторона не может исполнить обязательство из-за чрезвычайных обстоятельств вне её контроля (стихийное бедствие, война, решения государственных органов, масштабные сбои энергоснабжения или связи, кибератаки и т. п.), она освобождается от ответственности на время действия этих обстоятельств и по возможности скорее уведомляет другую сторону.

## 13. Изменение Оферты
13.1. Исполнитель вправе в одностороннем порядке изменять Оферту. Новая редакция публикуется на сайте и показывается пользователям в Сервисе; она вступает в силу через 10 (десять) дней после публикации, а изменение цен — в порядке пункта 5.4.
13.2. После вступления новой редакции в силу у пользователя может быть запрошено повторное согласие. Не согласный Заказчик вправе прекратить пользоваться Сервисом, выгрузить данные по пункту 10.3 и получить остаток баланса по пункту 5.6.

## 14. Срок, расторжение и смена Исполнителя
14.1. Договор вступает в силу с момента Акцепта и действует, пока Заказчик пользуется Сервисом или до закрытия его учётной записи.
14.2. Заказчик вправе в любое время прекратить пользование Сервисом. Исполнитель вправе заблокировать учётную запись Заказчика, существенно нарушившего Оферту, и расторгнуть договор.
14.3. Если форма деятельности Исполнителя изменится (например, он станет индивидуальным предпринимателем или юридическим лицом), права и обязанности по Оферте переходят к новой форме; об этом сообщается Заказчикам на сайте. Данные Заказчика и оплаченная подписка сохраняются.

## 15. Разрешение споров и применимое право
15.1. Оферта толкуется и применяется в соответствии с законодательством Республики Узбекистан.
15.2. Споры сначала разрешаются путём переговоров. Претензия направляется в письменной или электронной форме и рассматривается в течение 15 (пятнадцати) календарных дней.
15.3. Если согласие не достигнуто, спор разрешается в компетентном суде (экономическом суде или суде по гражданским делам) в соответствии с законодательством Республики Узбекистан.

## 16. Заключительные положения
16.1. Признание недействительным какого-либо пункта Оферты не влияет на силу остальных пунктов.
16.2. Оферта публикуется на узбекском и русском языках; при расхождении основным считается узбекский текст.
16.3. Контакты Исполнителя (телефон, Telegram, электронная почта) указаны в разделе «Контакты» на сайте.

## 17. Реквизиты Исполнителя
- Исполнитель: самозанятое лицо [ФИО]
- ПИНФЛ: [14-значный ПИНФЛ]
- Свидетельство самозанятого №: [НОМЕР СВИДЕТЕЛЬСТВА]
- Банк: [НАЗВАНИЕ БАНКА]
- Банковская карта: [НОМЕР КАРТЫ]
- Расчётный счёт (если есть): [НОМЕР СЧЁТА]
- Телефон: [ТЕЛЕФОН]
- Электронная почта: [E-MAIL]
- Сайт: [АДРЕС САЙТА]
- Дата редакции Оферты: [ДАТА]
MD,
        ],
        'privacy' => [
            'uz' => <<<'MD'
## 1. Umumiy ma'lumot
Ushbu siyosat [KOMPANIYA NOMI] («Ijrochi») KinderDesk xizmatida shaxsga doir ma'lumotlarni qanday yig'ishi, ishlatishi va himoya qilishini tushuntiradi. U O'zbekiston Respublikasining «Shaxsga doir ma'lumotlar to'g'risida»gi qonuniga muvofiq tuzilgan.

## 2. Qanday ma'lumot yig'iladi
- **Foydalanuvchi va bog'cha:** ism, telefon raqam, elektron pochta, bog'cha nomi, STIR, manzil.
- **Bog'cha kiritadigan ma'lumot:** bolalar, ota-onalar (vasiylar), xodimlar, davomad, to'lovlar va ish haqi ma'lumotlari. Bu ma'lumotlarni faqat bog'chaning o'zi kiritadi va faqat o'sha bog'cha xodimlari ko'ra oladi.
- **Texnik ma'lumot:** IP-manzil, qurilma va brauzer turi, kirish vaqti (xavfsizlik jurnali uchun).

## 3. Nima uchun ishlatiladi
Xizmatni ko'rsatish, kirishni tasdiqlash (SMS kod), to'lovlarni hisobga olish, qo'llab-quvvatlash va xavfsizlikni ta'minlash uchun. Ma'lumotlar sotilmaydi va reklama maqsadida uchinchi shaxslarga berilmaydi.

## 4. Himoya
- Bog'chalar ma'lumotlari bir-biridan qat'iy ajratilgan.
- Ota-ona va xodimlarning ism hamda telefonlari kabi maxfiy maydonlar bazada shifrlangan holda saqlanadi.
- Platforma administratori bola va xodimlarning shaxsiy ma'lumotlarini ko'rmaydi (faqat bog'cha direktori ruxsat bergan «yordam rejimida»).
- Sun'iy intellekt xizmatlariga ism, telefon, manzil yoki hujjat raqamlari yuborilmaydi — faqat umumiy sonlar va foizlar.

## 5. Uchinchi tomonlar
SMS yuborish (Eskiz), onlayn to'lov (Payme) va AI xizmati (Anthropic Claude) kabi provayderlar faqat zarur minimal ma'lumot bilan ishlaydi.

## 6. Saqlash muddati
Ma'lumotlar obuna davomida va tugagandan keyin [90] kun saqlanadi, so'ng o'chirilishi mumkin. Sizning so'rovingizga binoan ma'lumot ko'chirib beriladi yoki o'chiriladi.

## 7. Huquqlaringiz
Ma'lumotingiz bilan tanishish, tuzatish, o'chirish yoki ishlashga rozilikni qaytarib olishni so'rashingiz mumkin: [EMAIL] yoki [TELEFON].

## 8. O'zgarishlar
Siyosat yangilanishi mumkin; amaldagi tahrir shu sahifada e'lon qilinadi. Oxirgi yangilanish: [SANA].
MD,
            'ru' => <<<'MD'
## 1. Общие сведения
Настоящая политика объясняет, как [НАЗВАНИЕ КОМПАНИИ] («Исполнитель») собирает, использует и защищает персональные данные в сервисе KinderDesk. Она составлена в соответствии с Законом Республики Узбекистан «О персональных данных».

## 2. Какие данные собираются
- **Пользователь и детский сад:** имя, номер телефона, e-mail, название сада, ИНН, адрес.
- **Данные, вносимые садом:** дети, родители (опекуны), сотрудники, посещаемость, платежи и зарплата. Эти данные вносит только сам сад, и видят их только сотрудники этого сада.
- **Технические данные:** IP-адрес, тип устройства и браузера, время входа (для журнала безопасности).

## 3. Зачем используются
Для предоставления сервиса, подтверждения входа (SMS-код), учёта платежей, поддержки и безопасности. Данные не продаются и не передаются третьим лицам в рекламных целях.

## 4. Защита
- Данные садов строго изолированы друг от друга.
- Чувствительные поля (имена и телефоны родителей и сотрудников) хранятся в зашифрованном виде.
- Администратор платформы не видит персональных данных детей и сотрудников (только в «режиме помощи» с разрешения директора сада).
- Сервисам искусственного интеллекта не передаются имена, телефоны, адреса и номера документов — только обобщённые числа и проценты.

## 5. Третьи стороны
Провайдеры SMS (Eskiz), онлайн-оплаты (Payme) и ИИ (Anthropic Claude) получают только минимально необходимые данные.

## 6. Срок хранения
Данные хранятся в течение подписки и [90] дней после её окончания, затем могут быть удалены. По вашему запросу данные выгружаются или удаляются.

## 7. Ваши права
Вы можете запросить доступ к данным, их исправление, удаление или отозвать согласие на обработку: [EMAIL] или [ТЕЛЕФОН].

## 8. Изменения
Политика может обновляться; действующая редакция публикуется на этой странице. Последнее обновление: [ДАТА].
MD,
        ],
        'terms' => [
            'uz' => <<<'MD'
## 1. Umumiy
Ushbu shartlar KinderDesk xizmatidan foydalanishning umumiy qoidalarini belgilaydi va [Ommaviy oferta]ning ajralmas qismidir.

## 2. Hisob
2.1. Hisob ma'lumotlarini (telefon, parol) sir saqlang va boshqalarga bermang. Hisobingiz orqali amalga oshirilgan harakatlar uchun o'zingiz javob berasiz.
2.2. Har bir xodim uchun alohida kirish yarating va faqat kerakli ruxsatlarni bering.

## 3. Ruxsat etilmagan harakatlar
- Xizmatdan qonunga zid maqsadlarda foydalanish.
- Boshqa bog'chalarning ma'lumotlariga kirishga urinish, xizmatni buzish yoki ortiqcha yuklash.
- Yolg'on yoki boshqa shaxsga tegishli ma'lumotlarni kiritish.
- Bepul sinovni takror olish uchun boshqa telefon, email yoki STIR'dan foydalanish.

## 4. Sinov, obuna va ko'rish rejimi
Sinov va obuna muddati tugagach, tizim faqat ko'rish rejimiga o'tadi. Obuna o'zi yangilanmaydi: tarifni direktor balans hisobidan o'zi sotib oladi.

## 5. Ma'lumotlar
Siz kiritgan ma'lumotlar sizga tegishli. Siz ularning qonuniyligi va to'g'riligi uchun javobgarsiz. Maxfiylik siyosati bilan tanishib chiqing.

## 6. Cheklash va to'xtatish
Shartlar buzilganda Ijrochi hisobni ogohlantirib yoki ogohlantirmasdan vaqtincha cheklashi yoki to'xtatishi mumkin.

## 7. O'zgarishlar
Shartlar yangilanishi mumkin. Yangilanishdan keyin xizmatdan foydalanishni davom ettirish yangi shartlarga rozilikni bildiradi.

## 8. Aloqa
Savollar uchun: [TELEFON], [TELEGRAM], [EMAIL]. Oxirgi yangilanish: [SANA].
MD,
            'ru' => <<<'MD'
## 1. Общие положения
Настоящие условия определяют общие правила использования сервиса KinderDesk и являются неотъемлемой частью [Публичной оферты].

## 2. Учётная запись
2.1. Храните данные входа (телефон, пароль) в тайне и не передавайте их другим. Вы отвечаете за действия, совершённые под вашей учётной записью.
2.2. Создавайте для каждого сотрудника отдельный вход и выдавайте только необходимые права.

## 3. Запрещённые действия
- Использование сервиса в противоправных целях.
- Попытки доступа к данным других садов, нарушение работы или чрезмерная нагрузка на сервис.
- Внесение ложных данных или данных, принадлежащих другому лицу.
- Использование других номеров телефона, e-mail или ИНН для повторного получения бесплатного пробного периода.

## 4. Пробный период, подписка и режим просмотра
По окончании пробного периода и подписки система переходит в режим «только просмотр». Подписка не продлевается сама: директор выбирает тариф и оплачивает его с баланса.

## 5. Данные
Внесённые вами данные принадлежат вам. Вы отвечаете за их законность и достоверность. Ознакомьтесь с Политикой конфиденциальности.

## 6. Ограничение и приостановка
При нарушении условий Исполнитель вправе временно ограничить или приостановить учётную запись с предупреждением или без него.

## 7. Изменения
Условия могут обновляться. Продолжение использования сервиса после обновления означает согласие с новой редакцией.

## 8. Контакты
По вопросам: [ТЕЛЕФОН], [TELEGRAM], [EMAIL]. Последнее обновление: [ДАТА].
MD,
        ],
    ];
}

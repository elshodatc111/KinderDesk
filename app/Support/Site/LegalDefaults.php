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
## 1. Umumiy qoidalar
1.1. Ushbu ommaviy oferta (keyingi o'rinlarda — «Oferta») [KOMPANIYA NOMI] (STIR: [STIR], manzil: [MANZIL]; keyingi o'rinlarda — «Ijrochi») tomonidan KinderDesk onlayn xizmatidan (keyingi o'rinlarda — «Xizmat») foydalanish shartlarini belgilaydi.
1.2. Ro'yxatdan o'tish, obunani faollashtirish yoki balansni to'ldirish — Oferta shartlarini to'liq va so'zsiz qabul qilish (aksept) hisoblanadi.
1.3. «Buyurtmachi» — Xizmatdan foydalanuvchi xususiy bog'cha yoki uning vakili.

## 2. Xizmat predmeti
2.1. Ijrochi Buyurtmachiga bolalar, guruhlar, davomad, to'lovlar, ish haqi, arizalar va boshqa bo'limlarni yuritish uchun dasturiy xizmatni onlayn taqdim etadi.
2.2. Imkoniyatlar to'plami tanlangan nashr (Start, Standard, Premium) bilan belgilanadi. Nashrlar va narxlar sotuv sahifasida e'lon qilinadi va Ijrochi tomonidan o'zgartirilishi mumkin; o'zgarish amaldagi to'langan davrga ta'sir qilmaydi.

## 3. Sinov muddati va obuna
3.1. Yangi Buyurtmachiga [14] kunlik bepul sinov beriladi. Bir telefon raqam, elektron pochta yoki STIR uchun sinov bir marta beriladi.
3.2. Sinov yoki obuna muddati tugagach, Xizmat avtomatik «faqat ko'rish» rejimiga o'tadi: ma'lumotlar saqlanadi, yangi yozuv kiritib bo'lmaydi.
3.3. Obuna avtomatik yangilanmaydi va avtomatik yechilmaydi. Buyurtmachi tarifni o'zi tanlab, balansdagi mablag' hisobidan xarid qiladi. Balans yetarli bo'lmasa, avval balansni to'ldirish kerak.

## 4. To'lov tartibi
4.1. Balans Payme orqali yoki Ijrochining bank kartasiga o'tkazma qilib va chek yuborish orqali to'ldiriladi. Kartaga o'tkazma chek tasdiqlangandan so'ng to'liq summada balansga tushadi.
4.2. Narxlar so'mda ko'rsatiladi [QQS bilan / QQSsiz].
4.3. Xizmat ko'rsatilgan davr uchun to'lov qaytarilmaydi, qonunda nazarda tutilgan hollar bundan mustasno. Foydalanilmagan balans Buyurtmachi so'roviga ko'ra [___] kun ichida qaytariladi.

## 5. Tomonlarning huquq va majburiyatlari
5.1. Ijrochi Xizmatning ishlashini ta'minlash uchun oqilona choralar ko'radi; rejali texnik ishlar haqida oldindan xabar beradi.
5.2. Buyurtmachi kiritilgan ma'lumotlarning to'g'riligi, hisob (login, parol) maxfiyligi va xodimlarining harakatlari uchun javob beradi.
5.3. Buyurtmachi bolalar va xodimlar shaxsiy ma'lumotlarini kiritishda O'zbekiston Respublikasining «Shaxsga doir ma'lumotlar to'g'risida»gi qonuniga muvofiq zarur rozilikni olganini tasdiqlaydi.
5.4. Ijrochi Buyurtmachi ma'lumotlarini uchinchi shaxslarga bermaydi, qonunda belgilangan hollar bundan mustasno.

## 6. Ma'lumotlarni saqlash va o'chirish
6.1. Obuna tugagandan so'ng ma'lumotlar [90] kun saqlanadi. Bu muddatda to'lov qilinmasa, ma'lumotlar o'chirilishi mumkin va qayta tiklanmaydi.
6.2. Buyurtmachi istalgan vaqtda ma'lumotlarini ko'chirib olishni yoki o'chirishni so'rashi mumkin.

## 7. Javobgarlik
7.1. Xizmat «mavjud holda» taqdim etiladi. Ijrochi Buyurtmachining bilvosita zararlari uchun javobgar emas.
7.2. Ijrochining javobgarligi Buyurtmachi tomonidan so'nggi [3] oy uchun to'langan summa bilan cheklanadi.

## 8. Nizolarni hal qilish
8.1. Nizolar muzokaralar yo'li bilan hal qilinadi; kelishuv bo'lmasa — [SUD NOMI] sudida O'zbekiston Respublikasi qonunchiligiga muvofiq.

## 9. Rekvizitlar
[KOMPANIYA NOMI] · STIR: [STIR] · Hisob raqam: [HISOB RAQAM], [BANK], MFO: [MFO] · Manzil: [MANZIL] · Tel: [TELEFON] · Email: [EMAIL]
MD,
            'ru' => <<<'MD'
## 1. Общие положения
1.1. Настоящая публичная оферта (далее — «Оферта») определяет условия использования онлайн-сервиса KinderDesk (далее — «Сервис») компанией [НАЗВАНИЕ КОМПАНИИ] (ИНН: [ИНН], адрес: [АДРЕС]; далее — «Исполнитель»).
1.2. Регистрация, активация подписки или пополнение баланса означают полное и безоговорочное принятие условий Оферты (акцепт).
1.3. «Заказчик» — частный детский сад или его представитель, использующий Сервис.

## 2. Предмет
2.1. Исполнитель онлайн предоставляет Заказчику программный сервис для ведения детей, групп, посещаемости, платежей, зарплаты, заявок и других разделов.
2.2. Набор возможностей определяется выбранной редакцией (Start, Standard, Premium). Редакции и цены публикуются на сайте и могут изменяться Исполнителем; изменения не влияют на уже оплаченный период.

## 3. Пробный период и подписка
3.1. Новому Заказчику предоставляется бесплатный пробный период [14] дней. Пробный период выдаётся один раз на один номер телефона, e-mail или ИНН.
3.2. По окончании пробного периода или подписки Сервис автоматически переходит в режим «только просмотр»: данные сохраняются, новые записи вносить нельзя.
3.3. Подписка не продлевается и не списывается автоматически. Заказчик самостоятельно выбирает тариф и оплачивает его с баланса. При недостатке средств баланс нужно сначала пополнить.

## 4. Порядок оплаты
4.1. Баланс пополняется через Payme либо переводом на банковскую карту Исполнителя с отправкой чека. Перевод на карту зачисляется на баланс полной суммой после подтверждения чека.
4.2. Цены указаны в сумах [с НДС / без НДС].
4.3. Оплата за оказанный период не возвращается, кроме случаев, предусмотренных законом. Неиспользованный остаток баланса возвращается по запросу Заказчика в течение [___] дней.

## 5. Права и обязанности сторон
5.1. Исполнитель принимает разумные меры для работы Сервиса и заблаговременно уведомляет о плановых технических работах.
5.2. Заказчик отвечает за достоверность вносимых данных, конфиденциальность учётных данных (логин, пароль) и действия своих сотрудников.
5.3. Заказчик подтверждает, что при внесении персональных данных детей и сотрудников получил необходимые согласия в соответствии с Законом Республики Узбекистан «О персональных данных».
5.4. Исполнитель не передаёт данные Заказчика третьим лицам, кроме случаев, установленных законом.

## 6. Хранение и удаление данных
6.1. После окончания подписки данные хранятся [90] дней. Если за это время оплата не поступила, данные могут быть удалены без возможности восстановления.
6.2. Заказчик может в любое время запросить выгрузку или удаление своих данных.

## 7. Ответственность
7.1. Сервис предоставляется «как есть». Исполнитель не отвечает за косвенные убытки Заказчика.
7.2. Ответственность Исполнителя ограничивается суммой, оплаченной Заказчиком за последние [3] месяца.

## 8. Разрешение споров
8.1. Споры решаются путём переговоров; при недостижении согласия — в суде [НАЗВАНИЕ СУДА] в соответствии с законодательством Республики Узбекистан.

## 9. Реквизиты
[НАЗВАНИЕ КОМПАНИИ] · ИНН: [ИНН] · Р/с: [РАСЧЁТНЫЙ СЧЁТ], [БАНК], МФО: [МФО] · Адрес: [АДРЕС] · Тел: [ТЕЛЕФОН] · Email: [EMAIL]
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

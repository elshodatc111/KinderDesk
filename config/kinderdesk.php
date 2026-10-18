<?php

/*
|--------------------------------------------------------------------------
| KinderDesk — platforma sozlamalari reyestri
|--------------------------------------------------------------------------
| Bu fayl platformaning "miyasi": xususiyatlar (features), ruxsatlar,
| bog'cha sozlamalari, SMS hodisalari va nashrlar (editions) shu yerda
| ro'yxatdan o'tadi. Yangi imkoniyat qo'shish uchun shu yerga yozing.
*/

return [

    /* ---------------- Obuna va hayotiy sikl ---------------- */
    'trial_days' => (int) env('KD_TRIAL_DAYS', 14),
    'reminder_days' => (int) env('KD_REMINDER_DAYS', 3),
    'delete_after_days' => (int) env('KD_DELETE_AFTER_DAYS', 90),

    // Super admin (seeder uchun)
    'superadmin' => [
        'name' => env('KD_SUPERADMIN_NAME', 'Super Admin'),
        'phone' => env('KD_SUPERADMIN_PHONE', '998901112233'),
        'password' => env('KD_SUPERADMIN_PASSWORD', 'ChangeMe!2026'),
    ],

    // Rozilik va Telegram-2FA majburiyligi (testlarda o'chiriladi)
    'enforce_security' => (bool) env('KD_ENFORCE_SECURITY', true),

    /* ---------------- Telefon / OTP ---------------- */
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_seconds' => 60,
        // SMS_DRIVER=log bo'lsa, kod storage/logs ichiga yoziladi (mahalliy sinov uchun)
        'allow_otp_login' => true,
    ],

    /* ---------------- Xususiyatlar (feature flags) ----------------
     | group: menyu/sozlamalarda guruhlash uchun
     | core:  doim yoqiq, o'chirib bo'lmaydi
     */
    'features' => [
        'kids' => ['label' => 'Bolalar', 'desc' => "Bolalar ro'yxati, ota-ona ma'lumotlari, arxiv", 'group' => 'Asosiy', 'core' => true],
        'groups' => ['label' => 'Guruhlar', 'desc' => "Guruhlar, bola va tarbiyachi biriktirish tarixi", 'group' => 'Asosiy', 'core' => true],
        'employees' => ['label' => 'Xodimlar', 'desc' => "Xodimlar kartasi va tizimga kirish huquqi", 'group' => 'Asosiy', 'core' => true],
        'attendance' => ['label' => 'Bolalar davomadi', 'desc' => "Guruh bo'yicha kunlik davomad", 'group' => 'Asosiy'],
        'staff_attendance' => ['label' => 'Xodimlar davomadi', 'desc' => "Xodimlarning kunlik kelish-ketishi", 'group' => 'Asosiy'],
        'finance' => ['label' => 'Moliya', 'desc' => "Direktor moliyasi: kirim, xarajat, hisoblar o'rtasida o'tkazma", 'group' => 'Moliya'],
        'desk' => ['label' => 'Kassa', 'desc' => "Bog'cha kassasi: ota-onalardan to'lov qabul qilish va direktorga pul topshirish", 'group' => 'Moliya'],
        'clubs' => ['label' => "To'garaklar", 'desc' => "Alohida to'garaklar: a'zolik, davomad va alohida to'lov", 'group' => 'Asosiy'],
        'extra_groups' => ['label' => 'Shanba va kechki guruhlar', 'desc' => "Shanba guruhi va kechki guruh (17:00–20:00): faqat kelgan kun uchun to'lov", 'group' => 'Asosiy'],
        'payroll' => ['label' => 'Ish haqi', 'desc' => "Oylik ish haqi hisob-kitobi va to'lovi", 'group' => 'Moliya'],
        'leads' => ['label' => 'Arizalar (leadlar)', 'desc' => "Bola va xodim arizalari, voronka, ochiq forma", 'group' => 'Qabul'],
        'sms' => ['label' => 'SMS xabarlar', 'desc' => "Ota-onalarga SMS: o'z Eskiz akkaunti, shablonlar, qoidalar", 'group' => 'Aloqa'],
        'reports' => ['label' => 'Hisobotlar', 'desc' => "Bolalar, to'lovlar, qarzdorlar va boshqa hisobotlar", 'group' => 'Tahlil'],
        'analytics' => ['label' => 'Jonli statistika', 'desc' => "Dashboard grafiklari va real-time ko'rsatkichlar", 'group' => 'Tahlil'],
        'exports' => ['label' => 'Excel (CSV) eksport', 'desc' => "Hisobotlarni CSV faylga yuklab olish", 'group' => 'Tahlil'],
        'custom_roles' => ['label' => "Lavozimlar va ruxsatlar", 'desc' => "O'z lavozimlaringizni yaratish va har biriga ruxsatlarni sozlash", 'group' => 'Xavfsizlik'],
        'calendar' => ['label' => 'Ish kalendari', 'desc' => "Oylik ish kalendari: bayram va dam olish kunlari, direktor oyni tasdiqlaydi", 'group' => 'Asosiy', 'core' => true],
        'family_discount' => ['label' => "Oila bog'lamasi va aka-uka chegirmasi", 'desc' => "Bir oiladan 2 va undan ortiq bola kelsa, chegirma avtomatik qo'llanadi", 'group' => 'Moliya'],
        'excused_discount' => ['label' => "Sababli kelmagan chegirmasi", 'desc' => "Sababli kelmagan kunlar uchun chegirma: administrator kiritadi, direktor tasdiqlaydi", 'group' => 'Moliya'],
        'pay_schemes' => ['label' => "Ish haqi sxemalari", 'desc' => "Katta/kichik guruh, tarbiyachi va yordamchi, oshpaz va administrator uchun bolalar soniga (plus) bog'liq maosh", 'group' => 'Moliya'],
        'substitution' => ['label' => "Almashtirish (o'rindosh)", 'desc' => "Kelmagan xodim o'rniga ishlagan xodimga o'sha kunning ish haqi yoziladi", 'group' => 'Asosiy'],
        'subjects' => ['label' => "Fanlar va pedagoglar", 'desc' => "To'garak va darslar, dars jurnali, pedagog ish haqi (dars uchun yoki oylik), to'garak foydasi", 'group' => 'Asosiy'],
        'events' => ['label' => "Tadbirlar", 'desc' => "Bir martalik tadbirlar (smeta, ro'yxat, narx, yig'im, farq hisoboti) va guruh tadbiri bonusi", 'group' => 'Asosiy'],
        'report_chain' => ['label' => "Hisobot zanjiri", 'desc' => "Xodim → administrator → direktor hisobotlari, muddat, qabul qilish; hisobot o'tkazib yuborilsa bonus qo'shilmaydi", 'group' => 'Asosiy'],
        'ai_chat' => ['label' => "AI yordamchi", 'desc' => "Tizimdan foydalanish bo'yicha savol-javob (bog'cha ma'lumotlariga kirmaydi)", 'group' => 'AI'],
        'ai_report_weekly' => ['label' => "Haftalik AI hisobot", 'desc' => "Direktor uchun haftada bir marta mustaqil tahlil", 'group' => 'AI'],
        'ai_report_daily' => ['label' => "Kunlik AI hisobot", 'desc' => "Direktor uchun har kuni mustaqil tahlil, 30 kunlik tendensiyalar va ish haqi anomaliyalari", 'group' => 'AI'],
        'guardians2' => ['label' => "2 ta vasiy", 'desc' => "Bola uchun 2 tagacha vasiy: munosabat, telefonlar, asosiy to'lovchi, SMS va olib ketish ruxsati", 'group' => 'Qabul'],
        'telegram' => ['label' => 'Telegram bot', 'desc' => "Ota-onalar va xodimlar uchun bog'chaning o'z boti: davomad va to'lov xabarlari, kunlik hisobot, ovqat, e'lonlar", 'group' => 'Aloqa'],
        'health' => ['label' => "Sog'liq va xavfsizlik", 'desc' => "Har bir bola uchun allergiya, kasallik, dori va bolani olib ketishga ruxsat etilgan shaxslar (shifrlangan)", 'group' => 'Qabul'],
        'blacklist' => ['label' => "Qora ro'yxat", 'desc' => "Oila va xodim qora ro'yxati: yangi bola, ariza va xodim qo'shishda ogohlantirish", 'group' => 'Qabul'],
        'admin_bonus' => ['label' => "Administrator bonusi", 'desc' => "Administrator uchun yangi bola va faol bolalar soniga bog'liq bonus", 'group' => 'Moliya'],
        'payroll_attendance' => ['label' => "Davomadga qarab maosh", 'desc' => "Maosh ishlagan kunlarga qarab hisoblanadi", 'group' => 'Moliya'],
        'payroll_hybrid' => ['label' => "Aralash maosh (belgilangan + KPI)", 'desc' => "Asosiy maosh + bola soniga qarab qo'shimcha", 'group' => 'Moliya'],
    ],

    /* ---------------- Nashrlar (editions) ---------------- */
    'editions' => [
        'start' => [
            'name' => 'Start',
            'tagline' => "Kichik va oilaviy bog'chalar uchun",
            'color' => 'sky',
            'features' => ['kids', 'groups', 'employees', 'attendance', 'finance', 'desk', 'reports', 'analytics', 'telegram', 'health', 'custom_roles', 'calendar', 'family_discount', 'excused_discount'],
            'defaults' => ['billing.newcomer_mode' => 'full', 'payroll.strategy' => 'fixed', 'finance.require_confirmation' => false],
        ],
        'standard' => [
            'name' => 'Standard',
            'tagline' => "Oddiy xususiy bog'cha uchun to'liq CRM",
            'color' => 'teal',
            'features' => ['kids', 'groups', 'employees', 'attendance', 'staff_attendance', 'finance', 'desk', 'clubs', 'extra_groups', 'payroll', 'leads', 'sms', 'reports', 'analytics', 'exports', 'custom_roles', 'calendar', 'family_discount', 'excused_discount', 'guardians2', 'blacklist', 'telegram', 'health', 'ai_chat', 'ai_report_weekly', 'payroll_attendance'],
            'defaults' => ['billing.newcomer_mode' => 'full', 'payroll.strategy' => 'attendance', 'finance.require_confirmation' => true],
        ],
        'premium' => [
            'name' => 'Premium',
            'tagline' => "Katta va tarmoq bog'chalar uchun aralash hisob-kitob",
            'color' => 'sun',
            'features' => ['kids', 'groups', 'employees', 'attendance', 'staff_attendance', 'finance', 'desk', 'clubs', 'extra_groups', 'payroll', 'leads', 'sms', 'reports', 'analytics', 'exports', 'custom_roles', 'calendar', 'family_discount', 'excused_discount', 'pay_schemes', 'substitution', 'subjects', 'events', 'report_chain', 'ai_chat', 'ai_report_weekly', 'ai_report_daily', 'guardians2', 'blacklist', 'telegram', 'health', 'admin_bonus', 'payroll_attendance', 'payroll_hybrid'],
            'defaults' => ['billing.newcomer_mode' => 'full', 'payroll.strategy' => 'hybrid', 'finance.require_confirmation' => true],
        ],
    ],

    // Tariflar (muddat oylarda). Narxlar super admin panelida tahrirlanadi;
    // bu qiymatlar faqat boshlang'ich (seed) uchun — haqiqiy narxni o'zingiz belgilang.
    'plan_months' => [1, 3, 12],
    'seed_prices' => [
        'start' => [1 => 79000, 3 => 219000, 12 => 790000],
        'standard' => [1 => 149000, 3 => 399000, 12 => 1490000],
        'premium' => [1 => 299000, 3 => 799000, 12 => 2990000],
    ],

    /* ---------------- Ruxsatlar ---------------- */
    'permissions' => [
        'kids.view' => "Bolalarni ko'rish",
        'kids.manage' => "Bolalarni qo'shish/tahrirlash/arxivlash",
        'groups.view' => "Guruhlarni ko'rish",
        'groups.manage' => "Guruhlarni boshqarish",
        'attendance.kids' => "Bolalar davomadini belgilash",
        'attendance.staff' => "Xodimlar davomadini belgilash",
        'employees.view' => "Xodimlarni ko'rish",
        'employees.manage' => "Xodimlarni boshqarish",
        'leads.view' => "Arizalarni ko'rish",
        'leads.manage' => "Arizalarni boshqarish",
        'clubs.view' => "To'garaklarni ko'rish",
        'clubs.manage' => "To'garaklarni boshqarish",
        'desk.view' => "Kassani ko'rish",
        'finance.payments' => "Kassa: to'lov qabul qilish va qaytarish",
        'desk.handover' => "Kassadan direktorga pul topshirish",
        'finance.confirm' => "Karta/bank to'lovlarini tasdiqlash",
        'finance.view' => "Moliyani ko'rish (direktor hisobi)",
        'finance.handover' => "Kassadan topshirilgan pulni qabul qilish",
        'finance.expenses' => "Xarajat va daromad kiritish",
        'payroll.view' => "Ish haqini ko'rish",
        'payroll.manage' => "Ish haqini hisoblash va to'lash",
        'reports.view' => "Hisobotlarni ko'rish",
        'settings.manage' => "Sozlamalarni o'zgartirish",
        'sms.manage' => "SMS sozlamalarini boshqarish",
        'roles.manage' => "Rollarni boshqarish",
        'subscription.manage' => "Obuna va balansni boshqarish",
        'stats.view' => "Statistika va diagrammalarni ko'rish",
        'payroll.approve' => "Ish haqi qoralamasini tasdiqlash va qayta ochish",
        'payroll.items' => "Premiya, jarima va avans kiritish",
        'substitution.manage' => "Almashtirishlarni kiritish",
        'subjects.manage' => "Fanlar, dars jurnali va pedagog ish haqi sozlamalari",
        'events.manage' => "Tadbirlarni yaratish va boshqarish",
        'guardians.manage' => "Vasiylarni boshqarish",
        'blacklist.view' => "Qora ro'yxatni ko'rish",
        'blacklist.manage' => "Qora ro'yxatga qo'shish va olib tashlash",
        'blacklist.override' => "Qora ro'yxat ogohlantirishiga qaramay davom etish (sabab bilan)",
        'tg.manage' => "Telegram bot sozlamalari (token, matnlar, hodisalar)",
        'tg.contacts' => "Ota-onalarni Telegramga ulash havolalari va ulangan akkauntlar",
        'tg.broadcast' => "Ota-onalarga e'lon yuborish (veb va bot orqali)",
        'tg.report' => "Bot orqali guruh ota-onalariga kunlik hisobot (rasm/video) yuborish",
        'tg.meal' => "Bot orqali barcha ota-onalarga kunlik ovqatni yuborish",
        'ai.chat' => "AI yordamchidan foydalanish",
        'ai.report' => "AI hisobotni ko'rish (direktor)",
        'report.submit' => "Hisobot topshirish (o'z hisobotlari)",
        'report.review' => "Xodimlar hisobotini qabul qilish yoki qaytarish (1-daraja)",
        'report.approve' => "Administrator hisobotini qabul qilish yoki qaytarish (2-daraja)",
        'report.types' => "Hisobot turlarini sozlash",
        'report.waive' => "Hisobot o'tkazib yuborilganini sabab bilan kechirish",
        'events.approve' => "Tadbir smetasi va guruh tadbiri bonusini tasdiqlash",
        'calendar.view' => "Ish kalendarini ko'rish",
        'calendar.manage' => "Ish kalendarini o'zgartirish va oyni tasdiqlash",
        'family.manage' => "Oila bog'lamasini tasdiqlash",
        'discount.enter' => "Sababli kelmagan chegirmasini kiritish",
        'discount.approve' => "Chegirmalarni tasdiqlash",
        'attendance.excuse' => "Xodim kelmaganini sababli/sababsiz deb belgilash",
    ],

    // Ruxsatlar bo'limlar bo'yicha guruhlangan (lavozim sozlash sahifasida)
    'permission_groups' => [
        'Bolalar va guruhlar' => ['kids.view', 'kids.manage', 'groups.view', 'groups.manage', 'clubs.view', 'clubs.manage'],
        'Davomad' => ['attendance.kids', 'attendance.staff', 'attendance.excuse'],
        'Kalendar va chegirmalar' => ['calendar.view', 'calendar.manage', 'family.manage', 'discount.enter', 'discount.approve'],
        'Xodimlar' => ['employees.view', 'employees.manage', 'payroll.view', 'payroll.manage', 'payroll.items', 'payroll.approve', 'substitution.manage'],
        'Fanlar va tadbirlar' => ['subjects.manage', 'events.manage', 'events.approve'],
        "Qabul va xavfsizlik" => ['guardians.manage', 'blacklist.view', 'blacklist.manage', 'blacklist.override'],
        'AI' => ['ai.chat', 'ai.report'],
        'Telegram bot' => ['tg.manage', 'tg.contacts', 'tg.broadcast', 'tg.report', 'tg.meal'],
        'Hisobot zanjiri' => ['report.submit', 'report.review', 'report.approve', 'report.types', 'report.waive'],
        'Arizalar' => ['leads.view', 'leads.manage'],
        'Kassa' => ['desk.view', 'finance.payments', 'desk.handover', 'finance.confirm'],
        'Moliya (direktor hisobi)' => ['finance.view', 'finance.handover', 'finance.expenses'],
        'Hisobotlar va sozlamalar' => ['reports.view', 'stats.view', 'settings.manage', 'sms.manage', 'roles.manage', 'subscription.manage'],
    ],

    // Har bir yangi bog'chaga yaratiladigan boshlang'ich lavozimlar (direktor o'zgartira, o'chira va yangisini qo'sha oladi)
    'system_roles' => [
        'director' => ['name' => 'Direktor', 'permissions' => ['*']],
        'admin' => ['name' => 'Administrator', 'permissions' => [
            'kids.view', 'kids.manage', 'groups.view', 'groups.manage', 'clubs.view', 'clubs.manage', 'attendance.kids', 'attendance.staff',
            'employees.view', 'employees.manage', 'leads.view', 'leads.manage', 'desk.view', 'finance.payments', 'desk.handover',
            'finance.confirm', 'payroll.view', 'reports.view', 'stats.view', 'sms.manage', 'settings.manage',
            'calendar.view', 'family.manage', 'discount.enter', 'attendance.excuse', 'substitution.manage', 'subjects.manage', 'events.manage', 'report.submit', 'report.review', 'guardians.manage', 'blacklist.view', 'blacklist.manage', 'ai.chat', 'tg.contacts', 'tg.broadcast',
        ]],
        'accountant' => ['name' => 'Hisobchi', 'permissions' => [
            'kids.view', 'desk.view', 'finance.view', 'finance.payments', 'finance.confirm', 'finance.handover', 'finance.expenses',
            'payroll.view', 'payroll.manage', 'reports.view', 'stats.view', 'employees.view', 'ai.chat',
        ]],
        'cashier' => ['name' => 'Kassir', 'permissions' => ['kids.view', 'desk.view', 'finance.payments', 'desk.handover', 'ai.chat']],
        'teacher' => ['name' => 'Tarbiyachi', 'permissions' => ['kids.view', 'groups.view', 'clubs.view', 'attendance.kids', 'leads.view', 'report.submit', 'ai.chat', 'tg.contacts', 'tg.report']],
        'assistant' => ['name' => 'Yordamchi tarbiyachi', 'permissions' => ['kids.view', 'groups.view', 'attendance.kids', 'report.submit', 'ai.chat', 'tg.contacts', 'tg.report']],
        'pedagog' => ['name' => 'Pedagog', 'permissions' => ['kids.view', 'groups.view', 'clubs.view', 'report.submit', 'ai.chat', 'tg.report']],
        'cook' => ['name' => 'Oshpaz', 'permissions' => ['report.submit', 'tg.meal']],
        'nurse' => ['name' => 'Hamshira', 'permissions' => ['kids.view', 'report.submit']],
        'cleaner' => ['name' => 'Farrosh', 'permissions' => []],
        'staff' => ['name' => 'Boshqa xodim', 'permissions' => []],
    ],

    /* ---------------- Bog'cha sozlamalari reyestri ----------------
     | type: bool | int | string | text | select | multiselect | list | time
     | feature: shu xususiyat yoqiq bo'lsagina sozlama ko'rinadi
     | option_features: select variantlari uchun kerakli xususiyat
     */
    'settings' => [
        'general' => [
            'title' => 'Umumiy',
            'items' => [
                'general.currency' => ['type' => 'string', 'default' => "so'm", 'label' => "Valyuta nomi"],
                'general.working_days' => ['type' => 'multiselect', 'default' => [1, 2, 3, 4, 5], 'label' => "Ish kunlari",
                    'options' => [1 => 'Dushanba', 2 => 'Seshanba', 3 => 'Chorshanba', 4 => 'Payshanba', 5 => 'Juma', 6 => 'Shanba', 7 => 'Yakshanba']],
                'general.birthday_widget' => ['type' => 'bool', 'default' => true, 'label' => "Bosh sahifada tug'ilgan kunlarni ko'rsatish"],
            ],
        ],
        'groups' => [
            'title' => "Guruh narxlari",
            'items' => [
                'groups.price_large' => ['type' => 'int', 'default' => 1100000, 'min' => 0, 'label' => "Katta guruh oylik narxi (so'm)", 'help' => "Yangi katta guruh yaratilganda shu narx qo'yiladi; har guruhga alohida narx ham berish mumkin. Ish haqi hisobida ham shu narx asos bo'ladi."],
                'groups.price_small' => ['type' => 'int', 'default' => 1300000, 'min' => 0, 'label' => "Kichik guruh oylik narxi (so'm)"],
            ],
        ],
        'billing' => [
            'title' => "Bolalar to'lovi",
            'items' => [
                'billing.newcomer_mode' => ['type' => 'select', 'default' => 'full', 'label' => "Oy o'rtasida kelgan bolaning birinchi oyi",
                    'help' => "Asosiy guruh to'lovi qat'iy: bola kelsa ham, kelmasa ham guruh narxi to'liq yechiladi. Faqat yangi kelgan bolaning birinchi oyini ish kalendaridagi kunlarga proporsional qilish mumkin. Masalan: narx 600 000, oyda 24 ish kuni, bola 12-kundan 8 ish kuni qolgan → 200 000 so'm.",
                    'options' => ['full' => "To'liq narx", 'prorated' => "Qolgan ish kunlariga proporsional"]],
                'billing.auto_charge' => ['type' => 'bool', 'default' => true, 'label' => "Oylik to'lovni avtomatik yechish"],
                'billing.charge_day' => ['type' => 'int', 'default' => 1, 'min' => 1, 'max' => 28, 'label' => "Har oyning nechanchi kuni hisoblansin"],
                'billing.debt_warning' => ['type' => 'int', 'default' => 0, 'min' => 0, 'label' => "Qarz ogohlantirish chegarasi (so'm)", 'help' => "0 — o'chirilgan. Qarz shu summadan oshsa, bola qizil belgi bilan ko'rsatiladi"],
            ],
        ],
        'sibling' => [
            'title' => "Aka-uka chegirmasi",
            'feature' => 'family_discount',
            'items' => [
                'sibling.mode' => ['type' => 'select', 'default' => 'percent', 'label' => "Chegirma turi",
                    'help' => "Eng qimmat guruhdagi bola to'liq to'laydi, qolganlari chegirma oladi. Oila bog'lamasini administrator tasdiqlaydi, chegirma keyingi oydan boshlanadi.",
                    'options' => ['percent' => 'Foiz', 'amount' => "Qat'iy summa"]],
                'sibling.second_percent' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 100, 'label' => "2-bola uchun chegirma (%)"],
                'sibling.third_percent' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 100, 'label' => "3-bola va keyingilar uchun chegirma (%)"],
                'sibling.second_amount' => ['type' => 'int', 'default' => 0, 'min' => 0, 'label' => "2-bola uchun chegirma (so'm)"],
                'sibling.third_amount' => ['type' => 'int', 'default' => 0, 'min' => 0, 'label' => "3-bola va keyingilar uchun chegirma (so'm)"],
                'sibling.apply_to' => ['type' => 'multiselect', 'default' => ['main', 'club', 'evening'], 'label' => "Qaysi to'lovlarga qo'llansin",
                    'options' => ['main' => 'Asosiy guruh', 'club' => "To'garak", 'evening' => 'Kechki guruh'],
                    'help' => "Shaxsiy narx belgilangan bolaga chegirma berilmaydi."],
                'discount.max_percent' => ['type' => 'int', 'default' => 100, 'min' => 0, 'max' => 100, 'label' => "Jami chegirma chegarasi (oylik narxdan %)"],
            ],
        ],
        'finance' => [
            'title' => 'Moliya',
            'feature' => 'finance',
            'items' => [
                'finance.method_cash' => ['type' => 'bool', 'default' => true, 'label' => "Naqd to'lovni yoqish"],
                'finance.method_card' => ['type' => 'bool', 'default' => true, 'label' => "Karta to'lovini yoqish"],
                'finance.method_bank' => ['type' => 'bool', 'default' => true, 'label' => "Bank o'tkazmasini yoqish"],
                'finance.require_confirmation' => ['type' => 'bool', 'default' => true, 'label' => "Karta/bank to'lovlari tasdiqlanishi kerak",
                    'help' => "Yoqilsa, to'lov 'kutilmoqda' bo'ladi va hisobchi tasdiqlagach bola hisobiga tushadi"],
                'finance.allow_negative_cash' => ['type' => 'bool', 'default' => false, 'label' => "Kassa qoldig'i manfiy bo'lishiga ruxsat"],
            ],
        ],
        'ai' => [
            'title' => 'AI',
            'feature' => 'ai_chat',
            'items' => [
                'ai.enabled' => ['type' => 'bool', 'default' => true, 'label' => "AI'dan foydalanish", 'help' => "O'chirsangiz, chat va AI hisobot to'xtaydi. AI'ga bola, xodim ismi, telefon yoki hujjat raqami yuborilmaydi — faqat sonlar va foizlar."],
                'ai.show_names' => ['type' => 'bool', 'default' => false, 'label' => "AI hisobotda xodim ismlarini ko'rsatish", 'help' => "Yoqilsa, hisobotdagi «Xodim-12» belgilari faqat sizning ekraningizda ismga almashtiriladi. Ismlar AI'ga hech qachon yuborilmaydi."],
            ],
        ],
        'report_chain' => [
            'title' => 'Hisobot zanjiri',
            'feature' => 'report_chain',
            'items' => [
                'report.allow_late' => ['type' => 'bool', 'default' => true, 'label' => "Kechikib topshirilgan, lekin qabul qilingan hisobot hisoblansin", 'help' => "O'chiq bo'lsa, muddatdan keyin topshirilgan hisobot qabul qilinsa ham «o'tkazib yuborilgan» sanaladi."],
                'report.review_days' => ['type' => 'int', 'default' => 2, 'min' => 0, 'max' => 14, 'label' => "Administrator hisobotni necha kun ichida ko'rib chiqishi kerak", 'help' => "Shu muddatdan oshib ko'rilmagan xodim hisoboti administrator bonusini to'xtatadi."],
            ],
        ],
        'events' => [
            'title' => 'Tadbirlar',
            'feature' => 'events',
            'items' => [
                'event.markup_percent' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 100, 'label' => "Tadbir narxiga ustama (%)", 'help' => "Bitta bola narxi = jami xarajat / bolalar soni, yuqoriga yaxlitlanadi, so'ng shu foiz qo'shiladi."],
                'event.round_to' => ['type' => 'int', 'default' => 1000, 'min' => 1, 'label' => "Narxni yaxlitlash qadami (so'm)"],
                'event.collect_before' => ['type' => 'bool', 'default' => false, 'label' => "Pul tadbirdan oldin yig'iladi", 'help' => "Yoqilsa, ro'yxat yopilganda barcha yozilgan bolalardan yechiladi. O'chiq bo'lsa — faqat tadbirga kelgan bolalardan."],
                'event.bonus_teacher' => ['type' => 'int', 'default' => 50000, 'min' => 0, 'label' => "Guruh tadbiri bonusi: tarbiyachi (so'm)"],
                'event.bonus_assistant' => ['type' => 'int', 'default' => 25000, 'min' => 0, 'label' => "Guruh tadbiri bonusi: yordamchi (so'm)"],
            ],
        ],
        'attendance' => [
            'title' => 'Davomad',
            'feature' => 'attendance',
            'items' => [
                'attendance.late_after' => ['type' => 'time', 'default' => '09:00', 'label' => "Xodim kechikkan hisoblanadigan vaqt", 'feature' => 'staff_attendance'],
                'attendance.fine_late' => ['type' => 'int', 'default' => 0, 'min' => 0, 'label' => "Sababsiz kechikish jarimasi (so'm)", 'feature' => 'staff_attendance', 'help' => "Faqat administrator «sababsiz» deb belgilagan kechikishlar uchun."],
                'attendance.fine_absent' => ['type' => 'int', 'default' => 0, 'min' => 0, 'label' => "Sababsiz kelmaslik jarimasi (har kun uchun, so'm)", 'feature' => 'staff_attendance'],
            ],
        ],
        'payroll' => [
            'title' => 'Ish haqi',
            'feature' => 'payroll',
            'items' => [
                'payroll.strategy' => ['type' => 'select', 'default' => 'fixed', 'label' => "Ish haqi hisoblash usuli",
                    'help' => "Belgilangan — har oy bir xil oylik. Davomadga qarab — bir kunlik stavka × ishlagan kunlar. Aralash — belgilangan oylik + xodim biriktirilgan guruhlardagi har bir bola uchun qo'shimcha (KPI). Masalan: oylik 3 000 000 + 15 bola × 50 000 = 3 750 000 so'm. Aralash usul Premium nashrda; xodim kartasida har biriga alohida usul ham tanlash mumkin.",
                    'options' => ['fixed' => "Belgilangan oylik", 'attendance' => "Davomadga qarab", 'hybrid' => "Aralash (asosiy + KPI)"],
                    'option_features' => ['attendance' => 'payroll_attendance', 'hybrid' => 'payroll_hybrid']],
                'pay.admin_fixed' => ['type' => 'int', 'default' => 3000000, 'min' => 0, 'label' => "Administrator: belgilangan oylik (so'm)", 'feature' => 'pay_schemes'],
                'pay.admin_new_kid' => ['type' => 'int', 'default' => 100000, 'min' => 0, 'label' => "Administrator: yangi bola uchun (so'm)", 'feature' => 'pay_schemes', 'help' => "Bola birinchi oy to'lovini to'laganidan keyin, har bola uchun faqat bir marta."],
                'pay.admin_per_kid' => ['type' => 'int', 'default' => 25000, 'min' => 0, 'label' => "Administrator: har bir faol bola uchun (so'm)", 'feature' => 'pay_schemes', 'help' => "Oy oxiridagi faol bolalar soni bo'yicha."],
                'pay.kpi_amount' => ['type' => 'int', 'default' => 100000, 'min' => 0, 'label' => "Tarbiyachi va yordamchi KPI: bitta shart uchun (so'm)", 'feature' => 'pay_schemes', 'help' => "Uch shart: ota-onaga hisobot, ota-onalar mamnun, tadbirlarda ishtirok."],
                'pay.penalty_mode' => ['type' => 'select', 'default' => 'withhold', 'label' => "Jarima qanday qo'llanadi", 'feature' => 'pay_schemes',
                    'options' => ['withhold' => "Maoshdan ushlab qolinadi", 'conditional_bonus' => "Shartli bonus qo'shilmaydi"],
                    'help' => "Jarima faqat sababsiz kechikish va kelmaslikka. Qaysi usulni tanlashdan oldin yurist bilan maslahatlashing."],
                'payroll.working_days' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 31, 'label' => "Oydagi ish kunlari soni (0 — ish kalendaridan avtomatik)"],
            ],
        ],
        'leads' => [
            'title' => 'Arizalar',
            'feature' => 'leads',
            'items' => [
                'leads.statuses' => ['type' => 'list', 'default' => ['Yangi', 'Aloqa qilindi', 'Tashrif buyurdi', 'Qabul qilindi', 'Rad etildi'], 'label' => "Ariza bosqichlari (voronka)",
                    'help' => "Har bir bosqichni yangi qatordan yozing. Birinchisi — yangi ariza, oxirgi ikkitasi — muvaffaqiyatli va rad etilgan."],
                'leads.sources' => ['type' => 'list', 'default' => ['Instagram', 'Telegram', "Do'st tavsiyasi", 'Sayt', 'Boshqa'], 'label' => "Ariza manbalari"],
                'leads.public_form' => ['type' => 'bool', 'default' => true, 'label' => "Ochiq ariza formasi yoqilgan"],
            ],
        ],
    ],

    /* ---------------- SMS hodisalari ----------------
     | scope: tenant — bog'cha ota-onaga yuboradi; platform — platforma yuboradi
     | vars: shablonda ishlatiladigan o'zgaruvchilar
     */
    'sms_events' => [
        'payment_received' => [
            'scope' => 'tenant', 'label' => "To'lov qabul qilindi", 'default_on' => true,
            'body' => "{bogcha}: {bola} uchun {summa} so'm to'lov qabul qilindi. Rahmat!",
            'vars' => ['bogcha', 'bola', 'summa'],
        ],
        'debt_reminder' => [
            'scope' => 'tenant', 'label' => "Qarz eslatmasi", 'default_on' => false,
            'body' => "{bogcha}: {bola} uchun {summa} so'm qarzdorlik mavjud. Iltimos, to'lovni amalga oshiring.",
            'vars' => ['bogcha', 'bola', 'summa'],
        ],
        'kid_absent' => [
            'scope' => 'tenant', 'label' => "Bola kelmadi", 'default_on' => false,
            'body' => "{bogcha}: {bola} bugun bog'chaga kelmadi. Sababini bilsak bo'ladimi?",
            'vars' => ['bogcha', 'bola'],
        ],
        'birthday' => [
            'scope' => 'tenant', 'label' => "Tug'ilgan kun tabrigi", 'default_on' => false,
            'body' => "{bogcha} jamoasi {bola}ni tug'ilgan kuni bilan tabriklaydi!",
            'vars' => ['bogcha', 'bola'],
        ],
        'lead_welcome' => [
            'scope' => 'tenant', 'label' => "Yangi ariza qabul qilindi", 'default_on' => false,
            'body' => "{bogcha}: arizangiz qabul qilindi. Tez orada siz bilan bog'lanamiz.",
            'vars' => ['bogcha'],
        ],
        'bulk' => [
            'scope' => 'tenant', 'label' => "Ommaviy xabar", 'default_on' => true,
            'body' => "{bogcha}: {matn}",
            'vars' => ['bogcha', 'matn'],
        ],
        // Platforma xabarlari
        'otp' => [
            'scope' => 'platform', 'label' => "Tasdiqlash kodi",
            'body' => "KinderDesk: tasdiqlash kodingiz {kod}. Uni hech kimga bermang.",
            'vars' => ['kod'],
        ],
        'subscription_expiring' => [
            'scope' => 'platform', 'label' => "Obuna tugashiga 3 kun qoldi",
            'body' => "KinderDesk: {bogcha} obunasi {sana} kuni tugaydi. Uzilishsiz ishlash uchun obunani yangilang.",
            'vars' => ['bogcha', 'sana'],
        ],
        'subscription_expired' => [
            'scope' => 'platform', 'label' => "Obuna tugadi",
            'body' => "KinderDesk: {bogcha} obunasi tugadi. Tizim faqat ko'rish rejimida. Obunani yangilang.",
            'vars' => ['bogcha'],
        ],
        'subscription_renewed' => [
            'scope' => 'platform', 'label' => "Obuna avtomatik yangilandi",
            'body' => "KinderDesk: {bogcha} obunasi balansdan {sana} gacha yangilandi.",
            'vars' => ['bogcha', 'sana'],
        ],
    ],

    // Eskiz shablon holatlarini ichki holatga o'tkazish jadvali.
    // Eskiz hujjatiga qarab kerak bo'lsa shu yerni to'g'rilang.
    'eskiz_status_map' => [
        'approved' => ['service', 'reklama', 'active', 'approved', 'accepted', 'confirmed', 'sent'],
        'rejected' => ['rejected', 'reject', 'cancel', 'canceled', 'cancelled', 'declined', 'blocked'],
        'pending' => ['moderation', 'pending', 'new', 'wait', 'waiting', 'review'],
    ],

    'attendance_statuses' => [
        'present' => 'Keldi',
        'absent' => 'Kelmadi',
        'sick' => 'Kasal',
    ],
    'staff_attendance_statuses' => [
        'present' => 'Keldi',
        'late' => 'Kechikdi',
        'absent' => 'Kelmadi',
        'leave' => "Ta'tilda",
    ],

    /* ---------------- Ish haqi sxemalari (boshlang'ich qiymatlar; direktor o'zgartiradi) ----------------
     | size: asos narx F qaysi guruh o'lchamidan olinadi (groups.price_large / price_small)
     | p: bir bolaning ulushi; L: chiziqli chegara; M: qadam davri; s: oraliq qadam; c: 31+ qadami (0 — yo'q)
     */
    'pay_schemes' => [
        'teacher_large' => ['name' => 'Tarbiyachi (katta guruh)', 'role' => 'teacher', 'size' => 'large', 'p' => 14, 'L' => 15, 'M' => 5, 's' => 100000, 'c' => 150000],
        'assistant_large' => ['name' => 'Yordamchi (katta guruh)', 'role' => 'assistant', 'size' => 'large', 'p' => 7, 'L' => 15, 'M' => 5, 's' => 50000, 'c' => 75000],
        'teacher_small' => ['name' => 'Tarbiyachi (kichik guruh)', 'role' => 'teacher', 'size' => 'small', 'p' => 16, 'L' => 5, 'M' => 5, 's' => 160000, 'c' => 0],
        'assistant_small' => ['name' => 'Yordamchi (kichik guruh)', 'role' => 'assistant', 'size' => 'small', 'p' => 8, 'L' => 5, 'M' => 5, 's' => 80000, 'c' => 0],
        'cook' => ['name' => 'Oshpaz (butun bog\'cha)', 'role' => 'cook', 'size' => 'large', 'p' => 4, 'L' => 45, 'M' => 5, 's' => 30000, 'c' => 0],
    ],

    /* ---------------- Telegram bot ---------------- */
    'telegram' => [
        'delete_minutes' => 30,
        'rate_per_second' => 25,
        'events' => [
            'attendance_came' => ['label' => 'Bola keldi', 'to' => 'Barcha ulangan ota-onalarga'],
            'attendance_absent' => ['label' => 'Bola kelmadi', 'to' => 'Barcha ulangan ota-onalarga'],
            'payment' => ['label' => "To'lov qabul qilindi", 'to' => "Faqat to'lovchi ota-onaga"],
        ],
        'templates' => [
            'attendance_came' => "✅ {kid} bugun ({time}) bog'chaga keldi.",
            'attendance_absent' => "⚠️ {kid} bugun ({date}) bog'chaga kelmadi.",
            'payment' => "✅ To'lov qabul qilindi: {amount} so'm.\n{kid} hisobi: {balance} so'm.",
        ],
        'vars' => ['{kid}', '{group}', '{date}', '{time}', '{amount}', '{balance}', '{kindergarten}'],
    ],

];

<?php

use Illuminate\Support\Facades\Schedule;

// Har kuni yarim tundan keyin (Toshkent vaqti): obuna tekshiruvi, avto-yangilash, eslatmalar
Schedule::command('subscriptions:process')->dailyAt('00:05')->withoutOverlapping();
// Kun davomida ham tekshirib turamiz (masalan, to'lov tushgandan keyin kechikib qolgan eslatmalar uchun)
Schedule::command('subscriptions:process')->hourlyAt(30)->withoutOverlapping();

// Bolalar oylik to'lovini hisoblash (har kuni tekshiriladi, takrorlanmaydi)
Schedule::command('kids:charge')->dailyAt('01:00')->withoutOverlapping();

// Tug'ilgan kun tabriklari (09:00 Toshkent vaqti)
Schedule::command('kids:birthdays')->dailyAt('09:00')->withoutOverlapping();

// Server holati signallari (super admin → "Tizim holati")
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('kd:health:scheduler', now()->toIso8601String(), 3600))->everyMinute()->name('kd-heartbeat');
Schedule::job(new \App\Jobs\WorkerHeartbeat)->everyMinute();

// Ish haqi qoralamasi: oyning birinchi ishchi kunida (Premium)
Schedule::command('payroll:draft')->dailyAt('06:00')->withoutOverlapping();

// Hisobot zanjiri: muddatdan ~2 soat oldin eslatma va muddat o'tgach direktorga xabar (Premium)
Schedule::command('reports:remind --mode=remind')->dailyAt('21:55')->withoutOverlapping();
Schedule::command('reports:remind --mode=overdue')->dailyAt('00:10')->withoutOverlapping();

// AI hisobot: har kuni 00:05 dan keyin (Premium — kunlik, Standard — haftalik)
Schedule::command('ai:daily')->dailyAt('00:05')->withoutOverlapping();

// Telegram: worker (tg:run) ishlamasa ham xabarlar ketishi va polling yangilanishlari olinishi uchun zaxira
Schedule::command('tg:run --once')->everyMinute()->withoutOverlapping();

// Zaxira API: navbatdagi ishlar (fon jarayoni ishga tushmasa zaxira yo'li) va 24 soatdan o'tganlarni o'chirish
Schedule::command('backup:run --pending')->everyMinute()->withoutOverlapping();
Schedule::command('backup:purge')->hourly()->withoutOverlapping();

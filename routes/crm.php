<?php

use App\Http\Controllers\App as C;
use Illuminate\Support\Facades\Route;

/* CRM bo'limlari. Prefiks: /app, nomlar: app.* */

// ----- Yo'riqnoma (hamma foydalanuvchi uchun) -----
Route::get('/yoriqnoma', [C\GuideController::class, 'index'])->name('guide');

// ----- Ish kalendari -----
Route::middleware('feature:calendar')->prefix('kalendar')->name('calendar.')->group(function () {
    Route::get('/', [C\CalendarController::class, 'index'])->middleware('can:calendar.view')->name('index');
    Route::middleware('can:calendar.manage')->group(function () {
        Route::post('/kun', [C\CalendarController::class, 'day'])->name('day');
        Route::post('/tasdiqlash', [C\CalendarController::class, 'confirm'])->name('confirm');
    });
});

// ----- Oila bog'lamasi va chegirmalar -----
Route::middleware(['feature:family_discount', 'can:family.manage'])->prefix('oilalar')->name('families.')->group(function () {
    Route::get('/', [C\FamilyController::class, 'index'])->name('index');
    Route::post('/boglash', [C\FamilyController::class, 'link'])->name('link');
    Route::delete('/bola/{kid}', [C\FamilyController::class, 'unlink'])->name('unlink');
});
Route::middleware('feature:excused_discount')->prefix('chegirmalar')->name('excuses.')->group(function () {
    Route::get('/', [C\ExcuseController::class, 'index'])->name('index');
    Route::post('/', [C\ExcuseController::class, 'store'])->middleware('can:discount.enter')->name('store');
    Route::post('/{excuse}/tasdiqlash', [C\ExcuseController::class, 'approve'])->middleware('can:discount.approve')->name('approve');
    Route::post('/{excuse}/rad', [C\ExcuseController::class, 'reject'])->middleware('can:discount.approve')->name('reject');
});

// ----- Guruhlar -----
Route::middleware('feature:groups')->group(function () {
    Route::get('/guruhlar', [C\GroupController::class, 'index'])->middleware('can:groups.view')->name('groups.index');
    Route::middleware('can:groups.manage')->group(function () {
        Route::get('/guruhlar/yangi', [C\GroupController::class, 'create'])->name('groups.create');
        Route::post('/guruhlar', [C\GroupController::class, 'store'])->name('groups.store');
        Route::get('/guruhlar/{group}/tahrirlash', [C\GroupController::class, 'edit'])->name('groups.edit');
        Route::put('/guruhlar/{group}', [C\GroupController::class, 'update'])->name('groups.update');
        Route::post('/guruhlar/{group}/arxiv', [C\GroupController::class, 'archive'])->name('groups.archive');
        Route::post('/guruhlar/{group}/xodim', [C\GroupController::class, 'assignEmployee'])->name('groups.employees.assign');
        Route::delete('/guruhlar/{group}/xodim/{link}', [C\GroupController::class, 'removeEmployee'])->name('groups.employees.remove');
        Route::post('/guruhlar/{group}/bola', [C\GroupController::class, 'enrollKid'])->name('groups.kids.enroll');
        Route::delete('/guruhlar/{group}/bola/{link}', [C\GroupController::class, 'leaveKid'])->name('groups.kids.leave');
    });
    Route::get('/guruhlar/{group}', [C\GroupController::class, 'show'])->middleware('can:groups.view')->name('groups.show');
});

// ----- Bolalar -----
Route::middleware('feature:kids')->group(function () {
    Route::get('/bolalar', [C\KidController::class, 'index'])->middleware('can:kids.view')->name('kids.index');
    Route::middleware('can:kids.manage')->group(function () {
        Route::get('/bolalar/yangi', [C\KidController::class, 'create'])->name('kids.create');
        Route::post('/bolalar', [C\KidController::class, 'store'])->name('kids.store');
        Route::get('/bolalar/{kid}/tahrirlash', [C\KidController::class, 'edit'])->name('kids.edit');
        Route::put('/bolalar/{kid}', [C\KidController::class, 'update'])->name('kids.update');
        Route::post('/bolalar/{kid}/otkazish', [C\KidController::class, 'transfer'])->name('kids.transfer');
        Route::post('/bolalar/{kid}/qoshimcha', [C\KidController::class, 'addExtra'])->name('kids.extra.add');
        Route::post('/bolalar/{kid}/arxiv', [C\KidController::class, 'archive'])->name('kids.archive');
        Route::post('/bolalar/{kid}/qayta-qabul', [C\KidController::class, 'restore'])->name('kids.restore');
        Route::post('/bolalar/{kid}/izoh', [C\KidController::class, 'note'])->name('kids.notes.store');
        Route::delete('/bolalar/{kid}/izoh/{note}', [C\KidController::class, 'noteDestroy'])->name('kids.notes.destroy');
    });
    Route::middleware(['feature:desk', 'can:finance.payments'])->group(function () {
        Route::post('/bolalar/{kid}/tolov', [C\KidPaymentController::class, 'payment'])->name('kids.payment');
        Route::post('/bolalar/{kid}/qaytarish', [C\KidPaymentController::class, 'refund'])->name('kids.refund');
        Route::post('/bolalar/{kid}/tuzatish', [C\KidPaymentController::class, 'adjust'])->name('kids.adjust');
    });
    Route::get('/bolalar/{kid}', [C\KidController::class, 'show'])->middleware('can:kids.view')->name('kids.show');
});

// ----- Xodimlar -----
Route::middleware('feature:employees')->group(function () {
    Route::get('/xodimlar', [C\EmployeeController::class, 'index'])->middleware('can:employees.view')->name('employees.index');
    Route::middleware('can:employees.manage')->group(function () {
        Route::get('/xodimlar/yangi', [C\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/xodimlar', [C\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/xodimlar/{employee}/tahrirlash', [C\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/xodimlar/{employee}', [C\EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/xodimlar/{employee}/arxiv', [C\EmployeeController::class, 'archive'])->name('employees.archive');
        Route::post('/xodimlar/{employee}/izoh', [C\EmployeeController::class, 'note'])->name('employees.notes.store');
    });
    Route::get('/xodimlar/{employee}', [C\EmployeeController::class, 'show'])->middleware('can:employees.view')->name('employees.show');
});

// ----- Sozlamalar -----
Route::middleware('can:settings.manage')->prefix('sozlamalar')->name('settings.')->group(function () {
    Route::get('/', [C\SettingsController::class, 'index'])->name('index');
    Route::put('/', [C\SettingsController::class, 'update'])->name('update');
    Route::post('/bolimlar', [C\SettingsController::class, 'modules'])->name('modules');
    Route::post('/bogcha', [C\SettingsController::class, 'organization'])->name('organization');
    Route::post('/foydalanuvchilar', [C\SettingsController::class, 'userStore'])->name('users.store');
    Route::put('/foydalanuvchilar/{user}', [C\SettingsController::class, 'userUpdate'])->name('users.update');
    Route::delete('/foydalanuvchilar/{user}', [C\SettingsController::class, 'userDestroy'])->name('users.destroy');
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/lavozimlar/yangi', [C\SettingsController::class, 'roleCreate'])->name('roles.create');
        Route::post('/lavozimlar', [C\SettingsController::class, 'roleStore'])->name('roles.store');
        Route::get('/lavozimlar/{role}', [C\SettingsController::class, 'roleEdit'])->name('roles.edit');
        Route::put('/lavozimlar/{role}', [C\SettingsController::class, 'roleUpdate'])->name('roles.update');
        Route::delete('/lavozimlar/{role}', [C\SettingsController::class, 'roleDestroy'])->name('roles.destroy');
    });
});

// ----- Statistika -----
Route::middleware(['feature:analytics', 'can:stats.view'])->get('/statistika', [C\StatsController::class, 'index'])->name('stats.index');

// ----- Davomad -----
Route::middleware('feature:attendance')->group(function () {
    Route::get('/davomad', [C\AttendanceController::class, 'index'])->middleware('can:attendance.kids')->name('attendance.index');
    Route::post('/davomad', [C\AttendanceController::class, 'store'])->middleware('can:attendance.kids')->name('attendance.store');
    Route::post('/davomad/qoshish', [C\AttendanceController::class, 'attach'])->middleware('can:attendance.kids')->name('attendance.attach');
});
Route::middleware('feature:staff_attendance')->group(function () {
    Route::get('/xodimlar-davomadi', [C\AttendanceController::class, 'staff'])->middleware('can:attendance.staff')->name('staff-attendance.index');
    Route::post('/xodimlar-davomadi', [C\AttendanceController::class, 'staffStore'])->middleware('can:attendance.staff')->name('staff-attendance.store');
});

// ----- Kassa (bog'cha) -----
Route::middleware(['feature:desk'])->prefix('kassa')->name('desk.')->group(function () {
    Route::get('/', [C\DeskController::class, 'index'])->middleware('can:desk.view')->name('index');
    Route::get('/bolalar', [C\DeskController::class, 'kids'])->middleware('can:finance.payments')->name('kids');
    Route::post('/xarajat', [C\DeskController::class, 'expense'])->middleware('can:finance.expenses')->name('expense');
    Route::post('/topshirish', [C\DeskController::class, 'handover'])->middleware('can:desk.handover')->name('handover');
    Route::post('/hisoblash', [C\DeskController::class, 'charge'])->middleware('can:finance.payments')->name('charge');
    Route::post('/{transaction}/tasdiqlash', [C\DeskController::class, 'confirm'])->middleware('can:finance.confirm')->name('confirm');
    Route::post('/{transaction}/bekor', [C\DeskController::class, 'cancel'])->middleware('can:finance.confirm')->name('cancel');
});

// ----- Moliya (direktor hisobi) -----
Route::middleware(['feature:finance'])->prefix('moliya')->name('finance.')->group(function () {
    Route::get('/', [C\FinanceController::class, 'index'])->middleware('can:finance.view')->name('index');
    Route::middleware('can:finance.expenses')->group(function () {
        Route::post('/yozuv', [C\FinanceController::class, 'store'])->name('store');
        Route::post('/otkazma', [C\FinanceController::class, 'transfer'])->name('transfer');
        Route::post('/hisoblar', [C\FinanceController::class, 'accountStore'])->name('accounts.store');
        Route::post('/hisoblar/{account}/holat', [C\FinanceController::class, 'accountToggle'])->name('accounts.toggle');
        Route::delete('/hisoblar/{account}', [C\FinanceController::class, 'accountDestroy'])->name('accounts.destroy');
        Route::post('/subsidiya', [C\FinanceController::class, 'subsidyStore'])->name('subsidy.store');
        Route::post('/subsidiya/{subsidy}/qabul', [C\FinanceController::class, 'subsidyReceive'])->name('subsidy.receive');
        Route::post('/subsidiya/{subsidy}/bekor', [C\FinanceController::class, 'subsidyCancel'])->name('subsidy.cancel');
    });
    Route::middleware('can:finance.handover')->group(function () {
        Route::post('/kassaga-pul', [C\FinanceController::class, 'fundDesk'])->name('fund-desk');
        Route::post('/topshirish/{handover}/qabul', [C\FinanceController::class, 'handoverConfirm'])->name('handover.confirm');
        Route::post('/topshirish/{handover}/rad', [C\FinanceController::class, 'handoverReject'])->name('handover.reject');
    });
});

// ----- To'garaklar -----
Route::middleware('feature:clubs')->prefix('togaraklar')->name('clubs.')->group(function () {
    Route::get('/', [C\ClubController::class, 'index'])->middleware('can:clubs.view')->name('index');
    Route::middleware('can:clubs.manage')->group(function () {
        Route::get('/yangi', [C\ClubController::class, 'create'])->name('create');
        Route::post('/', [C\ClubController::class, 'store'])->name('store');
        Route::get('/{club}/tahrirlash', [C\ClubController::class, 'edit'])->name('edit');
        Route::put('/{club}', [C\ClubController::class, 'update'])->name('update');
        Route::post('/{club}/arxiv', [C\ClubController::class, 'archive'])->name('archive');
        Route::post('/{club}/bola', [C\ClubController::class, 'enroll'])->name('enroll');
        Route::delete('/{club}/bola/{link}', [C\ClubController::class, 'leave'])->name('leave');
    });
    Route::post('/{club}/davomad', [C\ClubController::class, 'attendance'])->middleware('can:attendance.kids')->name('attendance');
    Route::get('/{club}', [C\ClubController::class, 'show'])->middleware('can:clubs.view')->name('show');
});

// ----- Hisobotlar -----
Route::middleware(['feature:reports', 'can:reports.view'])->prefix('hisobotlar')->name('reports.')->group(function () {
    Route::get('/', [C\ReportController::class, 'index'])->name('index');
    Route::get('/yuklab-olish/{what}', [C\ReportController::class, 'export'])->name('export');
});

// ----- Ish haqi -----
Route::middleware(['feature:payroll', 'can:payroll.view'])->prefix('ish-haqi')->name('payroll.')->group(function () {
    Route::get('/', [C\PayrollController::class, 'index'])->name('index');
    Route::middleware('can:payroll.manage')->group(function () {
        Route::post('/hisoblash', [C\PayrollController::class, 'generate'])->name('generate');
        Route::post('/{payslip}/qayta', [C\PayrollController::class, 'recalc'])->name('recalc');
        Route::put('/{payslip}', [C\PayrollController::class, 'adjust'])->name('adjust');
        Route::post('/{payslip}/tolash', [C\PayrollController::class, 'pay'])->name('pay');
        Route::delete('/{payslip}', [C\PayrollController::class, 'destroy'])->name('destroy');
    });
    Route::get('/{payslip}/tafsilot', [C\PayrollController::class, 'detail'])->whereNumber('payslip')->name('detail');
    Route::middleware(['feature:pay_schemes'])->group(function () {
        Route::middleware('can:payroll.approve')->group(function () {
            Route::post('/tasdiqlash', [C\PayrollController::class, 'approveAll'])->name('approve-all');
            Route::post('/{payslip}/tasdiqlash', [C\PayrollController::class, 'approve'])->whereNumber('payslip')->name('approve');
            Route::post('/{payslip}/qayta-ochish', [C\PayrollController::class, 'reopen'])->whereNumber('payslip')->name('reopen');
        });
        Route::middleware('can:payroll.items')->group(function () {
            Route::post('/qator', [C\PayrollController::class, 'itemStore'])->name('items.store');
            Route::post('/qator/ommaviy', [C\PayrollController::class, 'itemBulk'])->name('items.bulk');
            Route::delete('/qator/{item}', [C\PayrollController::class, 'itemDestroy'])->name('items.destroy');
            Route::get('/shikoyatlar', [C\PayrollController::class, 'complaints'])->name('complaints');
            Route::post('/shikoyatlar', [C\PayrollController::class, 'complaintStore'])->name('complaints.store');
            Route::delete('/shikoyatlar/{complaint}', [C\PayrollController::class, 'complaintDestroy'])->name('complaints.destroy');
        });
        Route::middleware('can:payroll.manage')->group(function () {
            Route::get('/sxemalar', [C\PayrollController::class, 'schemes'])->name('schemes');
            Route::put('/sxemalar', [C\PayrollController::class, 'schemesUpdate'])->name('schemes.update');
        });
    });
});

// ----- Almashtirish (o'rindosh) -----
Route::middleware(['feature:substitution', 'can:substitution.manage'])->prefix('almashtirish')->name('substitutions.')->group(function () {
    Route::get('/', [C\SubstitutionController::class, 'index'])->name('index');
    Route::post('/', [C\SubstitutionController::class, 'store'])->name('store');
    Route::delete('/{substitution}', [C\SubstitutionController::class, 'destroy'])->name('destroy');
});

// ----- Fanlar va pedagoglar -----
Route::middleware(['feature:subjects', 'can:subjects.manage'])->prefix('fanlar')->name('subjects.')->group(function () {
    Route::get('/', [C\SubjectController::class, 'index'])->name('index');
    Route::post('/', [C\SubjectController::class, 'store'])->name('store');
    Route::post('/darslar', [C\SubjectController::class, 'lessonStore'])->name('lessons.store');
    Route::delete('/darslar/{lesson}', [C\SubjectController::class, 'lessonDestroy'])->name('lessons.destroy');
    Route::put('/{subject}', [C\SubjectController::class, 'update'])->name('update');
    Route::delete('/{subject}', [C\SubjectController::class, 'destroy'])->name('destroy');
});

// ----- Tadbirlar -----
Route::middleware('feature:events')->group(function () {
    Route::middleware('can:events.manage')->prefix('tadbirlar')->name('events.')->group(function () {
        Route::get('/', [C\EventController::class, 'index'])->name('index');
        Route::get('/yangi', [C\EventController::class, 'create'])->name('create');
        Route::post('/', [C\EventController::class, 'store'])->name('store');
        Route::get('/{event}', [C\EventController::class, 'show'])->name('show');
        Route::put('/{event}', [C\EventController::class, 'update'])->name('update');
        Route::delete('/{event}', [C\EventController::class, 'destroy'])->name('destroy');
        Route::post('/{event}/xarajat', [C\EventController::class, 'costStore'])->name('costs.store');
        Route::delete('/{event}/xarajat/{cost}', [C\EventController::class, 'costDestroy'])->name('costs.destroy');
        Route::put('/{event}/haqiqiy', [C\EventController::class, 'costActual'])->name('costs.actual');
        Route::post('/{event}/xodim', [C\EventController::class, 'staffStore'])->name('staff.store');
        Route::delete('/{event}/xodim/{staff}', [C\EventController::class, 'staffDestroy'])->name('staff.destroy');
        Route::post('/{event}/yuborish', [C\EventController::class, 'submit'])->name('submit');
        Route::post('/{event}/yopish-royxat', [C\EventController::class, 'lock'])->name('lock');
        Route::post('/{event}/otkazildi', [C\EventController::class, 'hold'])->name('hold');
        Route::post('/{event}/yakunlash', [C\EventController::class, 'close'])->name('close');
        Route::post('/{event}/bolalar', [C\EventController::class, 'kidAdd'])->name('kids.add');
        Route::post('/{event}/guruh', [C\EventController::class, 'groupAdd'])->name('kids.group');
        Route::delete('/{event}/bolalar/{kid}', [C\EventController::class, 'kidRemove'])->name('kids.remove');
        Route::post('/{event}/bolalar/{kid}/davomat', [C\EventController::class, 'mark'])->name('kids.mark');
    });
    Route::middleware('can:events.approve')->prefix('tadbirlar')->name('events.')->group(function () {
        Route::post('/{event}/tasdiqlash', [C\EventController::class, 'approve'])->name('approve');
        Route::post('/{event}/qaytarish', [C\EventController::class, 'reject'])->name('reject');
    });
    Route::middleware('can:events.manage')->prefix('guruh-tadbirlari')->name('group-events.')->group(function () {
        Route::get('/', [C\GroupEventController::class, 'index'])->name('index');
        Route::post('/', [C\GroupEventController::class, 'store'])->name('store');
        Route::delete('/{groupEvent}', [C\GroupEventController::class, 'destroy'])->name('destroy');
    });
    Route::middleware('can:events.approve')->prefix('guruh-tadbirlari')->name('group-events.')->group(function () {
        Route::post('/{groupEvent}/tasdiqlash', [C\GroupEventController::class, 'approve'])->name('approve');
        Route::post('/{groupEvent}/rad', [C\GroupEventController::class, 'reject'])->name('reject');
    });
});

// ----- AI -----
Route::prefix('ai')->name('ai.')->group(function () {
    Route::middleware(['feature:ai_chat', 'can:ai.chat'])->group(function () {
        Route::get('/', [C\AiController::class, 'index'])->name('index');
        Route::post('/savol', [C\AiController::class, 'ask'])->middleware('throttle:20,1')->name('ask');
        Route::post('/oqim', [C\AiController::class, 'stream'])->middleware('throttle:20,1')->name('stream');
        Route::post('/{chat}/baho', [C\AiController::class, 'rate'])->name('rate');
    });
    Route::middleware('can:ai.report')->prefix('hisobotlar')->name('reports.')->group(function () {
        Route::get('/', [C\AiReportController::class, 'index'])->name('index');
        Route::get('/{report}', [C\AiReportController::class, 'show'])->name('show');
    });
});

// ----- Hisobot zanjiri -----
Route::middleware('feature:report_chain')->prefix('hisobot-zanjiri')->name('chain.')->group(function () {
    Route::get('/', [C\ReportChainController::class, 'index'])->name('index');
    Route::post('/topshirish', [C\ReportChainController::class, 'submit'])->name('submit');
    Route::post('/{submission}/korish', [C\ReportChainController::class, 'review'])->name('review');
    Route::get('/{submission}/fayl/{i}', [C\ReportChainController::class, 'file'])->whereNumber('i')->name('file');
    Route::middleware('can:report.types')->group(function () {
        Route::post('/turlar', [C\ReportChainController::class, 'typeStore'])->name('types.store');
        Route::put('/turlar/{type}', [C\ReportChainController::class, 'typeUpdate'])->name('types.update');
        Route::delete('/turlar/{type}', [C\ReportChainController::class, 'typeDestroy'])->name('types.destroy');
    });
    Route::post('/kechirish', [C\ReportChainController::class, 'waive'])->middleware('can:report.waive')->name('waive');
});

// ----- Qora ro'yxat -----
Route::middleware(['feature:blacklist', 'can:blacklist.view'])->prefix('qora-royxat')->name('blacklist.')->group(function () {
    Route::get('/', [C\BlacklistController::class, 'index'])->name('index');
    Route::middleware('can:blacklist.manage')->group(function () {
        Route::post('/', [C\BlacklistController::class, 'store'])->name('store');
        Route::delete('/{entry}', [C\BlacklistController::class, 'destroy'])->name('destroy');
    });
});

// ----- Arizalar -----
Route::middleware('feature:leads')->prefix('arizalar')->name('leads.')->group(function () {
    Route::get('/', [C\LeadController::class, 'index'])->middleware('can:leads.view')->name('index');
    Route::middleware('can:leads.manage')->group(function () {
        Route::post('/', [C\LeadController::class, 'store'])->name('store');
        Route::post('/{lead}/holat', [C\LeadController::class, 'move'])->name('move');
        Route::put('/{lead}', [C\LeadController::class, 'update'])->name('update');
        Route::delete('/{lead}', [C\LeadController::class, 'destroy'])->name('destroy');
    });
});

// ----- SMS -----
Route::middleware(['feature:sms', 'can:sms.manage'])->prefix('sms')->name('sms.')->group(function () {
    Route::get('/', [C\SmsController::class, 'index'])->name('index');
    Route::post('/akkaunt', [C\SmsController::class, 'saveAccount'])->name('account');
    Route::post('/akkaunt/tekshirish', [C\SmsController::class, 'checkAccount'])->name('account.check');
    Route::delete('/akkaunt', [C\SmsController::class, 'removeAccount'])->name('account.remove');
    Route::post('/qoidalar', [C\SmsController::class, 'saveRules'])->name('rules');
    Route::post('/shablonlar', [C\SmsController::class, 'templateStore'])->name('templates.store');
    Route::post('/shablonlar/holat', [C\SmsController::class, 'templatesSync'])->name('templates.sync');
    Route::put('/shablonlar/{template}', [C\SmsController::class, 'templateUpdate'])->name('templates.update');
    Route::post('/shablonlar/{template}/yuborish', [C\SmsController::class, 'templateSubmit'])->name('templates.submit');
    Route::post('/shablonlar/{template}/sinov', [C\SmsController::class, 'templateTest'])->name('templates.test');
    Route::delete('/shablonlar/{template}', [C\SmsController::class, 'templateDestroy'])->name('templates.destroy');
    Route::post('/ommaviy', [C\SmsController::class, 'bulk'])->name('bulk');
    Route::post('/qarz-eslatma', [C\SmsController::class, 'debtReminders'])->name('debts');
});

// ----- Telegram bot -----
Route::middleware('feature:telegram')->prefix('telegram')->name('telegram.')->group(function () {
    $c = C\TelegramController::class;
    Route::middleware('can:tg.manage')->group(function () use ($c) {
        Route::get('/', [$c, 'settings'])->name('settings');
        Route::put('/', [$c, 'save'])->name('save');
        Route::post('/tekshirish', [$c, 'check'])->name('check');
        Route::delete('/', [$c, 'disconnect'])->name('disconnect');
        Route::get('/xodimlar', [$c, 'employees'])->name('employees');
        Route::delete('/xodim-akkaunt/{contact}', [$c, 'removeEmployeeContact'])->name('employee-contact.remove');
    });
    Route::middleware('can:tg.contacts')->group(function () use ($c) {
        Route::get('/guruhlar', [$c, 'groupsIndex'])->name('groups');
        Route::get('/guruhlar/{group}', [$c, 'group'])->name('group');
        Route::delete('/akkaunt/{contact}', [$c, 'removeContact'])->name('contact.remove');
        Route::post('/havola/{guardian}/yangilash', [$c, 'revoke'])->name('revoke');
    });
    Route::middleware('can:tg.broadcast')->group(function () use ($c) {
        Route::get('/postlar', [$c, 'posts'])->name('posts');
        Route::post('/elon', [$c, 'announce'])->name('announce');
        Route::delete('/post/{post}', [$c, 'deletePost'])->name('post.delete');
    });
});

// ----- Sog'liq va xavfsizlik (ko'rish — barcha xodimlar; tahrirlash — kids.manage) -----
Route::middleware('feature:health')->prefix('sogliq')->name('health.')->group(function () {
    $c = C\HealthController::class;
    Route::get('/', [$c, 'index'])->name('index');
    Route::get('/{kid}', [$c, 'show'])->name('show');
    Route::middleware('can:kids.manage')->group(function () use ($c) {
        Route::put('/{kid}', [$c, 'update'])->name('update');
        Route::post('/{kid}/olib-ketuvchi', [$c, 'pickupStore'])->name('pickup.store');
        Route::delete('/{kid}/olib-ketuvchi/{pickup}', [$c, 'pickupDestroy'])->name('pickup.destroy');
    });
});

// ----- Ma'lumot import (faqat direktor; tekshiruv kontrollerda) -----
Route::prefix('import')->name('import.')->group(function () {
    $c = C\ImportController::class;
    Route::get('/', [$c, 'index'])->name('index');
    Route::get('/shablon/{type}', [$c, 'template'])->name('template');
    Route::post('/', [$c, 'upload'])->name('upload');
    Route::get('/{run}', [$c, 'show'])->name('show');
    Route::post('/{run}/tasdiqlash', [$c, 'commit'])->name('commit');
    Route::delete('/{run}', [$c, 'discard'])->name('discard');
});

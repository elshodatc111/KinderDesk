<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\App as AppC;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Webhooks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/* ============ Ommaviy sahifalar ============ */
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/robots.txt', [\App\Http\Controllers\Public\SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [\App\Http\Controllers\Public\SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/yangiliklar', [\App\Http\Controllers\Public\NewsController::class, 'index'])->name('news.index');
Route::get('/yangiliklar/feed.xml', [\App\Http\Controllers\Public\NewsController::class, 'feed'])->name('news.feed');
Route::get('/yangiliklar/{slug}', [\App\Http\Controllers\Public\NewsController::class, 'show'])->name('news.show');
Route::get('/yangiliklar/{slug}/muqova', [\App\Http\Controllers\Public\NewsController::class, 'cover'])->name('news.cover');
Route::post('/yangiliklar/{slug}/izoh', [\App\Http\Controllers\Public\NewsController::class, 'comment'])->middleware('throttle:post-comment')->name('news.comment');
Route::get('/huquqiy/{page}', [HomeController::class, 'legal'])->name('legal');
Route::get('/til/{locale}', [HomeController::class, 'locale'])->name('locale');

/* ============ Kirish / ro'yxatdan o'tish ============ */
Route::middleware('guest')->group(function () {
    Route::get('/kirish', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/kirish', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::post('/kirish/kod', [AuthController::class, 'loginOtpSend'])->middleware('throttle:otp')->name('login.otp.send');
    Route::post('/kirish/kod/tasdiq', [AuthController::class, 'loginOtp'])->middleware('throttle:login')->name('login.otp');

    // SMS kelmasa: kodni Telegram (platforma boti) orqali olish
    Route::post('/kod/telegram', [AuthController::class, 'telegramOtp'])->middleware('throttle:otp')->name('otp.telegram');
    Route::get('/kod/telegram/holat', [AuthController::class, 'telegramOtpStatus'])->middleware('throttle:60,1')->name('otp.telegram.status');

    Route::get('/royxatdan-otish', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/royxatdan-otish/kod', [AuthController::class, 'registerOtp'])->middleware('throttle:otp')->name('register.otp');
    Route::post('/royxatdan-otish', [AuthController::class, 'register'])->middleware('throttle:login')->name('register.store');

    Route::get('/parolni-tiklash', [AuthController::class, 'showForgot'])->name('password.forgot');
    Route::post('/parolni-tiklash/kod', [AuthController::class, 'forgotOtp'])->middleware('throttle:otp')->name('password.otp');
    Route::post('/parolni-tiklash', [AuthController::class, 'reset'])->middleware('throttle:login')->name('password.reset');
});
Route::post('/chiqish', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::post('/impersonate/stop', function (\Illuminate\Http\Request $request) {
    $adminId = $request->session()->pull('impersonator_id');
    abort_unless($adminId, 403);
    Auth::loginUsingId($adminId);
    $request->session()->regenerate();

    return redirect()->route('admin.dashboard');
})->middleware('auth')->name('impersonate.stop');

/* ============ Rozilik (oferta / maxfiylik / shartlar) ============ */
Route::middleware('auth')->group(function () {
    Route::get('/rozilik', [\App\Http\Controllers\Auth\ConsentController::class, 'show'])->name('consent.show');
    Route::post('/rozilik', [\App\Http\Controllers\Auth\ConsentController::class, 'accept'])->name('consent.accept');
});

/* ============ Telegram orqali ikki bosqichli tasdiq (direktor va super admin) ============ */
Route::middleware('auth')->prefix('xavfsizlik')->name('twofa.')->group(function () {
    $c = \App\Http\Controllers\Auth\TwoFactorController::class;
    Route::get('/telegram', [$c, 'setup'])->name('setup');
    Route::get('/telegram/holat', [$c, 'status'])->name('status');
    Route::post('/telegram/tasdiq', [$c, 'confirm'])->name('confirm');
    Route::post('/telegram/qurilmalar', [$c, 'forgetDevices'])->name('devices');
    Route::get('/kod', [$c, 'challenge'])->name('challenge');
    Route::post('/kod', [$c, 'verify'])->middleware('throttle:login')->name('verify');
    Route::post('/kod/qayta', [$c, 'resend'])->middleware('throttle:otp')->name('resend');
});

/* ============ Ochiq ariza formasi ============ */
Route::get('/k/{key}', [\App\Http\Controllers\Public\LeadFormController::class, 'show'])->name('lead.form');
Route::post('/k/{key}', [\App\Http\Controllers\Public\LeadFormController::class, 'submit'])->middleware('throttle:public-lead')->name('lead.submit');

/* ============ Tashqi tizimlar (Payme, Eskiz) ============ */
Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function () {
    Route::post('/payme', Webhooks\PaymeController::class)->name('webhooks.payme');
    Route::post('/eskiz', Webhooks\EskizController::class)->name('webhooks.eskiz');
});

/* ============ Telegram webhook (webhook rejimida; polling rejimida ishlatilmaydi) ============ */
Route::post('/tg/{bot}', \App\Http\Controllers\Webhooks\TelegramController::class)->middleware('throttle:webhooks')->whereNumber('bot')->name('tg.webhook');

/* ============ Bog'cha kabineti ============ */
Route::prefix('app')->name('app.')->middleware(['auth', 'secure', 'tenant', 'writable'])->group(function () {
    Route::get('/', [AppC\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/stats', [AppC\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/poll', [NotificationController::class, 'poll'])->name('poll');

    Route::get('/bildirishnomalar', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/bildirishnomalar/oxirgi', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/bildirishnomalar/oqildi', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/video', [AppC\VideoController::class, 'index'])->name('videos');
    Route::get('/profil', [AppC\ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profil', [AppC\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/parol', [AppC\ProfileController::class, 'password'])->name('profile.password');

    Route::middleware('can:subscription.manage')->prefix('obuna')->name('subscription.')->group(function () {
        Route::get('/', [AppC\SubscriptionController::class, 'index'])->name('index');
        Route::post('/sotib-olish', [AppC\SubscriptionController::class, 'buy'])->name('buy');
        Route::post('/balans/chek', [AppC\SubscriptionController::class, 'topupManual'])->name('topup.manual');
        Route::post('/balans/payme', [AppC\SubscriptionController::class, 'topupPayme'])->name('topup.payme');
        Route::get('/chek/{topup}', [AppC\SubscriptionController::class, 'receipt'])->name('receipt');
    });

    // CRM bo'limlari alohida fayllarda
    require __DIR__.'/crm.php';
});

/* ============ Super admin ============ */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'secure', 'super'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/stats', [Admin\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/poll', [NotificationController::class, 'poll'])->name('poll');

    Route::get('/bildirishnomalar', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/bildirishnomalar/oxirgi', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/bildirishnomalar/oqildi', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/bogchalar', [Admin\TenantController::class, 'index'])->name('tenants.index');
    Route::get('/bogchalar/yangi', [Admin\TenantController::class, 'create'])->name('tenants.create');
    Route::post('/bogchalar', [Admin\TenantController::class, 'store'])->name('tenants.store');
    Route::get('/bogchalar/{tenant}', [Admin\TenantController::class, 'show'])->name('tenants.show');
    Route::post('/bogchalar/{tenant}/faqat-korish', [Admin\TenantController::class, 'readonly'])->name('tenants.readonly');
    Route::delete('/bogchalar/{tenant}/faqat-korish', [Admin\TenantController::class, 'readonlyOff'])->name('tenants.readonly.off');
    Route::post('/bogchalar/{tenant}/umrbod', [Admin\TenantController::class, 'lifetime'])->name('tenants.lifetime');
    Route::post('/bogchalar/{tenant}/bepul-muddat', [Admin\TenantController::class, 'grant'])->name('tenants.grant');
    Route::put('/bogchalar/{tenant}/nashr', [Admin\TenantController::class, 'edition'])->name('tenants.edition');
    Route::put('/bogchalar/{tenant}/imkoniyatlar', [Admin\TenantController::class, 'features'])->name('tenants.features');
    Route::post('/bogchalar/{tenant}/blok', [Admin\TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/bogchalar/{tenant}/balans', [Admin\TenantController::class, 'wallet'])->name('tenants.wallet');
    Route::post('/bogchalar/{tenant}/xabar', [Admin\TenantController::class, 'message'])->name('tenants.message');
    Route::post('/bogchalar/{tenant}/2fa-tiklash', [Admin\TenantController::class, 'twofaReset'])->name('tenants.twofa-reset');
    Route::post('/bogchalar/{tenant}/kirish', [Admin\TenantController::class, 'impersonate'])->name('tenants.impersonate');
    Route::delete('/bogchalar/{tenant}', [Admin\TenantController::class, 'destroy'])->name('tenants.destroy');

    Route::get('/cheklar', [Admin\TopupController::class, 'index'])->name('topups.index');
    Route::post('/cheklar/{topup}/tasdiqlash', [Admin\TopupController::class, 'approve'])->name('topups.approve');
    Route::post('/cheklar/{topup}/rad-etish', [Admin\TopupController::class, 'reject'])->name('topups.reject');
    Route::get('/cheklar/{topup}/rasm', [Admin\TopupController::class, 'receipt'])->name('topups.receipt');

    Route::get('/ai', [Admin\AiController::class, 'index'])->name('ai.index');
    Route::put('/ai', [Admin\AiController::class, 'update'])->name('ai.update');
    Route::post('/ai/sinash', [Admin\AiController::class, 'test'])->name('ai.test');
    Route::delete('/ai/kalit', [Admin\AiController::class, 'forgetKey'])->name('ai.key.forget');
    Route::put('/ai/bogcha/{tenant}', [Admin\AiController::class, 'tenant'])->name('ai.tenant');

    Route::get('/tizim', [Admin\SystemController::class, 'index'])->name('system.index');
    Route::get('/tizim/holat', [Admin\SystemController::class, 'data'])->name('system.data');

    Route::get('/tariflar', [Admin\PlanController::class, 'index'])->name('plans.index');
    Route::put('/tariflar/{edition}', [Admin\PlanController::class, 'updateEdition'])->name('plans.update');

    Route::get('/sozlamalar', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/sozlamalar', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/sozlamalar/eskiz-tekshirish', [Admin\SettingsController::class, 'eskizCheck'])->name('settings.eskiz-check');
    Route::post('/sozlamalar/shablonlar/holat', [Admin\SettingsController::class, 'templatesSync'])->name('settings.templates.sync');
    Route::put('/sozlamalar/shablonlar/{template}', [Admin\SettingsController::class, 'templateUpdate'])->name('settings.templates.update');
    Route::post('/sozlamalar/shablonlar/{template}/yuborish', [Admin\SettingsController::class, 'templateSubmit'])->name('settings.templates.submit');
    Route::post('/sozlamalar/shablonlar/{template}/sinov', [Admin\SettingsController::class, 'templateTest'])->name('settings.templates.test');

    Route::put('/sozlamalar/tarmoqlar', [Admin\SettingsController::class, 'social'])->name('settings.social');
    Route::put('/sozlamalar/ulanishlar', [Admin\SettingsController::class, 'connections'])->name('settings.connections');
    Route::put('/sozlamalar/savollar', [Admin\SettingsController::class, 'faq'])->name('settings.faq');
    Route::put('/sozlamalar/huquqiy', [Admin\SettingsController::class, 'legal'])->name('settings.legal');
    Route::delete('/sozlamalar/huquqiy', [Admin\SettingsController::class, 'legalReset'])->name('settings.legal.reset');

    Route::get('/profil', [Admin\ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profil', [Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/parol', [Admin\ProfileController::class, 'password'])->name('profile.password');

    Route::get('/telegram', [Admin\TelegramController::class, 'index'])->name('telegram.index');
    Route::put('/telegram', [Admin\TelegramController::class, 'update'])->name('telegram.update');
    Route::post('/telegram/qollash', [Admin\TelegramController::class, 'apply'])->name('telegram.apply');
    Route::delete('/telegram', [Admin\TelegramController::class, 'forgetToken'])->name('telegram.forget');
    Route::get('/zaxira', [Admin\BackupController::class, 'index'])->name('backup.index');
    Route::put('/zaxira', [Admin\BackupController::class, 'update'])->name('backup.update');
    Route::post('/zaxira/yaratish', [Admin\BackupController::class, 'runNow'])->name('backup.run');
    Route::get('/yangiliklar', [Admin\PostController::class, 'index'])->name('posts.index');
    Route::get('/yangiliklar/yangi', [Admin\PostController::class, 'create'])->name('posts.create');
    Route::post('/yangiliklar', [Admin\PostController::class, 'store'])->name('posts.store');
    Route::get('/yangiliklar/izohlar', [Admin\PostCommentController::class, 'index'])->name('comments.index');
    Route::post('/yangiliklar/izohlar/ommaviy', [Admin\PostCommentController::class, 'bulk'])->name('comments.bulk');
    Route::post('/yangiliklar/izohlar/{comment}/tasdiqlash', [Admin\PostCommentController::class, 'approve'])->name('comments.approve');
    Route::post('/yangiliklar/izohlar/{comment}/yashirish', [Admin\PostCommentController::class, 'hide'])->name('comments.hide');
    Route::post('/yangiliklar/izohlar/{comment}/javob', [Admin\PostCommentController::class, 'reply'])->name('comments.reply');
    Route::delete('/yangiliklar/izohlar/{comment}', [Admin\PostCommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/yangiliklar/{post}/tahrirlash', [Admin\PostController::class, 'edit'])->name('posts.edit');
    Route::put('/yangiliklar/{post}', [Admin\PostController::class, 'update'])->name('posts.update');
    Route::delete('/yangiliklar/{post}', [Admin\PostController::class, 'destroy'])->name('posts.destroy');

    Route::get('/video', [Admin\VideoController::class, 'index'])->name('videos.index');
    Route::post('/video', [Admin\VideoController::class, 'store'])->name('videos.store');
    Route::put('/video/{video}', [Admin\VideoController::class, 'update'])->name('videos.update');
    Route::delete('/video/{video}', [Admin\VideoController::class, 'destroy'])->name('videos.destroy');
    Route::get('/audit', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('/rozilik', [Admin\ConsentLogController::class, 'index'])->name('consents.index');
    Route::get('/rozilik/csv', [Admin\ConsentLogController::class, 'csv'])->name('consents.csv');
});

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Billing\TopupService;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsService;
use App\Support\Site\Faq;
use App\Support\Site\Legal;
use App\Support\Site\LegalDefaults;
use App\Support\Site\PlatformConfig;
use App\Support\Site\Social;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(TopupService $topups)
    {
        // Platforma shablonlari: yo'q bo'lsa standart matn bilan qoralama yaratamiz
        foreach (config('kinderdesk.sms_events') as $key => $def) {
            if ($def['scope'] === 'platform') {
                SmsTemplate::firstOrCreate(['tenant_id' => null, 'event_key' => $key], ['name' => $def['label'], 'body' => $def['body'], 'status' => 'draft']);
            }
        }

        return view('admin.settings', [
            'tab' => in_array(request('tab'), ['conn', 'pay', 'site', 'sms'], true) ? request('tab') : 'conn',
            'conn' => [
                'sms_driver' => PlatformSetting::get('conn.sms_driver') ?: config('services.sms.driver'),
                'eskiz_email' => PlatformSetting::get('conn.eskiz_email') ?: config('services.eskiz.email'),
                'eskiz_from' => PlatformSetting::get('conn.eskiz_from') ?: config('services.eskiz.from'),
                'eskiz_callback_url' => PlatformSetting::get('conn.eskiz_callback_url') ?: config('services.eskiz.callback_url'),
                'eskiz_password_set' => PlatformConfig::has('conn.eskiz_password'),
                'payme_merchant_id' => PlatformSetting::get('conn.payme_merchant_id') ?: config('services.payme.merchant_id'),
                'payme_key_set' => PlatformConfig::has('conn.payme_key'),
                'payme_test_key_set' => PlatformConfig::has('conn.payme_test_key'),
                'payme_test_mode' => (bool) config('services.payme.test_mode'),
                'payme_allowed_ips' => implode(', ', (array) config('services.payme.allowed_ips')),
            ],
            'faq' => Faq::all(),
            'legalTexts' => collect(Legal::PAGES)->map(fn ($l, $p) => ['uz' => Legal::markdown($p, 'uz'), 'ru' => Legal::markdown($p, 'ru'), 'sample_uz' => Legal::isSample($p, 'uz'), 'sample_ru' => Legal::isSample($p, 'ru')])->all(),
            'values' => collect(array_keys(PlatformSetting::DEFAULTS))->mapWithKeys(fn ($k) => [$k => PlatformSetting::get($k)])->all(),
            'templates' => SmsTemplate::whereNull('tenant_id')->orderBy('id')->get(),
            'events' => config('kinderdesk.sms_events'),
            'smsDriver' => config('services.sms.driver'),
            'paymeEnabled' => $topups->paymeEnabled(),
            'paymeTest' => config('services.payme.test_mode'),
            'logs' => SmsLog::whereNull('tenant_id')->latest('id')->limit(10)->get(),
            'eskizConfigured' => filled(config('services.eskiz.email')) && filled(config('services.eskiz.password')),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'payment_card_number' => 'nullable|string|max:30',
            'payment_card_holder' => 'nullable|string|max:80',
            'payment_card_bank' => 'nullable|string|max:60',
            'payment_min_topup' => 'required|integer|min:1000',
            'payment_note' => 'nullable|string|max:300',
            'support_phone' => 'nullable|string|max:30',
            'support_telegram' => 'nullable|string|max:60',
            'support_from' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'support_to' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'support_days' => 'nullable|array',
            'support_days.*' => 'integer|between:1,7',
            'support_response' => 'nullable|string|max:80',
        ]);
        $data['support_days'] = implode(',', collect($data['support_days'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all());
        foreach ($data as $k => $v) {
            // payment_card_number -> payment.card_number
            PlatformSetting::put(preg_replace('/^(payment|support)_/', '$1.', $k), $v);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'pay'])->with('success', 'Sozlamalar saqlandi');
    }

    /** Footerdagi Telegram / Instagram / YouTube havolalari. */
    public function social(Request $request)
    {
        $request->validate(['social_telegram' => 'nullable|string|max:200', 'social_instagram' => 'nullable|string|max:200', 'social_youtube' => 'nullable|string|max:200']);
        $clean = [];
        $errors = [];
        foreach (array_keys(Social::NETWORKS) as $net) {
            $url = Social::normalize($net, $request->input('social_'.$net));
            if ($url === null) {
                $errors['social_'.$net] = __("Havola noto'g'ri. @nom yoki :label havolasini kiriting", ['label' => Social::NETWORKS[$net]['label']]);
            } else {
                $clean[$net] = $url;
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        foreach ($clean as $net => $url) {
            PlatformSetting::put('social.'.$net, $url === '' ? null : $url);
        }
        \App\Models\AuditLog::record('platform.social_updated', null, $clean);

        return redirect()->route('admin.settings.index', ['tab' => 'pay'])->with('success', 'Ijtimoiy tarmoqlar saqlandi');
    }

    /** Payme va Eskiz kalitlari: bazada shifrlangan. Bo'sh qoldirilgan maxfiy maydon eski qiymatni saqlab qoladi. */
    public function connections(Request $request)
    {
        $data = $request->validate([
            'sms_driver' => 'required|in:log,eskiz', 'eskiz_email' => 'nullable|string|max:120', 'eskiz_password' => 'nullable|string|max:200',
            'eskiz_from' => 'nullable|string|max:30', 'eskiz_callback_url' => 'nullable|url|max:200',
            'payme_merchant_id' => 'nullable|string|max:80', 'payme_key' => 'nullable|string|max:200', 'payme_test_key' => 'nullable|string|max:200',
            'payme_test_mode' => 'nullable|boolean', 'payme_allowed_ips' => 'nullable|string|max:300',
        ]);
        foreach (['sms_driver', 'eskiz_email', 'eskiz_from', 'eskiz_callback_url', 'payme_merchant_id', 'payme_allowed_ips'] as $k) {
            PlatformConfig::put('conn.'.$k, $data[$k] ?? null);
        }
        PlatformConfig::put('conn.payme_test_mode', $request->boolean('payme_test_mode') ? '1' : '0');
        foreach (['eskiz_password', 'payme_key', 'payme_test_key'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                PlatformConfig::put('conn.'.$secret, $data[$secret]);
            }
        }
        if ($request->boolean('forget_eskiz')) {
            PlatformConfig::put('conn.eskiz_password', null);
        }
        if ($request->boolean('forget_payme')) {
            PlatformConfig::put('conn.payme_key', null);
            PlatformConfig::put('conn.payme_test_key', null);
        }
        \App\Models\AuditLog::record('platform.connections_updated', null, ['sms_driver' => $data['sms_driver']]);

        return redirect()->route('admin.settings.index', ['tab' => 'conn'])->with('success', 'Ulanishlar saqlandi');
    }

    public function faq(Request $request)
    {
        $request->validate(['faq' => 'nullable|array|max:40', 'faq.*.q_uz' => 'nullable|string|max:250', 'faq.*.q_ru' => 'nullable|string|max:250', 'faq.*.a_uz' => 'nullable|string|max:1500', 'faq.*.a_ru' => 'nullable|string|max:1500']);
        Faq::save((array) $request->input('faq', []));

        return redirect()->route('admin.settings.index', ['tab' => 'site'])->with('success', "Savollar saqlandi");
    }

    public function legal(Request $request)
    {
        $data = $request->validate(['page' => 'required|in:oferta,privacy,terms', 'text_uz' => 'nullable|string|max:60000', 'text_ru' => 'nullable|string|max:60000']);
        if ($request->boolean('reconsent')) {
            app(\App\Services\Security\ConsentService::class)->bump();
        }
        foreach (['uz', 'ru'] as $l) {
            $text = trim((string) ($data['text_'.$l] ?? ''));
            // Namunaning o'zi bo'lsa, saqlamaymiz (keyin namuna yangilansa, avtomatik yangilanadi)
            PlatformSetting::put("legal.{$data['page']}.$l", $text === '' || $text === trim(LegalDefaults::get($data['page'], $l)) ? null : $text);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'site'])->with('success', "Matn saqlandi");
    }

    public function legalReset(Request $request)
    {
        $page = $request->validate(['page' => 'required|in:oferta,privacy,terms'])['page'];
        PlatformSetting::put("legal.$page.uz", null);
        PlatformSetting::put("legal.$page.ru", null);

        return redirect()->route('admin.settings.index', ['tab' => 'site'])->with('success', "Namuna matn qaytarildi");
    }

    public function templateUpdate(Request $request, SmsTemplate $template)
    {
        abort_if($template->tenant_id !== null, 404);
        $data = $request->validate(['body' => 'required|string|max:480']);
        $template->update(['body' => $data['body'], 'status' => 'draft', 'rejection_reason' => null, 'eskiz_template_id' => null]);

        return back()->with('success', 'Matn saqlandi. Eskizga tasdiqlash uchun yuboring.');
    }

    public function templateSubmit(SmsTemplate $template, SmsService $sms)
    {
        abort_if($template->tenant_id !== null, 404);
        try {
            $sms->submitTemplate($template, null);
        } catch (\Throwable $e) {
            return back()->with('error', 'Yuborilmadi: '.$e->getMessage());
        }

        return back()->with('success', 'Eskiz moderatsiyasiga yuborildi');
    }

    public function templatesSync(SmsService $sms)
    {
        try {
            $n = $sms->syncTemplates(null);
        } catch (\Throwable $e) {
            return back()->with('error', 'Tekshirib bo\'lmadi: '.$e->getMessage());
        }

        return back()->with('success', __("Holatlar yangilandi (:n ta shablon)", ['n' => $n]));
    }

    public function templateTest(Request $request, SmsTemplate $template, SmsManager $manager, SmsService $sms)
    {
        abort_if($template->tenant_id !== null, 404);
        $data = $request->validate(['phone' => 'required|string']);
        $phone = normalize_phone($data['phone']);
        if (! $phone) {
            return back()->with('error', "Telefon raqam noto'g'ri");
        }
        $message = $sms->render($template->body, ['kod' => '123456', 'bogcha' => 'Sinov bog\'chasi', 'sana' => now()->format('d.m.Y')]);
        $result = $manager->platform()->send($phone, $message);
        SmsLog::create(['tenant_id' => null, 'to' => $phone, 'event_key' => 'test', 'template_id' => $template->id, 'message' => $message,
            'status' => $result->ok ? 'sent' : 'failed', 'provider_id' => $result->providerId, 'error' => $result->error]);

        return back()->with($result->ok ? 'success' : 'error', $result->ok ? 'Sinov SMS yuborildi' : 'Yuborilmadi: '.$result->error);
    }

    public function eskizCheck(SmsManager $manager)
    {
        if (config('services.sms.driver') !== 'eskiz') {
            return back()->with('error', "SMS rejimi «Sinov (log)». «Ulanishlar» bo'limida «Eskiz (haqiqiy yuborish)» ni tanlang.");
        }
        try {
            $balance = $manager->platformClient()->balance();
        } catch (\Throwable $e) {
            return back()->with('error', 'Eskiz bilan ulanib bo\'lmadi: '.$e->getMessage());
        }

        return back()->with('success', "Eskiz ulangan. Balans: ".number_format((int) $balance, 0, '', ' ')." SMS");
    }
}

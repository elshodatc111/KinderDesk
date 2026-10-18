<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\SmsEventRule;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\TenantSmsAccount;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private SmsService $sms)
    {
    }

    public function index(Request $request)
    {
        $t = tenant();
        $tab = $request->query('tab', 'events');
        $events = collect(config('kinderdesk.sms_events'))->filter(fn ($d) => $d['scope'] === 'tenant');

        return view('app.sms.index', [
            'tab' => $tab,
            'account' => TenantSmsAccount::where('tenant_id', $t->id)->first(),
            'events' => $events,
            'rules' => SmsEventRule::where('tenant_id', $t->id)->get()->keyBy('event_key'),
            'templates' => SmsTemplate::where('tenant_id', $t->id)->orderBy('id')->get(),
            'logs' => $tab === 'logs' ? SmsLog::where('tenant_id', $t->id)->latest('id')->paginate(30)->withQueryString() : collect(),
            'groups' => \App\Models\Group::active()->orderBy('name')->get(),
            'bulkTemplate' => SmsTemplate::where('tenant_id', $t->id)->where('event_key', 'bulk')->first(),
        ]);
    }

    /* ---------- Eskiz akkaunt ---------- */

    public function saveAccount(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'nullable|string|max:200', 'sender' => 'nullable|string|max:30', 'is_active' => 'nullable|boolean']);
        $account = TenantSmsAccount::where('tenant_id', tenant()->id)->first();
        if (! $account && empty($data['password'])) {
            return back()->withErrors(['password' => 'Eskiz parolini kiriting'])->withInput();
        }
        $payload = ['email' => $data['email'], 'sender' => $data['sender'] ?? null, 'is_active' => $request->boolean('is_active', true)];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['token'] = null;
        }
        $account = TenantSmsAccount::updateOrCreate(['tenant_id' => tenant()->id], $payload);
        tenant()->unsetRelation('smsAccount');

        try {
            $balance = $this->sms->checkAccount(tenant()->fresh());

            return redirect()->route('app.sms.index', ['tab' => 'account'])->with('success', "Eskiz ulandi. Balans: ".number_format($balance, 0, '', ' ')." SMS");
        } catch (\Throwable $e) {
            return redirect()->route('app.sms.index', ['tab' => 'account'])->with('error', "Saqlandi, lekin ulanib bo'lmadi: ".$e->getMessage());
        }
    }

    public function checkAccount()
    {
        try {
            $b = $this->sms->checkAccount(tenant()->fresh());

            return back()->with('success', "Ulanish yaxshi. Balans: ".number_format($b, 0, '', ' ')." SMS");
        } catch (\Throwable $e) {
            return back()->with('error', "Ulanib bo'lmadi: ".$e->getMessage());
        }
    }

    public function removeAccount()
    {
        TenantSmsAccount::where('tenant_id', tenant()->id)->delete();

        return back()->with('success', 'Eskiz akkaunti uzildi');
    }

    /* ---------- Qoidalar ---------- */

    public function saveRules(Request $request)
    {
        $t = tenant();
        $input = $request->input('rules', []);
        foreach (config('kinderdesk.sms_events') as $key => $def) {
            if ($def['scope'] !== 'tenant') {
                continue;
            }
            $r = $input[$key] ?? [];
            $rule = SmsEventRule::firstOrNew(['tenant_id' => $t->id, 'event_key' => $key]);
            $rule->enabled = ! empty($r['enabled']);
            $tplId = $r['template_id'] ?? null;
            if ($tplId && SmsTemplate::where('tenant_id', $t->id)->whereKey($tplId)->exists()) {
                $rule->template_id = $tplId;
            }
            $rule->options = array_filter([
                'quiet_from' => preg_match('/^\d{2}:\d{2}$/', $r['quiet_from'] ?? '') ? $r['quiet_from'] : null,
                'quiet_to' => preg_match('/^\d{2}:\d{2}$/', $r['quiet_to'] ?? '') ? $r['quiet_to'] : null,
            ]);
            $rule->save();
        }

        return redirect()->route('app.sms.index', ['tab' => 'events'])->with('success', 'SMS qoidalari saqlandi');
    }

    /* ---------- Shablonlar ---------- */

    public function templateStore(Request $request)
    {
        $data = $request->validate(['event_key' => 'required|string', 'name' => 'required|string|max:80', 'body' => 'required|string|max:480']);
        abort_unless(config("kinderdesk.sms_events.{$data['event_key']}.scope") === 'tenant', 422);
        SmsTemplate::create($data + ['tenant_id' => tenant()->id, 'status' => 'draft']);

        return redirect()->route('app.sms.index', ['tab' => 'templates'])->with('success', "Shablon qo'shildi");
    }

    public function templateUpdate(Request $request, SmsTemplate $template)
    {
        $this->own($template);
        $data = $request->validate(['name' => 'required|string|max:80', 'body' => 'required|string|max:480']);
        $changed = trim($data['body']) !== trim($template->body);
        $template->update($data + ($changed ? ['status' => 'draft', 'eskiz_template_id' => null, 'rejection_reason' => null] : []));

        return back()->with('success', $changed ? "Matn o'zgardi: Eskizga qayta tasdiqlashga yuboring" : 'Saqlandi');
    }

    public function templateSubmit(SmsTemplate $template)
    {
        $this->own($template);
        try {
            $this->sms->submitTemplate($template, tenant()->fresh());
        } catch (\Throwable $e) {
            return back()->with('error', 'Yuborilmadi: '.$e->getMessage());
        }

        return back()->with('success', "Eskiz moderatsiyasiga yuborildi. Odatda tasdiqlash bir necha soat oladi. «Holatni tekshirish» tugmasi bilan kuzating.");
    }

    public function templatesSync()
    {
        try {
            $n = $this->sms->syncTemplates(tenant()->fresh());
        } catch (\Throwable $e) {
            return back()->with('error', "Tekshirib bo'lmadi: ".$e->getMessage());
        }

        return back()->with('success', __("Holatlar yangilandi (:n ta shablon)", ['n' => $n]));
    }

    public function templateTest(Request $request, SmsTemplate $template)
    {
        $this->own($template);
        $data = $request->validate(['phone' => 'required|string']);
        $res = $this->sms->sendTest(tenant()->fresh(), $template, $data['phone']);

        return back()->with($res->ok ? 'success' : 'error', $res->ok ? "Sinov SMS yuborildi. Telefoningizni tekshiring." : 'Yuborilmadi: '.$res->error);
    }

    public function templateDestroy(SmsTemplate $template)
    {
        $this->own($template);
        if (SmsEventRule::where('tenant_id', tenant()->id)->where('template_id', $template->id)->exists()) {
            return back()->with('error', "Bu shablon qoidada ishlatilmoqda. Avval qoidada boshqasini tanlang.");
        }
        $template->delete();

        return back()->with('success', "Shablon o'chirildi");
    }

    /* ---------- Ommaviy xabar va qarz eslatmasi ---------- */

    public function bulk(Request $request)
    {
        $data = $request->validate(['text' => 'required|string|max:300', 'group_id' => 'nullable|integer', 'only_debtors' => 'nullable|boolean']);
        $q = Kid::active();
        if (! empty($data['group_id'])) {
            $q->whereHas('currentLink', fn ($x) => $x->where('group_id', $data['group_id']));
        }
        if ($request->boolean('only_debtors')) {
            $q->where('balance', '<', 0);
        }
        $phones = $q->pluck('parent_phone')->filter()->unique()->values();
        if ($phones->isEmpty()) {
            return back()->with('error', 'Qabul qiluvchi topilmadi');
        }
        [$sent, $blocked] = [0, 0];
        foreach ($phones as $phone) {
            $log = $this->sms->sendTenant(tenant(), $phone, 'bulk', ['matn' => $data['text']]);
            ($log && $log->status !== 'blocked') ? $sent++ : $blocked++;
        }

        return back()->with($sent ? 'success' : 'error', $sent ? __(":n ta SMS navbatga qo'yildi", ['n' => $sent]) : "Yuborilmadi: «Jurnal» bo'limida sababini ko'ring (Eskiz ulanmagan yoki shablon tasdiqlanmagan bo'lishi mumkin)");
    }

    public function debtReminders()
    {
        $kids = Kid::active()->where('balance', '<', 0)->get();
        $sent = 0;
        foreach ($kids as $k) {
            $log = $this->sms->sendTenant(tenant(), $k->parent_phone, 'debt_reminder', ['bola' => $k->full_name, 'summa' => money(abs($k->balance))]);
            if ($log && $log->status !== 'blocked') {
                $sent++;
            }
        }

        return back()->with($sent ? 'success' : 'error', $sent ? __(":n ta qarz eslatmasi yuborildi", ['n' => $sent]) : "Yuborilmadi. «Jurnal»dan sababini ko'ring.");
    }

    private function own(SmsTemplate $t): void
    {
        abort_unless($t->tenant_id === tenant()->id, 404);
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\Sms\SmsService;
use App\Support\Realtime;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/** Bog'chaning ochiq ariza formasi: /k/{public_key}. Kirish talab qilinmaydi. */
class LeadFormController extends Controller
{
    private function tenant(string $key): Tenant
    {
        $t = Tenant::where('public_key', $key)->where('status', 'active')->firstOrFail();
        abort_unless($t->hasFeature('leads') && $t->setting('leads.public_form', true) && ! $t->isReadOnly(), 404);

        return $t;
    }

    public function show(string $key)
    {
        return view('public.lead-form', ['t' => $this->tenant($key), 'done' => session('done')]);
    }

    public function submit(Request $request, string $key, TenantContext $ctx, NotificationService $notify, SmsService $sms)
    {
        $t = $this->tenant($key);
        // Spam tuzog'i: oddiy foydalanuvchi bu maydonni ko'rmaydi va to'ldirmaydi
        if ($request->filled('website')) {
            return back()->with('done', true);
        }
        $data = $request->validate([
            'type' => 'required|in:kid,employee', 'name' => 'required|string|max:150', 'phone' => 'required|string',
            'child_name' => 'nullable|string|max:150', 'child_age' => 'nullable|string|max:30', 'position' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);
        $phone = normalize_phone($data['phone']);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => "Telefon raqamni to'g'ri kiriting. Masalan: 90 123 45 67"]);
        }

        $lead = $ctx->run($t, function () use ($t, $data, $phone) {
            // Takroriy yuborishni cheklaymiz: 10 daqiqada bir xil raqam
            $dup = Lead::where('phone', $phone)->where('created_at', '>', now()->subMinutes(10))->exists();
            if ($dup) {
                return null;
            }
            $statuses = array_values((array) $t->setting('leads.statuses'));

            return Lead::create([
                'tenant_id' => $t->id, 'type' => $data['type'], 'status' => $statuses[0] ?? 'Yangi', 'result' => 'open',
                'name' => $data['name'], 'phone' => $phone, 'source' => 'Sayt', 'note' => $data['note'] ?? null,
                'data' => array_filter(['Bola' => $data['child_name'] ?? null, 'Yoshi' => $data['child_age'] ?? null, 'Lavozim' => $data['position'] ?? null]),
            ]);
        });

        if ($lead) {
            $notify->toTenantUsers($t, 'leads.view', 'Yangi ariza: '.$lead->name, format_phone($phone), url('/app/arizalar?type='.$lead->type), 'info', 'inbox');
            Realtime::tenant($t->id, 'lead', ['message' => 'Yangi ariza: '.$lead->name, 'level' => 'info']);
            try {
                $sms->sendTenant($t, $phone, 'lead_welcome');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('lead.form', $key)->with('done', true);
    }
}

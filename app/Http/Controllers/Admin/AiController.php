<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\AiGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/** Super admin: AI kalit, modellar, narxlar, limitlar va xarajat. Bog'cha ma'lumotini ko'rmaydi — faqat sonlar. */
class AiController extends Controller
{
    private const SETTINGS = ['ai.model_chat', 'ai.model_report', 'ai.price_in', 'ai.price_out', 'ai.price_cache',
        'ai.questions.standard', 'ai.questions.premium', 'ai.tokens.standard', 'ai.tokens.premium'];

    public function index(AiClient $client, AiGate $gate)
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $sum = fn ($from) => AiUsage::where('created_at', '>=', $from)->where('status', 'ok')->selectRaw('COALESCE(SUM(input_tokens),0) i, COALESCE(SUM(output_tokens),0) o, COALESCE(SUM(cache_tokens),0) c, COALESCE(SUM(cost_micro),0) cost, COUNT(*) n')->first();

        $byTenant = AiUsage::where('created_at', '>=', $month)->where('status', 'ok')->whereNotNull('tenant_id')
            ->selectRaw('tenant_id, SUM(input_tokens + output_tokens) tokens, SUM(cost_micro) cost, COUNT(*) n')->groupBy('tenant_id')->orderByDesc('cost')->limit(15)->get();
        $tenants = Tenant::with('edition')->whereIn('id', $byTenant->pluck('tenant_id'))->get()->keyBy('id');

        $trend = AiUsage::where('created_at', '>=', now()->subDays(29)->startOfDay())->where('status', 'ok')
            ->selectRaw('DATE(created_at) d, SUM(cost_micro) cost, SUM(input_tokens + output_tokens) tokens')->groupBy('d')->orderBy('d')->get()->keyBy('d');
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString())->map(fn ($d) => ['d' => $d, 'cost' => (int) ($trend[$d]->cost ?? 0)]);

        return view('admin.ai', [
            'configured' => filled($client->key()), 'enabled' => PlatformSetting::get('ai.enabled', '1') === '1',
            'values' => collect(self::SETTINGS)->mapWithKeys(fn ($k) => [$k => PlatformSetting::get($k)])->all(),
            'today' => $sum($today), 'month' => $sum($month),
            'byKind' => AiUsage::where('created_at', '>=', $month)->where('status', 'ok')->selectRaw('kind, COUNT(*) n, SUM(cost_micro) cost')->groupBy('kind')->get(),
            'byTenant' => $byTenant->map(fn ($r) => [
                'tenant' => $tenants[$r->tenant_id] ?? null, 'tokens' => (int) $r->tokens, 'cost' => (int) $r->cost, 'n' => (int) $r->n,
                'limit' => ($tenants[$r->tenant_id] ?? null) ? $gate->monthlyTokenLimit($tenants[$r->tenant_id]) : 0,
            ]),
            'trend' => $days, 'blocked' => AiUsage::where('created_at', '>=', $month)->where('status', 'blocked')->count(),
            'lastOk' => PlatformSetting::get('ai.last_ok_at'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'api_key' => 'nullable|string|max:300', 'enabled' => 'nullable|boolean',
            'ai_model_chat' => 'required|string|max:60', 'ai_model_report' => 'required|string|max:60',
            'ai_price_in' => 'required|numeric|min:0', 'ai_price_out' => 'required|numeric|min:0', 'ai_price_cache' => 'required|numeric|min:0',
            'ai_questions_standard' => 'required|integer|min:0', 'ai_questions_premium' => 'required|integer|min:0',
            'ai_tokens_standard' => 'required|integer|min:0', 'ai_tokens_premium' => 'required|integer|min:0',
        ]);
        if (filled($data['api_key'] ?? null)) {
            PlatformSetting::put('ai.api_key', Crypt::encryptString(trim($data['api_key'])));
            AuditLog::record('ai.key_set', null, [], null);
        }
        PlatformSetting::put('ai.enabled', $request->boolean('enabled') ? '1' : '0');
        foreach (self::SETTINGS as $k) {
            PlatformSetting::put($k, (string) $data[str_replace('.', '_', $k)]);
        }
        AuditLog::record('ai.settings_changed', null, [], null);

        return back()->with('success', 'AI sozlamalari saqlandi');
    }

    public function forgetKey()
    {
        PlatformSetting::put('ai.api_key', null);
        AuditLog::record('ai.key_removed', null, [], null);

        return back()->with('success', "API kalit o'chirildi");
    }

    public function test(AiClient $client)
    {
        try {
            $r = $client->complete('Reply with the single word: ok', [['role' => 'user', 'content' => 'ping']], 'test', null, auth()->user(), 20, $client->model('chat'));
        } catch (AiException $e) {
            return back()->with('error', 'Sinov muvaffaqiyatsiz: '.$e->getMessage());
        }

        return back()->with('success', 'Ulanish ishlayapti ('.trim($r['text']).'; '.$r['input'].'+'.$r['output'].' token)');
    }

    public function tenant(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['ai_token_limit' => 'nullable|integer|min:0']);
        $tenant->update(['ai_disabled' => $request->boolean('ai_disabled'), 'ai_token_limit' => $data['ai_token_limit'] ?? null]);
        AuditLog::record('tenant.ai_changed', $tenant, ['disabled' => $tenant->ai_disabled, 'limit' => $tenant->ai_token_limit], $tenant->id);

        return back()->with('success', "Bog'cha AI sozlamasi saqlandi");
    }
}

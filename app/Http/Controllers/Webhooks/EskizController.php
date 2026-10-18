<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;

/** Eskiz yetkazib berish holati (callback). */
class EskizController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = (string) ($request->input('request_id') ?? $request->input('id') ?? '');
        $status = strtolower((string) ($request->input('status') ?? ''));
        if ($id !== '') {
            $new = match (true) {
                in_array($status, ['delivered', 'delivrd', 'success'], true) => 'delivered',
                in_array($status, ['rejected', 'undelivered', 'failed', 'expired', 'deleted'], true) => 'failed',
                default => null,
            };
            if ($new) {
                SmsLog::withoutGlobalScopes()->where('provider_id', $id)->update(['status' => $new, 'updated_at' => now()]);
            }
        }

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymeService;
use Illuminate\Http\Request;

class PaymeController extends Controller
{
    public function __invoke(Request $request, PaymeService $payme)
    {
        // Payme har doim HTTP 200 va JSON-RPC javob kutadi
        return response()->json($payme->handle($request), 200, [], JSON_UNESCAPED_UNICODE);
    }
}

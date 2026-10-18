<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AiChat;
use App\Services\Ai\AiException;
use App\Services\Ai\AiGate;
use App\Services\Ai\ChatService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function index(Request $request, AiGate $gate)
    {
        $t = tenant();

        return view('app.ai.index', [
            'history' => AiChat::where('user_id', $request->user()->id)->latest('id')->limit(30)->get()->reverse()->values(),
            'blocked' => $gate->tenantReady($t, 'ai_chat'),
            'limit' => $gate->dailyQuestionLimit($t),
            'used' => $gate->questionsToday($request->user()),
        ]);
    }

    public function ask(Request $request, ChatService $chat, AiGate $gate)
    {
        $data = $request->validate(['question' => 'required|string|min:2|max:600']);
        try {
            $row = $chat->ask($request->user(), $data['question'], app()->getLocale() === 'ru' ? 'ru' : 'uz');
        } catch (AiException $e) {
            return response()->json(['message' => __($e->getMessage())], 422);
        }

        return response()->json([
            'id' => $row->id, 'question' => $row->question, 'answer' => $row->answer, 'masked' => $row->masked,
            'left' => max(0, $gate->dailyQuestionLimit(tenant()) - $gate->questionsToday($request->user())),
        ]);
    }

    /** Jonli javob (NDJSON oqimi): {t:delta,text} ... {t:done,id,left,masked} yoki {t:error,message}. */
    public function stream(Request $request, ChatService $chat, AiGate $gate)
    {
        $data = $request->validate(['question' => 'required|string|min:2|max:600']);
        $user = $request->user();
        $lang = app()->getLocale() === 'ru' ? 'ru' : 'uz';
        $tenant = tenant();

        return response()->stream(function () use ($chat, $gate, $data, $user, $lang, $tenant) {
            @set_time_limit(150);
            @ini_set('zlib.output_compression', '0');
            $send = function (array $row) {
                echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            $send(['t' => 'start']);
            try {
                $row = app(\App\Support\Tenancy\TenantContext::class)->run($tenant, fn () => $chat->askStream($user, $data['question'], $lang, fn ($d) => $send(['t' => 'delta', 'text' => $d])));
                $left = app(\App\Support\Tenancy\TenantContext::class)->run($tenant, fn () => max(0, $gate->dailyQuestionLimit($tenant) - $gate->questionsToday($user)));
                $send(['t' => 'done', 'id' => $row->id, 'masked' => (bool) $row->masked, 'left' => $left, 'answer' => $row->answer]);
            } catch (AiException $e) {
                $send(['t' => 'error', 'message' => __($e->getMessage())]);
            } catch (\Throwable $e) {
                report($e);
                $send(['t' => 'error', 'message' => __('Xatolik yuz berdi. Qayta urinib ko\'ring.')]);
            }
        }, 200, ['Content-Type' => 'application/x-ndjson; charset=utf-8', 'Cache-Control' => 'no-cache, no-transform', 'X-Accel-Buffering' => 'no']);
    }

    public function rate(Request $request, AiChat $chat)
    {
        abort_unless($chat->user_id === $request->user()->id, 403);
        $chat->update(['rating' => $request->integer('rating') > 0 ? 1 : -1]);

        return response()->json(['ok' => true]);
    }
}

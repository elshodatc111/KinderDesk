<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupJob;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Tashqi zaxira tizimi uchun 2 ta endpoint: so'rov (job yaratadi) va yuklab olish. Login + parol + IP ro'yxati. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backup)
    {
    }

    private function deny(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (! $this->backup->enabled()) {
            return response()->json(['error' => 'disabled'], 503);
        }
        if (! $this->backup->ipAllowed((string) $request->ip())) {
            $this->backup->log('backup.denied', $request, ['why' => 'ip']);

            return response()->json(['error' => 'forbidden'], 403);
        }
        if (! $this->backup->credentialsOk($request)) {
            $this->backup->log('backup.denied', $request, ['why' => 'credentials']);

            return response()->json(['error' => 'unauthorized'], 401);
        }

        return null;
    }

    private function payload(BackupJob $job): array
    {
        return [
            'job_id' => $job->uid, 'status' => $job->status, 'date' => $job->for_date->toDateString(),
            'ready_at' => $job->ready_at?->toIso8601String(), 'expires_at' => $job->expires_at?->toIso8601String(),
            'size' => $job->size, 'sha256' => $job->sha256, 'download_url' => route('api.backup.download', $job->uid),
        ];
    }

    public function request(Request $request)
    {
        if ($r = $this->deny($request)) {
            return $r;
        }
        $job = $this->backup->requestJob((string) $request->ip());
        $this->backup->log('backup.request', $request, ['job' => $job->uid, 'status' => $job->status]);

        return response()->json($this->payload($job), $job->status === 'ready' ? 200 : 202);
    }

    public function download(Request $request, string $uid)
    {
        if ($r = $this->deny($request)) {
            return $r;
        }
        $job = BackupJob::where('uid', $uid)->first();
        if (! $job) {
            return response()->json(['error' => 'not_found'], 404);
        }
        if ($job->status === 'expired') {
            return response()->json(['error' => 'expired'] + $this->payload($job), 410);
        }
        if ($job->status === 'failed') {
            return response()->json(['error' => 'failed', 'message' => $job->error] + $this->payload($job), 500);
        }
        if ($job->status !== 'ready' || ! $job->path || ! Storage::disk('local')->exists($job->path)) {
            return response()->json($this->payload($job), 202, ['Retry-After' => 15]);
        }
        $job->increment('downloads');
        $this->backup->log('backup.download', $request, ['job' => $job->uid]);

        return response()->download(Storage::disk('local')->path($job->path), 'kinderdesk-'.$job->for_date->format('Y-m-d').'.sql', [
            'Content-Type' => 'application/sql', 'X-Backup-Sha256' => (string) $job->sha256,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupJob;
use App\Models\PlatformSetting;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index(BackupService $backup)
    {
        return view('admin.backup', [
            'enabled' => PlatformSetting::get('backup.enabled', '0') === '1', 'login' => PlatformSetting::get('backup.login', ''),
            'passwordSet' => filled(PlatformSetting::get('backup.password_hash')), 'ips' => PlatformSetting::get('backup.ips', ''),
            'dumpBin' => PlatformSetting::get('backup.mysqldump', ''), 'jobs' => BackupJob::latest('id')->limit(15)->get(),
            'myIp' => request()->ip(),
        ]);
    }

    public function update(Request $request, BackupService $backup)
    {
        $d = $request->validate([
            'login' => 'required|string|min:4|max:60', 'password' => 'nullable|string|min:12|max:100', 'ips' => 'nullable|string|max:500', 'mysqldump' => 'nullable|string|max:255',
        ]);
        if (! PlatformSetting::get('backup.password_hash') && ! filled($d['password'] ?? null)) {
            return back()->withInput()->withErrors(['password' => "Parolni kiriting (kamida 12 belgi)"]);
        }
        $backup->setCredentials($d['login'], $d['password'] ?? null);
        PlatformSetting::put('backup.ips', trim((string) ($d['ips'] ?? '')));
        PlatformSetting::put('backup.mysqldump', trim((string) ($d['mysqldump'] ?? '')));
        PlatformSetting::put('backup.enabled', $request->boolean('enabled') ? '1' : '0');
        \App\Models\AuditLog::record('backup.settings');

        return back()->with('success', 'Saqlandi');
    }

    /** Sinov: hozir nusxa yaratadi (xuddi API so'rovidek). */
    public function runNow(BackupService $backup, Request $request)
    {
        $job = $backup->requestJob((string) $request->ip());
        $backup->run($job);

        return back()->with($job->fresh()->status === 'failed' ? 'error' : 'success', 'Holat: '.$job->fresh()->status.($job->fresh()->error ? ' — '.$job->fresh()->error : ''));
    }
}

<?php

namespace App\Console\Commands;

use App\Models\BackupJob;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'backup:run {uid? : Ish identifikatori} {--pending : Navbatdagi barcha ishlarni bajarish}';

    protected $description = "Ma'lumotlar bazasi zaxira nusxasini (.sql) yaratadi";

    public function handle(BackupService $backup): int
    {
        if ($this->option('pending') || ! $this->argument('uid')) {
            $this->info($backup->runPending().' ta ish bajarildi');

            return self::SUCCESS;
        }
        $job = BackupJob::where('uid', $this->argument('uid'))->first();
        if (! $job) {
            $this->error('Ish topilmadi');

            return self::FAILURE;
        }
        $backup->run($job);
        $this->info($job->fresh()->status);

        return self::SUCCESS;
    }
}

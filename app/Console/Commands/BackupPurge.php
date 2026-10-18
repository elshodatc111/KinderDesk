<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupPurge extends Command
{
    protected $signature = 'backup:purge';

    protected $description = "24 soatdan o'tgan zaxira nusxalarni o'chiradi";

    public function handle(BackupService $backup): int
    {
        $this->info($backup->purge()." ta nusxa o'chirildi");

        return self::SUCCESS;
    }
}

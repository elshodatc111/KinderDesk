<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramPoller;
use Illuminate\Console\Command;

class TelegramRun extends Command
{
    protected $signature = 'tg:run {--once : Bir marta aylanib chiqish} {--sleep=2 : Aylanishlar orasidagi pauza (soniya)} {--max-seconds=0 : Shu vaqtdan keyin to\'xtash (0 = to\'xtamaydi)}';

    protected $description = "Telegram worker: yangilanishlarni olish (polling rejimida) va yuborish navbatini bo'shatish";

    public function handle(TelegramPoller $poller): int
    {
        $start = time();
        do {
            $r = $poller->tick();
            if ($this->output->isVerbose() || $this->option('once')) {
                $this->line(sprintf('[%s] yangilanish: %d, yuborildi: %d', now()->format('H:i:s'), $r['updates'], $r['sent']));
            }
            if ($this->option('once')) {
                break;
            }
            if ((int) $this->option('max-seconds') > 0 && time() - $start >= (int) $this->option('max-seconds')) {
                break;
            }
            sleep(max(1, (int) $this->option('sleep')));
        } while (true);

        return self::SUCCESS;
    }
}

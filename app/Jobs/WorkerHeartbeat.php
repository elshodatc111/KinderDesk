<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Navbat ishchisi tirikligini bildiradi (har daqiqa scheduler yuboradi). */
class WorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put('kd:health:worker', now()->toIso8601String(), 3600);
    }
}

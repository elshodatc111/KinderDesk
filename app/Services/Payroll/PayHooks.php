<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Ish haqiga ta'sir qiluvchi boshqa bo'limlar (hisobot zanjiri, tadbirlar) bilan bog'lanish nuqtalari.
 * Tegishli xususiyat o'chiq bo'lsa, shart bajarilgan hisoblanadi.
 */
class PayHooks
{
    /** Kunlik ota-ona hisoboti oyning barcha ishchi kunlarida topshirilgan va qabul qilinganmi (KPI). */
    public static function parentReportMet(Employee $e, Carbon $period): bool
    {
        if (! tenant()->hasFeature('report_chain') || ! class_exists(\App\Services\Reports\ReportChain::class)) {
            return true;
        }

        return app(\App\Services\Reports\ReportChain::class)->parentReportsComplete($e, $period);
    }

    /** Oy davomidagi guruh tadbirlarida ishtirok etganmi (KPI). */
    public static function eventsMet(Employee $e, Carbon $period): bool
    {
        if (! tenant()->hasFeature('events') || ! class_exists(\App\Services\Events\EventService::class)) {
            return true;
        }

        return app(\App\Services\Events\EventService::class)->participated($e, $period);
    }

    /** Hisobot zanjiri buzilmagan oyda bonuslar qo'shiladi (direktor kechirgan bo'lsa ham). */
    public static function bonusesAllowed(Employee $e, Carbon $period): bool
    {
        if (! tenant()->hasFeature('report_chain') || ! class_exists(\App\Services\Reports\ReportChain::class)) {
            return true;
        }

        return app(\App\Services\Reports\ReportChain::class)->bonusesAllowed($e, $period);
    }
}

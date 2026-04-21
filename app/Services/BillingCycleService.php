<?php

namespace App\Services;

use App\Models\BillingCycle;
use Carbon\Carbon;

class BillingCycleService
{
    public function getOrCreateNextCycle(Carbon $now = null)
    {
        $now = $now ?: now('Asia/Jakarta');
        $target = $now->copy()->startOfMonth();

        if ((int) $now->day > 1) {
            $target = $target->addMonth();
        }

        return BillingCycle::query()->firstOrCreate(
            ['year' => (int) $target->year, 'month' => (int) $target->month],
            [
                'cycle_start_date' => $target->copy()->startOfMonth()->toDateString(),
                'cycle_end_date' => $target->copy()->endOfMonth()->toDateString(),
                'scheduled_run_at' => $target->copy()->day(1)->setTime(8, 0, 0, 0)->timezone('Asia/Jakarta'),
            ]
        );
    }
}

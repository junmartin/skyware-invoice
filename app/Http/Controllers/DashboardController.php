<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $nextCycle = BillingCycle::query()->orderByDesc('year')->orderByDesc('month')->first();

        // Void invoices are excluded from operational KPIs.
        $activeQuery = Invoice::query()->where('status', '!=', Invoice::STATUS_VOID);

        return view('dashboard.index', [
            'nextCycle'           => $nextCycle,
            'statusCounts'        => (clone $activeQuery)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'pendingStampingCount'=> (clone $activeQuery)->where('status', Invoice::STATUS_PENDING_STAMPING)->count(),
        ]);
    }
}

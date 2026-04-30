<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\BillingCycleResource;
use App\Models\BillingCycle;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BillingCycleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Preview invoices that would be generated for next cycle (dry run)
     * GET /api/billing-cycles/next/preview-generation
     */
    public function previewNextGeneration(Request $request)
    {
        $now = now('Asia/Jakarta');
        $target = $now->copy()->startOfMonth();

        if ((int) $now->day > 1) {
            $target = $target->addMonth();
        }

        $year = (int) $target->year;
        $month = (int) $target->month;

        $existingCycle = BillingCycle::query()->where('year', $year)->where('month', $month)->first();

        $baseAmount = 1000000.0;
        $taxRate = 0.11;
        $perInvoiceSubtotal = $baseAmount;
        $perInvoiceTax = $baseAmount * $taxRate;
        $perInvoiceTotal = $perInvoiceSubtotal + $perInvoiceTax;

        $query = Client::query()
            ->where('is_active', true)
            ->whereDoesntHave('invoices', function ($q) use ($existingCycle, $year, $month) {
                $q->where('invoice_type', Invoice::TYPE_MONTHLY)
                    ->when($existingCycle, function ($sub) use ($existingCycle) {
                        $sub->where('billing_cycle_id', $existingCycle->id);
                    }, function ($sub) use ($year, $month) {
                        $sub->whereHas('billingCycle', function ($cycleQuery) use ($year, $month) {
                            $cycleQuery->where('year', $year)->where('month', $month);
                        });
                    });
            });

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 500));

        $candidates = $query->orderBy('name')->paginate($perPage);
        $candidateTotalCount = (int) $candidates->total();

        $rows = collect($candidates->items())->map(function ($client) use ($perInvoiceSubtotal, $perInvoiceTax, $perInvoiceTotal, $year, $month) {
            return [
                'client_id' => $client->id,
                'client_code' => $client->code,
                'client_name' => $client->name,
                'currency' => $client->currency,
                'projected_invoice_number' => sprintf('INV-%s-%04d%02d', strtoupper($client->code), $year, $month),
                'projected_subtotal' => round($perInvoiceSubtotal, 2),
                'projected_tax_amount' => round($perInvoiceTax, 2),
                'projected_total_amount' => round($perInvoiceTotal, 2),
            ];
        })->values();

        return ApiResponse::success([
            'cycle' => [
                'year' => $year,
                'month' => $month,
                'label' => sprintf('%04d-%02d', $year, $month),
                'cycle_start_date' => $target->copy()->startOfMonth()->toDateString(),
                'cycle_end_date' => $target->copy()->endOfMonth()->toDateString(),
                'scheduled_run_at' => $target->copy()->day(1)->setTime(8, 0, 0, 0)->timezone('Asia/Jakarta')->toIso8601String(),
                'exists_in_db' => (bool) $existingCycle,
                'billing_cycle_id' => $existingCycle ? $existingCycle->id : null,
            ],
            'assumptions' => [
                'base_amount_per_invoice' => round($perInvoiceSubtotal, 2),
                'tax_rate' => $taxRate,
                'tax_amount_per_invoice' => round($perInvoiceTax, 2),
                'total_per_invoice' => round($perInvoiceTotal, 2),
            ],
            'summary' => [
                'would_generate_count' => $candidateTotalCount,
                'projected_total_amount' => round($candidateTotalCount * $perInvoiceTotal, 2),
            ],
            'candidates' => $rows,
            'pagination' => [
                'current_page' => $candidates->currentPage(),
                'per_page' => $candidates->perPage(),
                'total' => $candidates->total(),
                'last_page' => $candidates->lastPage(),
                'from' => $candidates->firstItem(),
                'to' => $candidates->lastItem(),
                'has_more' => $candidates->hasMorePages(),
            ],
        ], 'Next cycle generation preview retrieved');
    }

    /**
     * List all billing cycles
     * GET /api/billing-cycles
     */
    public function index(Request $request)
    {
        $query = BillingCycle::query();

        // Filter by year if provided
        if ($year = $request->query('year')) {
            $query->where('year', $year);
        }

        // Filter by generated status if provided
        if ($request->has('is_generated')) {
            $query->where('is_generated', $request->boolean('is_generated'));
        }

        $perPage = $request->query('per_page', 20);
        $cycles = $query->orderByDesc('year')->orderByDesc('month')->paginate($perPage);

        return ApiResponse::paginated($cycles, 'Billing cycles retrieved');
    }

    /**
     * Get single billing cycle details
     * GET /api/billing-cycles/{id}
     */
    public function show(BillingCycle $cycle)
    {
        $cycle->loadCount('invoices');
        $cycle->total_amount = $cycle->invoices()->sum('total_amount');
        $cycle->paid_amount = $cycle->invoices()->where('status', 'paid')->sum('total_amount');

        return ApiResponse::success(new BillingCycleResource($cycle), 'Billing cycle retrieved');
    }

    /**
     * Generate next billing cycle
     * POST /api/billing-cycles/generate-next
     */
    public function generateNext(InvoiceGenerationService $service)
    {
        try {
            list($cycle, $count) = $service->generateNextCycle(auth()->id());

            return ApiResponse::success([
                'cycle' => new BillingCycleResource($cycle),
                'invoices_created' => $count,
            ], 'Billing cycle generated successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate billing cycle: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get invoices for a specific billing cycle
     * GET /api/billing-cycles/{id}/invoices
     */
    public function invoices(Request $request, BillingCycle $cycle)
    {
        $query = $cycle->invoices();

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by client if provided
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }

        $perPage = $request->query('per_page', 20);
        $invoices = $query->latest('issue_date')->paginate($perPage);

        return ApiResponse::paginated($invoices, 'Cycle invoices retrieved');
    }
}

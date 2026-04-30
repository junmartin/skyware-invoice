<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\BillingCycleResource;
use App\Models\BillingCycle;
use App\Models\Invoice;
use App\Services\InvoiceGenerationService;
use Illuminate\Http\Request;

class BillingCycleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
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

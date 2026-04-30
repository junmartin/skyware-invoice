<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all email logs
     * GET /api/email-logs
     */
    public function index(Request $request)
    {
        $query = EmailLog::query()->with('invoice');

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by date range
        if ($from = $request->query('from')) {
            $query->whereDate('attempted_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('attempted_at', '<=', $to);
        }

        // Filter by recipient
        if ($recipient = $request->query('recipient')) {
            $query->where('recipient', 'like', '%' . $recipient . '%');
        }

        $perPage = $request->query('per_page', 20);
        $logs = $query->latest('attempted_at')->paginate($perPage);

        return ApiResponse::paginated($logs, 'Email logs retrieved');
    }

    /**
     * Get email logs for a specific invoice
     * GET /api/invoices/{id}/email-logs
     */
    public function byInvoice(Request $request, $invoiceId)
    {
        $query = EmailLog::query()->where('invoice_id', $invoiceId);

        // Filter by status if provided
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->query('per_page', 20);
        $logs = $query->latest('attempted_at')->paginate($perPage);

        return ApiResponse::paginated($logs, 'Invoice email logs retrieved');
    }

    /**
     * Get single email log
     * GET /api/email-logs/{id}
     */
    public function show(EmailLog $log)
    {
        $log->load('invoice');

        return ApiResponse::success(new EmailLogResource($log), 'Email log retrieved');
    }
}

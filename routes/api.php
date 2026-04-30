<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public webhook endpoint (no authentication required)
Route::post('/webhooks/xendit/invoice', [WebhookController::class, 'xenditInvoicePaid'])->name('webhooks.xendit.invoice');

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.auth.logout');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum')->name('api.auth.user');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum')->name('api.auth.refresh');
});

// Protected API routes (require Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Client management
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/invoices', [ClientController::class, 'invoices'])->name('api.clients.invoices');

    // Invoice management (Phase 2)
    Route::get('invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index'])->name('api.invoices.index');
    Route::post('invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'store'])->name('api.invoices.store');
    Route::get('invoices/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'show'])->name('api.invoices.show');
    Route::post('invoices/{invoice}/confirm-draft', [\App\Http\Controllers\Api\InvoiceController::class, 'confirmDraft'])->name('api.invoices.confirm-draft');
    Route::post('invoices/{invoice}/send-email', [\App\Http\Controllers\Api\InvoiceController::class, 'sendEmail'])->name('api.invoices.send-email');
    Route::post('invoices/{invoice}/void', [\App\Http\Controllers\Api\InvoiceController::class, 'void'])->name('api.invoices.void');
    Route::post('invoices/{invoice}/mark-sent', [\App\Http\Controllers\Api\InvoiceController::class, 'markSent'])->name('api.invoices.mark-sent');
    Route::post('invoices/{invoice}/mark-as-paid', [\App\Http\Controllers\Api\InvoiceController::class, 'markAsPaid'])->name('api.invoices.mark-as-paid');
    Route::get('invoices/{invoice}/status-history', [\App\Http\Controllers\Api\InvoiceController::class, 'statusHistory'])->name('api.invoices.status-history');
    Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Api\InvoiceController::class, 'downloadPdf'])->name('api.invoices.pdf');
    Route::get('invoices/{invoice}/stamped-pdf', [\App\Http\Controllers\Api\InvoiceController::class, 'downloadStampedPdf'])->name('api.invoices.stamped-pdf');

    // Email logs (Phase 3)
    Route::get('email-logs', [\App\Http\Controllers\Api\EmailLogController::class, 'index'])->name('api.email-logs.index');
    Route::get('email-logs/{log}', [\App\Http\Controllers\Api\EmailLogController::class, 'show'])->name('api.email-logs.show');
    Route::get('invoices/{invoiceId}/email-logs', [\App\Http\Controllers\Api\EmailLogController::class, 'byInvoice'])->name('api.invoices.email-logs');

    // Payment records (Phase 3)
    Route::get('payments', [\App\Http\Controllers\Api\PaymentRecordController::class, 'index'])->name('api.payments.index');
    Route::get('payments/{payment}', [\App\Http\Controllers\Api\PaymentRecordController::class, 'show'])->name('api.payments.show');
    Route::post('payments/{payment}/sync-xendit', [\App\Http\Controllers\Api\PaymentRecordController::class, 'syncXendit'])->name('api.payments.sync-xendit');
    Route::get('invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\PaymentRecordController::class, 'byInvoice'])->name('api.invoices.payments');

    // Billing cycles (Phase 3)
    Route::get('billing-cycles', [\App\Http\Controllers\Api\BillingCycleController::class, 'index'])->name('api.billing-cycles.index');
    Route::get('billing-cycles/{cycle}', [\App\Http\Controllers\Api\BillingCycleController::class, 'show'])->name('api.billing-cycles.show');
    Route::get('billing-cycles/next/preview-generation', [\App\Http\Controllers\Api\BillingCycleController::class, 'previewNextGeneration'])->name('api.billing-cycles.preview-next-generation');
    Route::post('billing-cycles/generate-next', [\App\Http\Controllers\Api\BillingCycleController::class, 'generateNext'])->name('api.billing-cycles.generate-next');
    Route::get('billing-cycles/{cycle}/invoices', [\App\Http\Controllers\Api\BillingCycleController::class, 'invoices'])->name('api.billing-cycles.invoices');

    // Settings (Phase 3)
    Route::get('settings', [\App\Http\Controllers\Api\SettingsController::class, 'index'])->name('api.settings.index');
    Route::put('settings', [\App\Http\Controllers\Api\SettingsController::class, 'update'])->name('api.settings.update');
    Route::get('settings/{key}', [\App\Http\Controllers\Api\SettingsController::class, 'show'])->name('api.settings.show');

    // Bulk operations (Phase 3)
    Route::post('bulk/send-all-ready', [\App\Http\Controllers\Api\BulkOperationController::class, 'sendAllReady'])->name('api.bulk.send-all-ready');
    Route::post('bulk/sync-payments', [\App\Http\Controllers\Api\BulkOperationController::class, 'syncAllPayments'])->name('api.bulk.sync-payments');
    Route::post('bulk/void-invoices', [\App\Http\Controllers\Api\BulkOperationController::class, 'bulkVoid'])->name('api.bulk.void-invoices');
});


<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StampingQueueController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', '/dashboard');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('clients', ClientController::class)->except(['show', 'destroy']);

    Route::get('/invoices/active-next', [InvoiceController::class, 'activeNext'])->name('invoices.active-next');
    Route::post('/invoices/generate-next-cycle', [InvoiceController::class, 'generateNextCycle'])->name('invoices.generate-next-cycle');
    Route::post('/invoices/send-all-ready', [InvoiceController::class, 'sendAllReady'])->name('invoices.send-all-ready');
    Route::get('/invoices/adhoc/create', [InvoiceController::class, 'createAdhoc'])->name('invoices.adhoc.create');
    Route::post('/invoices/adhoc', [InvoiceController::class, 'storeAdhoc'])->name('invoices.adhoc.store');
    Route::post('/invoices/{invoice}/confirm-draft', [InvoiceController::class, 'confirmAdhocDraft'])->name('invoices.confirm-draft');
    Route::post('/invoices/{invoice}/mark-sent', [InvoiceController::class, 'markAsSent'])->name('invoices.mark-sent');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');
    Route::post('/invoices/{invoice}/update-status', [InvoiceController::class, 'updateStatus'])->name('invoices.update-status');
    Route::post('/invoices/bulk-update-status', [InvoiceController::class, 'bulkUpdateStatus'])->name('invoices.bulk-update-status');
    Route::post('/invoices/{invoice}/recipient', [InvoiceController::class, 'updateRecipient'])->name('invoices.update-recipient');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.download-pdf');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    Route::get('/stamping-queue', [StampingQueueController::class, 'index'])->name('stamping.index');
    Route::post('/stamping-queue/{invoice}/upload', [StampingQueueController::class, 'upload'])->name('stamping.upload');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/send-all-ready', [SettingController::class, 'sendAllReadyNow'])->name('settings.send-all-ready');
});

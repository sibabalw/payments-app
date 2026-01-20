<?php

use App\Http\Controllers\AiDataController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// WhatsApp Webhook Routes (no auth - webhook callbacks from Meta)
Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::get('/webhook', [WhatsAppWebhookController::class, 'verify'])->name('webhook.verify');
    Route::post('/webhook', [WhatsAppWebhookController::class, 'handle'])->name('webhook.handle');
});

// AI Data API Routes (authenticated by AI server API key)
Route::prefix('ai')->middleware(\App\Http\Middleware\AiServerAuth::class)->group(function () {
    Route::get('/business/{business}/summary', [AiDataController::class, 'businessSummary']);
    Route::get('/business/{business}/employees/summary', [AiDataController::class, 'employeesSummary']);
    Route::get('/business/{business}/payments/summary', [AiDataController::class, 'paymentsSummary']);
    Route::get('/business/{business}/payroll/summary', [AiDataController::class, 'payrollSummary']);
    Route::get('/business/{business}/escrow/balance', [AiDataController::class, 'escrowBalance']);
    Route::get('/business/{business}/compliance/status', [AiDataController::class, 'complianceStatus']);
    Route::get('/business/{business}/context', [AiDataController::class, 'fullContext']);
});

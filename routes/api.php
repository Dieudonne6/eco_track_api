<?php

use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\WorkerSimulatorController;
use Illuminate\Support\Facades\Route;

// --- AUTH PUBLIQUE ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- ACCÈS PUBLIC AU PRODUIT (CLIENT FINAL) ---
Route::get('/products/{uuid}/history', [TrackingController::class, 'history']);
Route::get('/products/{uuid}/qrcode', [ProductController::class, 'generateQrCode'])->name('products.qr');
Route::get('/worker/steps', [WorkerSimulatorController::class, 'getAvailableSteps']);
Route::get('/companies', [CompanyController::class, 'index']);

Route::post('/products', [ProductController::class, 'store'])
    ->middleware(['auth:sanctum', 'is_admin']);

// --- ROUTES PROTÉGÉES (ACTEURS LOGISTIQUES) ---
Route::middleware('auth:sanctum')->group(function () {

    
    Route::get('/allproducts', [ProductController::class, 'index']);
    // Route::get('/products/{uuid}', [ProductController::class, 'show']);

    // Scan (Ouvriers / IoT)
    Route::post('/products/{uuid}/scan', [TrackingController::class, 'scan']);

    Route::post('/worker/submit-scan', [WorkerSimulatorController::class, 'submitManualScan']);
});
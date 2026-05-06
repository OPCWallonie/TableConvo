<?php

use App\Http\Controllers\Member\CartesController;
use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\InvoiceController;
use App\Http\Controllers\Member\ProfileController as MemberProfileController;
use App\Http\Controllers\Payment\CheckoutController;
use App\Http\Controllers\Payment\PaymentReturnController;
use App\Http\Controllers\Payment\StubPaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\PricingController;
use App\Http\Controllers\Public\ShopController;
use Illuminate\Support\Facades\Route;

// --- Pages publiques ---
Route::get('/', fn () => view('welcome'));
Route::get('/tarifs', [PricingController::class, 'index'])->name('tarifs');
Route::get('/cgv', [LegalController::class, 'cgv'])->name('cgv');
Route::get('/confidentialite', [LegalController::class, 'confidentialite'])->name('confidentialite');

// --- Catalogue produit ---
Route::get('/achat', fn () => redirect()->route('tarifs'))->name('achat.index');
Route::get('/achat/{cardType}', [ShopController::class, 'show'])->name('achat.show');

// --- Panier & checkout (auth requis) ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/panier', fn () => view('public.panier'))->name('panier');
    Route::post('/panier/checkout', [CheckoutController::class, 'store'])->name('panier.checkout');
});

// --- Retour paiement (auth requis) ---
Route::middleware(['auth'])->group(function () {
    Route::get('/paiement/retour/{order}', [PaymentReturnController::class, 'show'])->name('paiement.retour');
    Route::get('/paiement/stub/{order}', [StubPaymentController::class, 'show'])->name('paiement.stub');
    Route::post('/paiement/stub/{order}/confirm', [StubPaymentController::class, 'confirm'])->name('paiement.stub.confirm');
    Route::post('/paiement/stub/{order}/fail', [StubPaymentController::class, 'fail'])->name('paiement.stub.fail');
});

// --- Webhook Mollie (CSRF exclus via bootstrap/app.php) ---
Route::post('/webhooks/mollie', [PaymentWebhookController::class, 'mollie'])->name('webhooks.mollie');

// --- Espace membre ---
Route::prefix('espace')->name('espace.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/cartes', [CartesController::class, 'index'])->name('cartes');
    Route::get('/factures', [InvoiceController::class, 'index'])->name('factures');
    Route::get('/factures/{invoice}/pdf', [InvoiceController::class, 'download'])->name('factures.pdf');
    Route::get('/profil', [MemberProfileController::class, 'show'])->name('profil');
    Route::patch('/profil', [MemberProfileController::class, 'update'])->name('profil.update');
    Route::get('/donnees', [MemberProfileController::class, 'exportData'])->name('donnees');
    Route::delete('/compte', [MemberProfileController::class, 'destroy'])->name('compte.destroy');
});

// Compat dashboard
Route::get('/dashboard', fn () => redirect()->route('espace.dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/auth.php';

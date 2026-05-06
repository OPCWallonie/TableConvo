<?php

use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\ProfileController as MemberProfileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Espace membre
Route::prefix('espace')->name('espace.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/profil', [MemberProfileController::class, 'show'])->name('profil');
    Route::patch('/profil', [MemberProfileController::class, 'update'])->name('profil.update');
    Route::get('/donnees', [MemberProfileController::class, 'exportData'])->name('donnees');
    Route::delete('/compte', [MemberProfileController::class, 'destroy'])->name('compte.destroy');
});

// Redirect /dashboard to espace for backwards compat
Route::get('/dashboard', fn () => redirect()->route('espace.dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Breeze profile routes (kept for email verification flow)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

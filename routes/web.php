<?php

use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\ProfileController as MemberProfileController;
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

require __DIR__.'/auth.php';

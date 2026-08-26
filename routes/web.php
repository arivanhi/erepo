<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/assign-account', [\App\Http\Controllers\AccountController::class, 'index'])->name('assign-account');
    Route::get('/assign-account/template', [\App\Http\Controllers\AccountController::class, 'downloadTemplate'])->name('assign-account.template');
    Route::post('/assign-account/add', [\App\Http\Controllers\AccountController::class, 'store'])->name('assign-account.add');
    Route::post('/assign-account/import', [\App\Http\Controllers\AccountController::class, 'import'])->name('assign-account.import');
    Route::put('/assign-account/{id}', [\App\Http\Controllers\AccountController::class, 'update'])->name('assign-account.update');
    Route::delete('/assign-account/{id}', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('assign-account.destroy');

    Route::get('/repository/capstone', [\App\Http\Controllers\RepositoryController::class, 'capstone'])->name('repository.capstone');
    Route::post('/repository/capstone', [\App\Http\Controllers\RepositoryController::class, 'storeCapstone'])->name('repository.capstone.store');
    Route::post('/repository/capstone/{id}/approve', [\App\Http\Controllers\RepositoryController::class, 'approveCapstone'])->name('repository.capstone.approve');
    Route::delete('/repository/capstone/{id}/delete', [\App\Http\Controllers\RepositoryController::class, 'deleteCapstone'])->name('repository.capstone.delete');
    Route::get('/repository/capstone/{id}/download', [\App\Http\Controllers\RepositoryController::class, 'downloadCapstone'])->name('repository.capstone.download');

    Route::get('/repository/script', [\App\Http\Controllers\RepositoryController::class, 'script'])->name('repository.script');
    Route::post('/repository/script', [\App\Http\Controllers\RepositoryController::class, 'storeScript'])->name('repository.script.store');
    Route::post('/repository/script/{id}/approve', [\App\Http\Controllers\RepositoryController::class, 'approveScript'])->name('repository.script.approve');
    Route::delete('/repository/script/{id}/delete', [\App\Http\Controllers\RepositoryController::class, 'deleteScript'])->name('repository.script.delete');
    Route::get('/repository/script/{id}/download', [\App\Http\Controllers\RepositoryController::class, 'downloadScript'])->name('repository.script.download');
    
    Route::get('/settings', function () {
        return Inertia::render('Settings');
    })->name('settings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

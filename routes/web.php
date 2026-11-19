<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/repository/capstone/{id}/request-reupload', [\App\Http\Controllers\RepositoryController::class, 'requestReuploadCapstone'])->name('repository.capstone.request-reupload');
    Route::post('/repository/capstone/{id}/recheck', [\App\Http\Controllers\RepositoryController::class, 'recheckCapstone'])->name('repository.capstone.recheck');
    Route::post('/repository/capstone/{id}/update', [\App\Http\Controllers\RepositoryController::class, 'updateCapstone'])->name('repository.capstone.update');
    Route::delete('/repository/capstone/{id}/delete', [\App\Http\Controllers\RepositoryController::class, 'deleteCapstone'])->name('repository.capstone.delete');
    Route::get('/repository/capstone/{id}/download', [\App\Http\Controllers\RepositoryController::class, 'downloadCapstone'])->name('repository.capstone.download');

    Route::get('/repository/script', [\App\Http\Controllers\RepositoryController::class, 'script'])->name('repository.script');
    Route::post('/repository/script', [\App\Http\Controllers\RepositoryController::class, 'storeScript'])->name('repository.script.store');
    Route::post('/repository/script/{id}/approve', [\App\Http\Controllers\RepositoryController::class, 'approveScript'])->name('repository.script.approve');
    Route::post('/repository/script/{id}/request-reupload', [\App\Http\Controllers\RepositoryController::class, 'requestReuploadScript'])->name('repository.script.request-reupload');
    Route::post('/repository/script/{id}/update', [\App\Http\Controllers\RepositoryController::class, 'updateScript'])->name('repository.script.update');
    Route::delete('/repository/script/{id}/delete', [\App\Http\Controllers\RepositoryController::class, 'deleteScript'])->name('repository.script.delete');
    Route::get('/repository/script/{id}/download', [\App\Http\Controllers\RepositoryController::class, 'downloadScript'])->name('repository.script.download');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::post('/settings/smtp', [SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    Route::post('/settings/smtp/test', [SettingsController::class, 'testSmtp'])->name('settings.smtp.test');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

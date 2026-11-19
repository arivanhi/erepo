<?php

use App\Http\Controllers\CapstoneTeamController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicRepositoryController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Public Portal (Tanpa Login) — v1.2
Route::get('/', [PublicRepositoryController::class, 'dashboard'])->name('public.dashboard');
Route::get('/explore/capstone', [PublicRepositoryController::class, 'capstone'])->name('public.capstone');
Route::get('/explore/capstone/{id}/abstract', [PublicRepositoryController::class, 'abstractMeta'])->name('public.capstone.abstract');
Route::get('/explore/capstone/{id}/abstract/{page}', [PublicRepositoryController::class, 'abstractPage'])->whereNumber('page')->name('public.capstone.abstract.page');
Route::post('/explore/capstone/{id}/request-token', [PublicRepositoryController::class, 'requestPdfToken'])->middleware('throttle:10,1')->name('public.capstone.request-token');

Route::get('/explore/script', [PublicRepositoryController::class, 'script'])->name('public.script');
Route::post('/explore/script/{id}/request-access', [PublicRepositoryController::class, 'requestScriptAccess'])->middleware('throttle:10,1')->name('public.script.request-access');

Route::post('/explore/download/verify', [PublicRepositoryController::class, 'verifyAndDownload'])->middleware('throttle:15,1')->name('public.download.verify');
Route::get('/explore/download/{urlToken}', [PublicRepositoryController::class, 'downloadByUrlToken'])->name('public.download.direct');

// Internal Portal (Wajib Login)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/assign-account', [\App\Http\Controllers\AccountController::class, 'index'])->name('assign-account');
    Route::get('/assign-account/template', [\App\Http\Controllers\AccountController::class, 'downloadTemplate'])->name('assign-account.template');
    Route::post('/assign-account/add', [\App\Http\Controllers\AccountController::class, 'store'])->name('assign-account.add');
    Route::post('/assign-account/import', [\App\Http\Controllers\AccountController::class, 'import'])->name('assign-account.import');
    Route::put('/assign-account/{id}', [\App\Http\Controllers\AccountController::class, 'update'])->name('assign-account.update');
    Route::delete('/assign-account/{id}', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('assign-account.destroy');

    // Add Capstone Team (Koordinator TA & Admin)
    Route::get('/capstone-teams', [CapstoneTeamController::class, 'index'])->name('capstone-teams');
    Route::post('/capstone-teams', [CapstoneTeamController::class, 'store'])->name('capstone-teams.store');
    Route::put('/capstone-teams/{id}', [CapstoneTeamController::class, 'update'])->name('capstone-teams.update');
    Route::post('/capstone-teams/{id}/send-token', [CapstoneTeamController::class, 'sendToken'])->name('capstone-teams.send-token');
    Route::delete('/capstone-teams/{id}', [CapstoneTeamController::class, 'destroy'])->name('capstone-teams.destroy');

    // Verifikasi Permintaan Download Script (Dosen Pembimbing, Koordinator TA, Admin)
    Route::get('/download-requests', [\App\Http\Controllers\DownloadRequestVerificationController::class, 'index'])->name('download-requests.index');
    Route::post('/download-requests/{id}/decide', [\App\Http\Controllers\DownloadRequestVerificationController::class, 'decide'])->name('download-requests.decide');

    Route::get('/repository/capstone', [\App\Http\Controllers\RepositoryController::class, 'capstone'])->name('repository.capstone');
    Route::post('/repository/capstone', [\App\Http\Controllers\RepositoryController::class, 'storeCapstone'])->name('repository.capstone.store');
    Route::get('/repository/capstone/{id}/view', [\App\Http\Controllers\RepositoryController::class, 'viewCapstone'])->name('repository.capstone.view');
    Route::post('/repository/capstone/{id}/approve', [\App\Http\Controllers\RepositoryController::class, 'approveCapstone'])->name('repository.capstone.approve');
    Route::post('/repository/capstone/{id}/accept-with-note', [\App\Http\Controllers\RepositoryController::class, 'acceptIncompleteCapstone'])->name('repository.capstone.accept-with-note');
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

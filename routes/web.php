<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentReportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InventoryImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::get('/', function () {
    // Cek apakah user sudah login atau belum
    if (Auth::check()) {
        return redirect('/dashboard');
    }

    // Jika belum login, arahkan ke halaman login
    return redirect('/login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Route Master Agent
    Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
    Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
    Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
    Route::post('/agents/{agent}/assign-supervisor', [AgentController::class, 'updateSupervisors'])
        ->name('agents.assign-supervisor');

    // Route Upload Laporan Agent
    Route::get('/reports', [AgentReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [AgentReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{report}/download', [AgentReportController::class, 'download'])->name('reports.download');
    Route::put('/reports/{report}', [AgentReportController::class, 'update'])->name('reports.update');
    Route::delete('/reports/{report}', [AgentReportController::class, 'destroy'])->name('reports.destroy');

    // Route Management Role
    Route::resource('roles', RoleController::class);

    // Route Regional
    Route::resource('regional', RegionalController::class);

    // Route Management Role
    Route::resource('users', UserController::class);
    // routes/web.php
    Route::post('/users/{user}/assign-supervisor', [UserController::class, 'assignSupervisor'])
        ->name('users.assign-supervisor');

    // web.php
    // Route::controller(InventoryImportController::class)->group(function () {
    //     Route::get('/inventory/import', 'index')->name('import.index');
    //     Route::post('/inventory/import/upload', 'upload')->name('import.upload');
    //     Route::post('/inventory/import/process', 'process')->name('import.process');
    // });

    Route::get('/import/mapping', [ImportController::class, 'mapping'])->name('import.mapping');
    Route::post('/import/process', [ImportController::class, 'process'])->name('import.process');
    Route::get('/import/preview', [ImportController::class, 'preview'])->name('import.preview');

    Route::post('/import/save-mapping', [ImportController::class, 'saveMapping'])
    ->name('import.saveMapping');
    Route::post('/mapping/reset', [ImportController::class, 'resetMapping'])
    ->name('mapping.reset');

});

require __DIR__ . '/auth.php';

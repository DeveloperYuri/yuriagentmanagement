<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ProfileController;
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
    // 1. Halaman List Agen (Tampilan Tabel)
    Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');

    // 2. Proses Simpan Agen Baru (Action Form)
    Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');

    // 3. Proses Update (Opsional buat nanti)
    Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');

    // 4. Proses Hapus (Opsional buat nanti)
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
});

require __DIR__ . '/auth.php';

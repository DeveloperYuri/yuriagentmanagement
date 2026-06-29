<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentItemMappingController;
use App\Http\Controllers\AgentReportController;
use App\Http\Controllers\CustomeritemminstocksController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InventoryImportController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemGroupController;
use App\Http\Controllers\MappingProdukController;
use App\Http\Controllers\MappingreportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PythonController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UploaddataagentControlller;
use App\Http\Controllers\UploaddatacmoControlller;
use App\Http\Controllers\UploadtemplateyuriControlller;
use App\Http\Controllers\UserController;
use App\Models\Customeritemminstocks;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    // Route Mapping Upload Laporan Agent
    Route::get('/mappingagentreports', [MappingreportController::class, 'index'])->name('mappingagentreports.index');
    Route::post('/mappingagentreports/store', [MappingreportController::class, 'store'])->name('mappingagentreports.store');
    Route::get('/mappingagentreports/{report}/download', [MappingreportController::class, 'download'])->name('mappingagentreports.download');
    Route::put('/mappingagentreports/{report}', [MappingreportController::class, 'update'])->name('mappingagentreports.update');
    Route::delete('/mappingagentreports/{report}', [MappingreportController::class, 'destroy'])->name('mappingagentreports.destroy');

    // Upload Data Agent
    Route::get('/dataagent', [UploaddataagentControlller::class, 'index'])->name('dataagent.index');
    Route::post('/dataagent/store', [UploaddataagentControlller::class, 'store'])->name('dataagent.store');
    Route::get('/dataagent/{report}/download', [UploaddataagentControlller::class, 'download'])->name('dataagent.download');
    Route::put('/dataagent/{report}', [UploaddataagentControlller::class, 'update'])->name('dataagent.update');
    Route::delete('/dataagent/{report}', [UploaddataagentControlller::class, 'destroy'])->name('dataagent.destroy');

    // Upload Data Template Yuri
    Route::get('/templateyuri', [UploadtemplateyuriControlller::class, 'index'])->name('templateyuri.index');
    Route::post('/templateyuri/store', [UploadtemplateyuriControlller::class, 'store'])->name('templateyuri.store');
    Route::get('/templateyuri/{report}/download', [UploadtemplateyuriControlller::class, 'download'])->name('templateyuri.download');
    Route::put('/templateyuri/{report}', [UploadtemplateyuriControlller::class, 'update'])->name('templateyuri.update');
    Route::delete('/templateyuri/{report}', [UploadtemplateyuriControlller::class, 'destroy'])->name('templateyuri.destroy');

    // Upload Data CMO
    Route::get('/datacmo', [UploaddatacmoControlller::class, 'index'])->name('datacmo.index');
    Route::post('/datacmo/store', [UploaddatacmoControlller::class, 'store'])->name('datacmo.store');
    Route::get('/datacmo/{report}/download', [UploaddatacmoControlller::class, 'download'])->name('datacmo.download');
    Route::put('/datacmo/{report}', [UploaddatacmoControlller::class, 'update'])->name('datacmo.update');
    Route::delete('/datacmo/{report}', [UploaddatacmoControlller::class, 'destroy'])->name('datacmo.destroy');

    // Route Management Role
    Route::resource('roles', RoleController::class);

    // Route Regional
    Route::resource('regional', RegionalController::class);

    // Route Management Role
    Route::resource('users', UserController::class);
    // routes/web.php
    Route::post('/users/{user}/assign-supervisor', [UserController::class, 'assignSupervisor'])
        ->name('users.assign-supervisor');

    Route::get('/import/mapping', [ImportController::class, 'mapping'])->name('import.mapping');
    Route::post('/import/process', [ImportController::class, 'process'])->name('import.process');
    Route::get('/import/preview', [ImportController::class, 'preview'])->name('import.preview');

    // Route::post('/import/save-mapping', [ImportController::class, 'saveMapping'])
    //     ->name('import.saveMapping');
    // Route::post('/mapping/reset', [ImportController::class, 'resetMapping'])
    //     ->name('mapping.reset');

    Route::get('/scan-excel', [ImportController::class, 'scanRawExcel'])->name('import.scanRawExcel');

    Route::post('/python/scan', [PythonController::class, 'scan']);

    Route::get('/yuri-engine', function () {
        return Inertia::render('Python/Index');
    });

    Route::post('/python/save-mapping', [PythonController::class, 'saveMapping']);
    Route::get('/python/get-mappings', [PythonController::class, 'getMappings']);
    Route::get('/mapping', [PythonController::class, 'mapping'])
        ->name('python.mapping');

    Route::post('/mapping/save', [PythonController::class, 'saveMapping']);

    Route::post('/python/exportexcel', [PythonController::class, 'exportexcel'])
        ->name('python.exportexcel');

    // Route::delete('/resetmapping/{id}', [PythonController::class, 'destroy']);

    Route::delete('/resetmapping/{id}', [PythonController::class, 'destroy']);
    Route::get('/mapping-multi', [PythonController::class, 'mappingMulti'])
        ->name('import.mapping.multi');
    Route::post('/python/compare-master', [PythonController::class, 'compareMaster']);
    Route::post('/python/process-multi', [PythonController::class, 'processMulti']);

    // Route::get('/python/mapping-export', [PythonController::class, 'mappingExport'])->name('exportmappingmulti');

    Route::get('/python/export-mapping', [PythonController::class, 'exportMappingPage'])
        ->name('exportmappingmulti');

    Route::post('/python/mapping-exportscan', [PythonController::class, 'mappingExportscanExcel'])
        ->name('python.mappingExportscan');

    Route::post('/python/mapping-export', [PythonController::class, 'mappingExport'])
        ->name('python.mapping-export');

    Route::post('/export/process', [ExportController::class, 'process'])->name('exportprocess');
    Route::get('/export-mapping', [ExportController::class, 'exportMappingPage'])->name('exportMappingPage');;
    Route::post('/python/scan-file', [ExportController::class, 'scanFile']);
    Route::post('/python/scan-header', [ExportController::class, 'scanHeader']); // BARIS INI YANG KURANG
    Route::post('/mapping/save', [ExportController::class, 'store']);

    Route::resource('items', ItemController::class);
    Route::resource('itemsgroups', ItemGroupController::class);
    Route::post('/items/import', [ItemController::class, 'import'])->name('items.import');

    Route::get('/mapping-produk', [MappingProdukController::class, 'index'])->name('mappingproduk.index');
    Route::post('/mapping-produk/save', [MappingProdukController::class, 'store'])->name('mapping-produk.store');
    Route::put('/mapping-produk/{id}', [MappingProdukController::class, 'update'])
        ->name('mapping-produk.update');
    Route::delete('/mapping-produk/{id}', [MappingProdukController::class, 'destroy'])
        ->name('mapping-produk.destroy');

    Route::post('/export/process-cmo', [ExportController::class, 'processCMO']);

    Route::resource('customer-item-min-stocks', CustomeritemminstocksController::class);

    Route::resource('agent-item-mappings', AgentItemMappingController::class);

    Route::post(
        '/agent-item-mappings/import',
        [AgentItemMappingController::class, 'import']
    )->name('agent-item-mappings.import');

    Route::post('/normalize', [
        ExportController::class,
        'normalize'
    ])->name('normalize.export');
});

// Route::post('/python/scan-file', function () {
//     return response()->json([
//         'file_path' => 'MASUK ROUTE',
//         'sheets' => ['Sheet1', 'Sheet2']
//     ]);
// });


Route::post('/python/process', [PythonController::class, 'process']);


require __DIR__ . '/auth.php';

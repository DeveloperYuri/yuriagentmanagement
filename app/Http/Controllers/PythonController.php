<?php

namespace App\Http\Controllers;

use App\Models\AgentReport;
use App\Models\Mapping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;


class PythonController extends Controller
{

    public function scan(Request $request)
    {
        // 🔥 kalau dari upload (FormData)
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('temp');
            $absolutePath = storage_path('app/' . $path);
        }
        // 🔥 kalau dari Mapping.vue (file_path)
        else if ($request->file_path) {
            $absolutePath = storage_path('app/public/' . $request->file_path);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan'
            ], 422);
        }

        $scriptPath = "/var/www/scripts/yuri_engine.py";

        $process = \Illuminate\Support\Facades\Process::run([
            'python3',
            $scriptPath,
            'scan',
            $absolutePath
        ]);

        if ($process->successful()) {
            return response()->json(json_decode($process->output(), true));
        }

        return response()->json([
            'status' => 'error',
            'message' => $process->errorOutput()
        ], 500);
    }

    public function mapping(Request $request)
    {
        $filePath = $request->query('file_path');

        if (!$filePath) {
            abort(400, 'file_path wajib');
        }

        // cari report berdasarkan file_path
        $report = AgentReport::where('file_path', $filePath)->firstOrFail();

        // ambil agent langsung dari report
        $agentReport = $report;

        if (!$agentReport) {
            abort(404, 'Report tidak ditemukan');
        }

        // dd($report, $agentReport);


        return inertia('Python/Mapping', [
            'filePath' => $filePath,
            'agent_id' => $agentReport->user_id,
            'report_id' => $report->id,
        ]);
    }

    public function process(Request $request)
    {
        $inputPath = storage_path('app/public/' . $request->file_path);
        $outputFileName = 'yuri_pro_' . \Illuminate\Support\Str::random(5) . '.xlsx';
        $tempFolder = storage_path('app/public/temp');
        $outputPath = $tempFolder . '/' . $outputFileName;
        $scriptPath = "/var/www/scripts/yuri_engine.py";

        if (!file_exists($tempFolder)) {
            mkdir($tempFolder, 0775, true);
        }

        // Susun command - tambahkan '2>&1' di ujung untuk menggabung error ke output biasa
        $command = sprintf(
            "python3 %s export %s %s %s %s 2>&1",
            escapeshellarg($scriptPath),
            escapeshellarg($inputPath),
            escapeshellarg(json_encode($request->mapping)),
            escapeshellarg($request->sheet),
            escapeshellarg($outputPath)
        );

        $process = \Illuminate\Support\Facades\Process::timeout(120)->run($command);

        if ($process->successful() && file_exists($outputPath)) {
            return response()->download($outputPath)->deleteFileAfterSend(true);
        }

        // Jika gagal, kita bongkar semua outputnya
        return response()->json([
            'status' => 'error',
            'message' => 'Python failed to generate file',
            'full_output' => $process->output(), // Cek semua pesan di sini
            'exit_code' => $process->exitCode(),
            'debug_cmd' => $command
        ], 500);
    }

    /**
     * Mengambil daftar mapping yang tersimpan dan isinya
     */
    public function getMappings()
    {
        $files = Storage::disk('local')->files('mappings');
        $results = [];

        foreach ($files as $file) {
            $name = str_replace(['mappings/', '.json'], '', $file);
            $content = json_decode(Storage::disk('local')->get($file), true);
            $results[] = [
                'name' => $name,
                'data' => $content
            ];
        }

        return response()->json($results);
    }

    public function saveMapping(Request $request)
    {
        try {
            $request->validate([
                'mapping' => 'required|array',
                'sheet' => 'required|string',
                'agent_report_id' => 'required|integer',
            ]);

            $agent = User::findOrFail($request->agent_id);

            Mapping::updateOrCreate(
                [
                    'agent_report_id' => $request->agent_report_id,
                    'sheet' => $request->sheet,
                    'agent_id' => $request->agent_id,
                    'nama_agent' => $agent->name
                ],
                [
                    'mapping_json' => $request->mapping,
                ]
            );

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        // 🔥 Ambil mapping + sheet dari DB
        // $data = DB::table('mappings')
        //     ->where('agent_id', $request->agent_id)
        //     ->latest()
        //     ->first();

        $data = DB::table('mappings')
            ->join('users', 'mappings.agent_id', '=', 'users.id')
            ->where('mappings.agent_id', $request->agent_id)
            ->select(
                'mappings.*',
                'users.name as nama_agent' // 🔥 auto ambil nama
            )
            ->latest('mappings.created_at')
            ->first();

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mapping belum ada'
            ], 400);
        }

        $mappingData = $data->mapping_json;

        $mappingArray = json_decode($mappingData, true);
        $mappingArray['Nama Agen'] = $data->nama_agent;
        $mappingData = json_encode($mappingArray);

        $sheet = $data->sheet; // ✅ ambil dari DB

        if (!$sheet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sheet tidak ditemukan di mapping'
            ], 400);
        }

        $absolutePath = storage_path('app/public/' . $request->file_path);

        if (!file_exists($absolutePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $scriptPath = "/var/www/scripts/yuri_engine.py";

        $outputFileName = 'result_' . \Illuminate\Support\Str::random(5) . '.xlsx';
        $outputPath = storage_path('app/public/temp/' . $outputFileName);

        // 🔥 pastikan folder ada
        if (!file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        $command = sprintf(
            "python3 %s export %s %s %s %s 2>&1",
            escapeshellarg($scriptPath),
            escapeshellarg($absolutePath),
            escapeshellarg($mappingData),
            escapeshellarg($sheet),
            escapeshellarg($outputPath)
        );

        $process = Process::timeout(120)->run($command);

        // 🔥 DEBUG jika gagal
        if (!$process->successful() || !file_exists($outputPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Python gagal generate file',
                'output' => $process->output(),
                'error' => $process->errorOutput(),
                'command' => $command
            ], 500);
        }

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

    public function destroy($id)
    {
        $deleted = DB::table('mappings')
            ->where('agent_report_id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted
        ]);
    }

    public function mappingMulti(Request $request)
    {
        return inertia('Python/MappingMulti', [
            'filePath' => $request->filePath,
            'agent_id' => $request->agent_id,
            'report_id' => $request->report_id,
        ]);
    }

    public function compareMaster(Request $request)
    {
        // Ambil path relatif dari request, misal: "reports/2026/05/abc.xlsx"
        $filePath = $request->file_path;

        // Gabungkan dengan path storage di Docker
        // storage_path('app/public/') akan menghasilkan "/var/www/storage/app/public/"
        $absolutePath = storage_path('app/public/' . $filePath);

        // DEBUG: Cek di log Laravel apakah filenya benar-benar ada
        if (!file_exists($absolutePath)) {
            Log::error("FILE TIDAK KETEMU: " . $absolutePath);
            return response()->json(['error' => "File tidak ditemukan di sistem."], 404);
        }

        $input = [
            'file_path' => $absolutePath, // Kirim "/var/www/storage/app/public/reports/..."
            'source_sheet' => $request->source_sheet,
            'mapping' => $request->mapping ?? [],
            'master_data' => DB::table('items')->select('item_code', 'item_name')->get()
        ];

        // Panggil Python
        $result = \Illuminate\Support\Facades\Process::input(json_encode($input))
            ->run(['python3', base_path('scripts/compare_master.py')]);

        return response()->json(json_decode($result->output()));
    }

    public function processMulti(Request $request)
    {
        $absolutePath = storage_path('app/public/' . $request->file_path);

        $input = [
            'file_path' => $absolutePath,
            'mappings' => $request->mappings,
            'master_data' => DB::table('items')
                ->select('item_code', 'item_name')
                ->get()
        ];

        $process = \Illuminate\Support\Facades\Process::input(json_encode($input))
            ->run(['python3', base_path('scripts/process_multi.py')]);

        if (!$process->successful()) {
            return response()->json([
                'error' => $process->errorOutput()
            ], 500);
        }

        // ✅ RETURN FILE (BINARY)
        return response()->streamDownload(function () use ($process) {
            echo $process->output();
        }, 'export.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        // 🔥 PAKSA TAMPILKAN ERROR DI RESPONSE
        // return response()->json([
        //     'success' => $process->successful(),
        //     'error' => $process->errorOutput(),
        //     'output_preview' => substr($process->output(), 0, 200)
        // ]);
    }

    public function exportMappingPage(Request $request)
    {
        return inertia('Python/ExportMapping', [
            'filePath' => $request->filePath,
            'agent_id' => $request->agent_id,
            'report_id' => $request->report_id,
        ]);
    }

    public function mappingExportscanExcel(Request $request)
    {
        try {

            $request->validate([
                'file_path' => 'required|string',
            ]);

            $absolutePath = storage_path('app/public/' . $request->file_path);

            if (!file_exists($absolutePath)) {
                return response()->json([
                    'error' => 'File tidak ditemukan'
                ], 404);
            }

            // =========================
            // LOAD EXCEL
            // =========================
            $spreadsheet = IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // =========================
            // AUTO DETECT HEADER
            // =========================
            $header = [];

            $header = array_map(function ($h) {
                return trim((string)$h);
            }, $header);

            $header = array_filter($header);
            $header = array_values($header);
            
            dd($header);

            // foreach ($rows as $row) {

            //     $text = strtolower(implode(' ', array_map('strval', $row)));

            //     if (
            //         str_contains($text, 'kode') ||
            //         str_contains($text, 'sku') ||
            //         str_contains($text, 'barang') ||
            //         str_contains($text, 'item')
            //     ) {
            //         $header = $row;
            //         break;
            //     }
            // }

            // CLEAN HEADER
            $header = array_values(array_filter($header, function ($h) {
                return $h !== null && !is_numeric($h);
            }));

            if (!$header) {
                return response()->json([
                    'error' => 'Header tidak ditemukan'
                ], 400);
            }

            // =========================
            // AUTO DETECT KOLOM SKU & STOCK
            // =========================
            $kode_col = null;
            $stock_col = null;

            foreach ($header as $col) {
                $colLower = strtolower($col);

                if (
                    str_contains($colLower, 'kode') ||
                    str_contains($colLower, 'sku')
                ) {
                    $kode_col = $col;
                }

                if (
                    str_contains($colLower, 'so') ||
                    str_contains($colLower, 'stock') ||
                    str_contains($colLower, 'qty')
                ) {
                    $stock_col = $col;
                }
            }

            if (!$kode_col) {
                return response()->json([
                    'error' => 'Kolom SKU tidak terdeteksi'
                ], 400);
            }

            if (!$kode_col) {
                return response()->json([
                    'error' => 'Kolom SKU tidak terdeteksi',
                    'header' => $header
                ], 400);
            }

            if (!$stock_col) {
                return response()->json([
                    'error' => 'Kolom Stock tidak terdeteksi',
                    'header' => $header
                ], 400);
            }

            // setelah detect kolom
            if (!$kode_col || !$stock_col) {
                return response()->json([
                    'error' => 'Kolom tidak terdeteksi',
                    'header' => $header
                ], 400);
            }

            // =========================
            // MASTER DATA
            // =========================
            $master = DB::table('items')
                ->select('item_code', 'item_name', 'item_per_box', 'item_group')
                ->get()
                ->map(fn($i) => (array) $i)
                ->toArray();

            // dd([
            //     'kode_col' => $kode_col,
            //     'stock_col' => $stock_col,
            //     'header' => $header
            // ]);

            // =========================
            // CALL PYTHON
            // =========================
            $input = [
                'file_path' => $absolutePath,
                'source_sheet' => $request->sheet,
                'mapping' => $request->mapping, // 🔥 INI YANG KURANG
                'master_data' => $master,
                'base_path' => base_path(),
            ];


            $process = Process::input(json_encode($input))
                ->timeout(120)
                ->run([
                    'python3',
                    base_path('scripts/mapping_export.py')
                ]);

            if (!$process->successful()) {
                return response()->json([
                    'error' => 'Python gagal',
                    'stderr' => $process->errorOutput()
                ], 500);
            }

            $result = json_decode($process->output(), true);

            if (!$result || isset($result['error'])) {
                return response()->json([
                    'error' => $result['error'] ?? 'Gagal memproses data dari Python (Output Null)',
                    'raw' => $process->output()
                ], 500);
            }

            // $result = json_decode($process->output(), true);

            // if (isset($result['error'])) {
            //     return response()->json([
            //         'error' => $result['error']
            //     ], 500);
            // }

            return response()->download($result['file'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mappingExport(Request $request)
    {
        try {
            // =========================
            // VALIDASI
            // =========================
            $request->validate([
                'file_path' => 'required|string',
                'sheet' => 'required|string',
                'kode_col' => 'required|string',
                'stock_col' => 'nullable|string',
            ]);

            if ($request->kode_col === $request->stock_col) {
                return response()->json([
                    'error' => 'Kolom tidak boleh sama'
                ], 400);
            }

            // =========================
            // PATH FILE
            // =========================
            $absolutePath = storage_path('app/public/' . $request->file_path);

            if (!file_exists($absolutePath)) {
                return response()->json([
                    'error' => 'File tidak ditemukan',
                    'path' => $absolutePath
                ], 404);
            }

            // =========================
            // MASTER DATA
            // =========================
            $master = DB::table('items')
                ->select('item_code', 'item_name', 'item_per_box', 'item_group')
                ->get()
                ->map(fn($i) => (array) $i)
                ->toArray();

            // =========================
            // PAYLOAD KE PYTHON
            // =========================
            $input = [
                'file_path' => $absolutePath,
                'source_sheet' => $request->sheet,
                'mapping' => $request->mapping, // 🔥 INI YANG KURANG
                'master_data' => $master,
                'base_path' => base_path(),
            ];

            // LOG INPUT
            Log::info('PYTHON INPUT', $input);

            // =========================
            // CALL PYTHON
            // =========================
            $process = Process::input(json_encode($input))
                ->timeout(120)
                ->run([
                    'python3',
                    base_path('scripts/mapping_export.py')
                ]);

            // =========================
            // DEBUG OUTPUT PYTHON
            // =========================
            if (!$process->successful()) {
                return response()->json([
                    'error' => 'Python gagal',
                    'python_output' => $process->output(),
                    'python_error' => $process->errorOutput(),
                    'input' => $input
                ], 500);
            }

            $output = $process->output();

            // LOG OUTPUT
            Log::info('PYTHON OUTPUT', ['output' => $output]);

            $result = json_decode($output, true);

            if (!$result) {
                return response()->json([
                    'error' => 'Output Python bukan JSON',
                    'raw_output' => $output
                ], 500);
            }

            if (isset($result['error'])) {
                return response()->json([
                    'error' => $result['error'],
                    'python_output' => $output
                ], 500);
            }

            if (!isset($result['file']) || !file_exists($result['file'])) {
                return response()->json([
                    'error' => 'File output tidak ditemukan',
                    'result' => $result
                ], 500);
            }

            // =========================
            // DOWNLOAD FILE
            // =========================
            return response()->download($result['file'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
}

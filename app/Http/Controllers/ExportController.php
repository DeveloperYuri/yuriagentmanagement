<?php

namespace App\Http\Controllers;

use App\Exports\CMOExport;
use App\Models\AgentExportReport;
use App\Models\AgentExportStock;
use App\Models\AgentReport;
use App\Models\MappingReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;

class ExportController extends Controller
{
    public function exportMappingPage(Request $request)
    {
        $report = DB::table('agent_reports')
            ->where('id', $request->report_id)
            ->first();

        return inertia('Python/ExportPage', [
            'filePath' => $request->filePath,
            'agent_id' => $request->agent_id,
            'report_id' => $request->report_id,
            'fileName' => $report->file_name ?? '',
        ]);
    }

    public function scanFile(Request $request)
    {
        try {
            // =========================
            // MODE 1: UPLOAD FILE
            // =========================
            if ($request->hasFile('file')) {

                $file = $request->file('file');

                $filename = time() . '_' . $file->getClientOriginalName();
                $targetDir = storage_path('app/uploads');

                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }

                $file->move($targetDir, $filename);

                $fullPath = $targetDir . '/' . $filename;
            }

            // =========================
            // MODE 2: FILE DARI DB
            // =========================
            else if ($request->file_path) {

                // 🔥 INI KUNCI
                $fullPath = storage_path('app/public/' . $request->file_path);
            } else {
                return response()->json(['error' => 'File tidak ditemukan'], 400);
            }

            // =========================
            // VALIDASI FILE
            // =========================
            if (!file_exists($fullPath)) {
                return response()->json([
                    'error' => "File tidak ditemukan di server: $fullPath"
                ], 500);
            }

            // =========================
            // JALANKAN PYTHON
            // =========================
            $process = new \Symfony\Component\Process\Process([
                'python3',
                '/var/www/scripts/scan_sheet.py',
                $fullPath
            ]);

            $process->run();

            if (!$process->isSuccessful()) {
                return response()->json([
                    'error' => 'Python gagal',
                    'details' => $process->getErrorOutput()
                ], 500);
            }

            $sheets = json_decode($process->getOutput());

            return response()->json([
                'file_path' => $fullPath,
                'sheets' => $sheets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function scanHeader(Request $request)
    {

        // dd($request->all());

        try {
            $filePath = $request->file_path;
            $sheet = $request->sheet;

            $process = new Process([
                'python3',
                base_path('scripts/scan_header.py'),
                $filePath,
                $sheet
            ]);

            $process->run();


            if (!$process->isSuccessful()) {
                // Kembalikan error asli dari Python agar kita tahu apa yang salah
                return response()->json([
                    'error' => 'Gagal scan header',
                    'details' => $process->getErrorOutput(), // Tambahkan ini
                    'output' => $process->getOutput()       // Dan ini
                ], 500);
            }

            // if (!$process->isSuccessful()) {
            //     // Gunakan Log saja buat debug, jangan dd() kalau lewat Axios
            //     Log::error($process->getErrorOutput());
            //     return response()->json(['error' => 'Gagal scan header'], 500);
            // }


            $headers = json_decode($process->getOutput(), true);

            return response()->json([
                'headers' => $headers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // dd($request->all());
        // return response()->json($request->all());
        // console.log(res.data);
        try {
            $mappingJson = json_encode($request->mapping);

            $user = DB::table('users')
                ->where('id', $request->agent_id)
                ->first();

            DB::table('mappings')->insert([
                'sheet' => $request->sheet,
                'mapping_json' => $mappingJson,
                'mapping_report_id' => $request->mapping_report_id,
                // 'agent_report_id' => $request->agent_report_id,
                'agent_id' => $request->agent_id,
                'nama_agent' => $user->name ?? null,
                // 'nama_agent' => $request->nama_agent,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Mapping saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function process(Request $request)
    {
        try {
            // 1. Ambil data dari request JSON (Bukan upload file lagi)
            // $filePath = $request->file_path;
            $filePath = storage_path('app/public/' . $request->file_path);

            $mapping = DB::table('mappings')
                ->where('agent_id', $request->agent_id)
                ->first();

            // return response()->json([
            //     'mapping' => $mapping
            // ]);


            $aliases = DB::table('item_aliases')
                ->select('agent_name', 'clean_name', 'master_name')
                ->get();

            if (!$mapping) {
                return response()->json([
                    'error' => 'Mapping belum ada untuk report ini'
                ], 400);
            }

            $namaAgent = $mapping->nama_agent ?? '';

            $mappingData = json_decode($mapping->mapping_json, true);

            $mappingJim = $mappingData['jim'] ?? [];
            $mappingInv = $mappingData['invoice'] ?? [];

            // $mappingJim = $request->mapping_jim;
            // $mappingInv = $request->mapping_inv;

            // Validasi dasar
            if (!file_exists($filePath)) {
                return response()->json(['error' => 'File fisik tidak ditemukan di server: ' . $filePath], 404);
            }

            // 2. Ambil Master Data dari DB
            $items = DB::table('items')
                ->select(
                    'item_code',
                    'item_name',
                    'item_per_box'
                )
                ->get();


            // TAMBAH BARU CODE
            $mappings = DB::table('agent_item_mappings')
                ->select([
                    'agent_sku',
                    'item_code',
                ])
                ->get()
                ->toArray();

            // 3. Payload untuk Python
            $payload = [
                "file_path"   => $filePath,
                "mapping_jim" => $mappingJim,
                "mapping_inv" => $mappingInv,
                "master_data" => $items,
                "alias_data"  => $aliases,
                "mapping_data" => $mappings, // TAMBAH BARU CODE
                "nama_agent"  => $namaAgent,
            ];

            // 4. Eksekusi Python
            $process = new \Symfony\Component\Process\Process(['python3', base_path('scripts/processor.py')]);
            $process->setInput(json_encode($payload));
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                return response()->json([
                    'error' => 'Python Processor Gagal',
                    'detail' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ], 500);
            }

            // if (!$process->isSuccessful()) {
            //     return response()->json([
            //         'error' => 'Python Processor Gagal',
            //         'detail' => $process->getErrorOutput()
            //     ], 500);
            // }

            // TAMBAH BARU CODE
            // return response()->json([
            //     'raw_output' => $process->getOutput(),
            //     'raw_error' => $process->getErrorOutput(),
            // ]);

            $result = json_decode(
                $process->getOutput(),
                true
            );

            // =====================
            // VALIDASI RESULT
            // =====================
            if (
                !$result ||
                !is_array($result)
            ) {

                return response()->json([
                    'error' => 'Processor output invalid',
                    'raw_output' =>
                    $process->getOutput(),
                    'raw_error' =>
                    $process->getErrorOutput(),
                ], 500);
            }

            // =====================
            // AMBIL DATA
            // =====================
            $invoiceData =
                $result['invoice_data'] ?? [];

            $stockAgentData =
                $result['stock_agent_data'] ?? [];

            // =====================
            // PERIODE
            // =====================
            $periodeDate = null;

            if (!empty($invoiceData)) {

                $firstDate =
                    $invoiceData[0]['Tanggal Invoice']
                    ?? null;

                if ($firstDate) {

                    $periodeDate =
                        Carbon::parse($firstDate);
                }
            }

            // =====================
            // SAVE INVOICE
            // =====================
            foreach ($invoiceData as $row) {

                AgentExportReport::create([

                    'sheet_name' => 'invoice',

                    'nama_agen' =>
                    $row['Nama Agen'] ?? null,

                    'kode_customer' =>
                    $row['Kode Customer'] ?? null,

                    'nama_customer' =>
                    $row['Nama Customer'] ?? null,

                    'alamat_customer' =>
                    $row['Alamat Customer'] ?? null,

                    'nomor_telepon_customer' =>
                    $row['Nomor Telepon/HP Customer']
                        ?? null,

                    'invoice_nomor_agen' =>
                    $row['Invoice Nomor Agen']
                        ?? null,

                    'tanggal_invoice' =>
                    $row['Tanggal Invoice']
                        ?? null,

                    'tipe_customer' =>
                    $row['Tipe Customer']
                        ?? null,

                    'sales' =>
                    $row['Sales']
                        ?? null,

                    'sku_kode_agen' =>
                    $row['SKU Kode Agen']
                        ?? null,

                    'nama_sku' =>
                    $row['Nama SKU']
                        ?? null,

                    'qty_terjual_pcs' =>
                    (float) (
                        $row['Qty Terjual (PCS)']
                        ?? 0
                    ),

                    'diskon_1_reguler' =>
                    (float) (
                        $row['% Diskon 1 (Reguler)']
                        ?? 0
                    ),

                    'diskon_2_cash' =>
                    (float) (
                        $row['% Diskon 2 (Cash)']
                        ?? 0
                    ),

                    'diskon_3_dc_free' =>
                    (float) (
                        $row['% Diskon 3 (DC Free)']
                        ?? 0
                    ),

                    'diskon_4_promo_1' =>
                    (float) (
                        $row['% Diskon 4 (Promo 1)']
                        ?? 0
                    ),

                    'diskon_5_promo_2' =>
                    (float) (
                        $row['% Diskon 5 (Promo 2)']
                        ?? 0
                    ),

                    'diskon_6_rp' =>
                    (float) (
                        $row['% Diskon 6 (Rp)']
                        ?? 0
                    ),

                    'quantity_bonus' =>
                    (float) (
                        $row['Quantity Bonus']
                        ?? 0
                    ),

                    'rafraksi' =>
                    (float) (
                        $row['Rafraksi']
                        ?? 0
                    ),

                    'total_invoice_value' =>
                    (float) (
                        $row['Total Invoice Value']
                        ?? 0
                    ),

                    'match_item' =>
                    $row['MATCH_STATUS']
                        ?? null,

                    'kode_sku_jim' =>
                    $row['Item Code']
                        ?? null,

                    'item_name_jim' =>
                    $row['Item Name']
                        ?? null,
                    'user_id' =>
                    $request->agent_id,
                ]);
            }

            // =====================
            // SAVE STOCK
            // =====================
            foreach ($stockAgentData as $row) {

                AgentExportStock::create([

                    'kode_sku_agent' =>
                    $row['Kode SKU Agent']
                        ?? null,

                    'kode_sku_jim' =>
                    $row['Item Code']
                        ?? null,

                    'item_name_jim' =>
                    $row['Item Name']
                        ?? null,

                    'stock_karton' =>
                    $row['Stock PCS']
                        ?? 0,

                    'bulan' =>
                    $periodeDate?->month,

                    'tahun' =>
                    $periodeDate?->year,

                    'periode' =>
                    $periodeDate?->format('Y-m'),

                    'agent_id' =>
                    $request->agent_id,
                ]);
            }

            // =====================
            // EXCEL
            // =====================
            $excelBase64 =
                $result['excel_base64']
                ?? '';

            if (!$excelBase64) {

                return response()->json([
                    'error' =>
                    'Excel kosong',
                    'result' =>
                    $result,
                ], 500);
            }

            $excelBinary =
                base64_decode($excelBase64);

            // =====================
            // DOWNLOAD
            // =====================
            return response($excelBinary)
                ->header(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                )
                ->header(
                    'Content-Disposition',
                    'attachment; filename="Hasil_Mapping_3_Sheet.xlsx"'
                );

            // dd($result);

            // 5. Return ke Vue sebagai Download
            // return response($process->getOutput())
            //     ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            //     ->header('Content-Disposition', 'attachment; filename="Hasil_Mapping_3_Sheet.xlsx"');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // PROSES LAMA BENER JGN DI APA2in YA
    // public function process(Request $request)
    // {
    //     try {
    //         // 1. Ambil data dari request JSON (Bukan upload file lagi)
    //         // $filePath = $request->file_path;
    //         $filePath = storage_path('app/public/' . $request->file_path);

    //         $mapping = DB::table('mappings')
    //             ->where('agent_id', $request->agent_id)
    //             ->first();

    //         $aliases = DB::table('item_aliases')
    //             ->select('agent_name', 'clean_name', 'master_name')
    //             ->get();

    //         if (!$mapping) {
    //             return response()->json([
    //                 'error' => 'Mapping belum ada untuk report ini'
    //             ], 400);
    //         }

    //         $namaAgent = $mapping->nama_agent ?? '';

    //         $mappingData = json_decode($mapping->mapping_json, true);

    //         $mappingJim = $mappingData['jim'] ?? [];
    //         $mappingInv = $mappingData['invoice'] ?? [];

    //         // $mappingJim = $request->mapping_jim;
    //         // $mappingInv = $request->mapping_inv;

    //         // Validasi dasar
    //         if (!file_exists($filePath)) {
    //             return response()->json(['error' => 'File fisik tidak ditemukan di server: ' . $filePath], 404);
    //         }

    //         // 2. Ambil Master Data dari DB
    //         $items = DB::table('items')
    //             ->select(
    //                 'item_code',
    //                 'item_name',
    //                 'item_per_box'
    //             )
    //             ->get();


    //         // TAMBAH BARU CODE
    //         $mappings = DB::table('agent_item_mappings')
    //             ->select([
    //                 'agent_sku',
    //                 'item_code',
    //             ])
    //             ->get()
    //             ->toArray();

    //         // 3. Payload untuk Python
    //         $payload = [
    //             "file_path"   => $filePath,
    //             "mapping_jim" => $mappingJim,
    //             "mapping_inv" => $mappingInv,
    //             "master_data" => $items,
    //             "alias_data"  => $aliases,
    //             "mapping_data" => $mappings, // TAMBAH BARU CODE
    //             "nama_agent"  => $namaAgent,
    //         ];

    //         // 4. Eksekusi Python
    //         $process = new \Symfony\Component\Process\Process(['python3', base_path('scripts/processor.py')]);
    //         $process->setInput(json_encode($payload));
    //         $process->setTimeout(300);
    //         $process->run();

    //         if (!$process->isSuccessful()) {
    //             return response()->json([
    //                 'error' => 'Python Processor Gagal',
    //                 'detail' => $process->getErrorOutput(),
    //                 'output' => $process->getOutput(),
    //             ], 500);
    //         }

    //         // if (!$process->isSuccessful()) {
    //         //     return response()->json([
    //         //         'error' => 'Python Processor Gagal',
    //         //         'detail' => $process->getErrorOutput()
    //         //     ], 500);
    //         // }

    //         // TAMBAH BARU CODE
    //         // return response()->json([
    //         //     'raw_output' => $process->getOutput(),
    //         //     'raw_error' => $process->getErrorOutput(),
    //         // ]);

    //         $result = json_decode($process->getOutput(), true);

    //         $invoiceData = $result['invoice_data'] ?? [];
    //         $stockAgentData = $result['stock_agent_data'] ?? [];

    //         $periodeDate = null;

    //         if (!empty($invoiceData)) {

    //             $firstDate =
    //                 $invoiceData[0]['Tanggal Invoice'] ?? null;

    //             if ($firstDate) {

    //                 $periodeDate = Carbon::parse($firstDate);
    //             }
    //         }

    //         foreach ($invoiceData as $row) {

    //             AgentExportReport::create([

    //                 'sheet_name' => 'invoice',

    //                 'nama_agen' =>
    //                 $row['Nama Agen'] ?? null,

    //                 'kode_customer' =>
    //                 $row['Kode Customer'] ?? null,

    //                 'nama_customer' =>
    //                 $row['Nama Customer'] ?? null,

    //                 'alamat_customer' =>
    //                 $row['Alamat Customer'] ?? null,

    //                 'nomor_telepon_customer' =>
    //                 $row['Nomor Telepon/HP Customer'] ?? null,

    //                 'invoice_nomor_agen' =>
    //                 $row['Invoice Nomor Agen'] ?? null,

    //                 'tanggal_invoice' =>
    //                 $row['Tanggal Invoice'] ?? null,

    //                 'tipe_customer' =>
    //                 $row['Tipe Customer'] ?? null,

    //                 'sales' =>
    //                 $row['Sales'] ?? null,

    //                 'sku_kode_agen' =>
    //                 $row['SKU Kode Agen'] ?? null,

    //                 'nama_sku' =>
    //                 $row['Nama SKU'] ?? null,

    //                 'qty_terjual_pcs' =>
    //                 (float) ($row['Qty Terjual (PCS)'] ?: 0),

    //                 'diskon_1_reguler' =>
    //                 (float) ($row['% Diskon 1 (Reguler)'] ?: 0),

    //                 'diskon_2_cash' =>
    //                 (float) ($row['% Diskon 2 (Cash)'] ?: 0),

    //                 'diskon_3_dc_free' =>
    //                 (float) ($row['% Diskon 3 (DC Free)'] ?: 0),

    //                 'diskon_4_promo_1' =>
    //                 (float) ($row['% Diskon 4 (Promo 1)'] ?: 0),

    //                 'diskon_5_promo_2' =>
    //                 (float) ($row['% Diskon 5 (Promo 2)'] ?: 0),

    //                 'diskon_6_rp' =>
    //                 (float) ($row['% Diskon 6 (Rp)'] ?: 0),

    //                 'quantity_bonus' =>
    //                 (float) ($row['Quantity Bonus'] ?: 0),

    //                 'rafraksi' =>
    //                 (float) ($row['Rafraksi'] ?: 0),

    //                 'total_invoice_value' =>
    //                 (float) ($row['Total Invoice Value'] ?: 0),

    //                 'match_item' =>
    //                 $row['MATCH ITEM'] ?? null,
    //             ]);
    //         }

    //         foreach ($stockAgentData as $row) {

    //             AgentExportStock::create([

    //                 'kode_sku_agent' =>
    //                 $row['Kode SKU Agent'] ?? null,

    //                 'kode_sku_jim' =>
    //                 $row['Kode SKU JIM'] ?? null,

    //                 'item_name_jim' =>
    //                 $row['Item Name JIM'] ?? null,

    //                 'stock_karton' =>
    //                 $row['Stock (Karton)'] ?? 0,

    //                 'bulan' =>
    //                 $periodeDate?->month,

    //                 'tahun' =>
    //                 $periodeDate?->year,

    //                 'periode' =>
    //                 $periodeDate?->format('Y-m'),

    //                 'agent_id' =>
    //                 $request->agent_id,

    //             ]);
    //         }

    //         $excelBinary = base64_decode($result['excel_base64']);

    //         return response($excelBinary)
    //             ->header(
    //                 'Content-Type',
    //                 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    //             )
    //             ->header(
    //                 'Content-Disposition',
    //                 'attachment; filename="Hasil_Mapping_3_Sheet.xlsx"'
    //             );

    //         // dd($result);

    //         // 5. Return ke Vue sebagai Download
    //         // return response($process->getOutput())
    //         //     ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    //         //     ->header('Content-Disposition', 'attachment; filename="Hasil_Mapping_3_Sheet.xlsx"');
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

    // public function processCMO(Request $request)
    // {
    //     $report = AgentReport::find($request->report_id);

    //     return Excel::download(
    //         new CMOExport($report->id),
    //         'EXPORT_CMO.xlsx'
    //     );
    //     // return Excel::download(
    //     //     new CMOExport($request->agent_id),
    //     //     'EXPORT_CMO.xlsx'
    //     // );
    // }

    public function processCMO(Request $request)
    {
        try {

            Log::info('START EXPORT CMO');

            // $report = AgentReport::find($request->report_id);
            $report = MappingReport::find($request->report_id);

            return Excel::download(
                new CMOExport($report->id),
                'EXPORT_CMO.xlsx'
            );
        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function normalize(Request $request)
    {
        // Pastikan request membawa tipe report (contoh: 'LK-000019' atau '000019')
        $reportType = $request->input('report_type', 'UNKNOWN');

        $fullInputPath = storage_path(
            'app/public/' . $request->file_path
        );

        // $outputFileName = 'normalized_' . time() . '.xlsx';
        $originalName = pathinfo(
            $request->input('report_type'),
            PATHINFO_FILENAME
        );

        $outputFileName = 'normalized_' . $originalName . '.xlsx';

        // Pastikan folder 'app/temp' sudah ada atau gunakan storage_path('app/public/temp/...')
        $fullOutputPath = storage_path(
            'app/temp/' . $outputFileName
        );

        $pythonScript = base_path(
            'scripts/normalize.py'
        );

        // Tambahkan $reportType sebagai argumen ke-3, bungkus dengan escapeshellarg demi keamanan CLI
        $process = Process::fromShellCommandline(
            "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($fullInputPath) . " " . escapeshellarg($fullOutputPath) . " " . escapeshellarg($reportType)
        );

        $process->setTimeout(300);
        // $process->run(function ($type, $buffer) {
        //     echo $buffer;
        // });
        // $process->run();

        $process->run();

        // $debugOutput = $process->getOutput();
        // $debugError = $process->getErrorOutput();

        // if (!$process->isSuccessful()) {
        //     return response()->json([
        //         'success' => false,
        //         'stdout' => $debugOutput,
        //         'stderr' => $debugError,
        //     ], 500);
        // }

        // return response()->json([
        //     'success' => true,
        //     'stdout' => $debugOutput,
        //     'stderr' => $debugError,
        //     'download' => asset('storage/temp/' . $outputFileName)
        // ]);

        if (!$process->isSuccessful()) {
            return response()->json([
                'success' => false,
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ], 500);
        }

        return response()->download(
            $fullOutputPath,
            $outputFileName
        );
    }

    // public function normalize(Request $request)
    // {
    //     $request->validate([
    //         'file_path' => 'required'
    //     ]);

    //     // ==========================================
    //     // FILE ASLI
    //     // ==========================================
    //     $fullInputPath = storage_path(
    //         'app/private/' . $request->file_path
    //     );

    //     // ==========================================
    //     // OUTPUT
    //     // ==========================================
    //     $outputFileName =
    //         'normalized_' . time() . '.xlsx';

    //     $fullOutputPath = storage_path(
    //         'app/temp/' . $outputFileName
    //     );

    //     // ==========================================
    //     // PYTHON SCRIPT
    //     // ==========================================
    //     $pythonScript = storage_path(
    //         'app/python/normalize.py'
    //     );

    //     // ==========================================
    //     // RUN PYTHON
    //     // ==========================================
    //     // $process = new Process([
    //     //     'python',
    //     //     $pythonScript,
    //     //     $fullInputPath,
    //     //     $fullOutputPath
    //     // ]);

    //     $process = Process::fromShellCommandline(
    //         "python \"$pythonScript\" \"$fullInputPath\" \"$fullOutputPath\""
    //     );

    //     $process->setTimeout(300);

    //     $process->run();

    //     dd([
    //         'success' => $process->isSuccessful(),
    //         'output' => $process->getOutput(),
    //         'error' => $process->getErrorOutput(),
    //         'command' => $process->getCommandLine(),
    //     ]);

    //     // ==========================================
    //     // ERROR
    //     // ==========================================
    //     if (!$process->isSuccessful()) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => $process->getErrorOutput()
    //         ], 500);
    //     }

    //     // ==========================================
    //     // DOWNLOAD
    //     // ==========================================
    //     return response()->download(
    //         $fullOutputPath,
    //         $outputFileName
    //     );
    // }
}

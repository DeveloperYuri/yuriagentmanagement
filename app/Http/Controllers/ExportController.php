<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'agent_report_id' => $request->agent_report_id,
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
            // ✅ TARO DI SINI
            $mapping = DB::table('mappings')
                ->where('agent_id', $request->agent_id)
                ->first();

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

            // 3. Payload untuk Python
            $payload = [
                "file_path"   => $filePath,
                "mapping_jim" => $mappingJim,
                "mapping_inv" => $mappingInv,
                "master_data" => $items,
                "alias_data"  => $aliases,
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
                    'detail' => $process->getErrorOutput()
                ], 500);
            }

            // 5. Return ke Vue sebagai Download
            return response($process->getOutput())
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="Hasil_Mapping_3_Sheet.xlsx"');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

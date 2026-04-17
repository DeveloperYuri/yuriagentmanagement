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
}

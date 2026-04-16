<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

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

        return inertia('Python/Mapping', [
            'filePath' => $filePath
        ]);
    }

    // public function scan(Request $request)
    // {
    //     $request->validate([
    //         'file_path' => 'required|string'
    //     ]);

    //     $absolutePath = storage_path('app/public/' . $request->file_path);

    //     if (!file_exists($absolutePath)) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'File tidak ditemukan',
    //             'debug_path' => $absolutePath
    //         ], 404);
    //     }

    //     $scriptPath = "/var/www/scripts/yuri_engine.py";

    //     $process = \Illuminate\Support\Facades\Process::run([
    //         'python3',
    //         $scriptPath,
    //         'scan',
    //         $absolutePath
    //     ]);

    //     if ($process->successful()) {
    //         return response()->json(json_decode($process->output(), true));
    //     }

    //     return response()->json([
    //         'status' => 'error',
    //         'message' => $process->errorOutput(),
    //         'output' => $process->output()
    //     ], 500);
    // }

    // public function mapping(Request $request)
    // {
    //     return Inertia::render('Python/Mapping', [
    //         'filePath' => $request->file_path,
    //     ]);
    // }

    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required',
            'mapping' => 'required'
        ]);

        $tempInput = $request->file('file')->store('temp');

        // Sesuaikan path input dan output dengan folder private
        $inputPath      = "/var/www/storage/app/private/{$tempInput}";
        $outputFileName = 'yuri_pro_' . \Illuminate\Support\Str::random(5) . '.xlsx';
        $outputPath     = "/var/www/storage/app/private/temp/{$outputFileName}";
        $scriptPath     = "/var/www/scripts/yuri_engine.py";

        $command = sprintf(
            "python3 %s export %s %s %s",
            $scriptPath,
            escapeshellarg($inputPath),
            escapeshellarg($request->mapping),
            escapeshellarg($outputPath)
        );

        $process = \Illuminate\Support\Facades\Process::timeout(120)->run($command);

        if ($process->successful()) {
            return response()->download($outputPath)->deleteFileAfterSend(true);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Python Error: ' . $process->errorOutput()
        ], 500);
    }

    public function saveMapping(Request $request)
    {
        $request->validate([
            'name' => 'required|string|alpha_dash',
            'mapping' => 'required|array'
        ]);

        $filename = "mappings/{$request->name}.json";
        Storage::disk('local')->put($filename, json_encode($request->mapping));

        return response()->json(['status' => 'success', 'message' => 'Mapping berhasil disimpan!']);
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
}

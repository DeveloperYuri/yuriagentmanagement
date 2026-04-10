<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImportController extends Controller
{

    public function mapping(Request $request)
    {
        $filePath = $request->filePath;
        $agentId = $request->agent_id; // 🔥 ambil agent

        // 🔥 DEBUG DI SINI
        // dd([
        //     'agent_id' => $agentId,
        //     'mapping' => \App\Models\UserMapping::where('user_id', $agentId)->get()
        // ]);


        $fullPath = storage_path('app/public/' . $filePath);

        // dd($fullPath);

        if (!file_exists($fullPath)) {
            dd('File tidak ditemukan!', $fullPath);
        }

        $rows = Excel::toArray([], $fullPath);

        if (empty($rows) || empty($rows[0])) {
            dd('Excel kosong atau tidak valid');
        }

        // 🔥 CEK MAPPING DULU
        $savedMapping = \App\Models\UserMapping::where('user_id', $agentId)
            ->pluck('excel_column', 'db_column')
            ->toArray();

        // ✅ KALAU SUDAH ADA → LANGSUNG PROCESS
        if (!empty($savedMapping)) {
            return $this->processWithMapping($filePath, $savedMapping);
        }

        // ❌ KALAU BELUM ADA → LANJUT KE MAPPING UI
        $data = $rows[0];

        $headerRow = null;

        foreach ($data as $row) {
            $filled = array_filter($row);

            if (count($filled) > 5) {
                $headerRow = $row;
                break;
            }
        }

        if (!$headerRow) {
            // fallback biar nggak error
            $headerRow = $data[0];
        }

        $headers = array_values(array_filter($headerRow));

        $dbColumns = [
            'nama_agen',
            'kode_customer',
            'nama_customer',
            'alamat_customer',
            'nomor_telepon_hp_customer',
            'invoice_nomor_agen',
            'tanggal_invoice',
            'tipe_customer',
            'sales',
            'sku_kode_agen',
            'nama_sku',
            'qty_terjual',
            'diskon1',
            'diskon2',
            'diskon3',
            'diskon4',
            'diskon5',
            'diskon6',
            'quantity_bonus',
            'rafraksi',
            'total_invoice_value',
        ];

        $agents = \App\Models\User::role('Admin Agent')->get(['id', 'name']);

        return Inertia::render('Import/Mapping', [
            'excelHeaders' => $headers,
            'tempPath' => $filePath,
            'dbColumns' => $dbColumns,
            'agents' => $agents,
            'selectedAgentId' => $agentId // 🔥 biar auto ke-select di Vue
        ]);
    }

    private function processWithMapping($filePath, $mapping)
    {
        $fullPath = storage_path('app/public/' . $filePath);

        $rows = Excel::toArray([], $fullPath)[0] ?? [];

        if (empty($rows)) {
            dd('Data kosong');
        }

        // 🔥 DETEK HEADER (SAMA SEPERTI FUNCTION PROCESS)
        $headerIndex = null;
        $maxFilled = 0;

        foreach ($rows as $i => $row) {
            $filled = count(array_filter($row));
            if ($filled > $maxFilled) {
                $maxFilled = $filled;
                $headerIndex = $i;
            }
        }

        if ($headerIndex === null) {
            dd('Header tidak ditemukan');
        }

        $headers = $rows[$headerIndex];

        $result = [];

        foreach ($rows as $i => $row) {

            if ($i <= $headerIndex) continue;

            $row = array_pad($row, count($headers), null);
            $assocRow = array_combine($headers, $row);

            $item = [];

            foreach ($mapping as $db => $excel) {
                $item[$db] = $excel ? ($assocRow[$excel] ?? null) : null;
            }

            $result[] = $item;
        }

        return Inertia::render('Import/Preview', [
            'data' => $result,
            'dbColumns' => array_keys($mapping)
        ]);
    }

    public function resetMapping(Request $request)
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        UserMapping::where('user_id', $request->agent_id)->delete();

        return back()->with('success', 'Mapping berhasil direset');
    }

    // public function mapping(Request $request)
    // {
    //     $filePath = $request->filePath;

    //     $fullPath = storage_path('app/public/' . $filePath);

    //     if (!file_exists($fullPath)) {
    //         dd('File tidak ditemukan!', $fullPath);
    //     }

    //     $rows = Excel::toArray([], $fullPath);

    //     if (empty($rows) || empty($rows[0])) {
    //         dd('Excel kosong atau tidak valid');
    //     }

    //     $data = $rows[0];

    //     // 🔥 Cari baris header yang bener (yang isinya banyak string)
    //     $headerRow = null;

    //     foreach ($data as $row) {
    //         $filled = array_filter($row); // buang null

    //         if (count($filled) > 5) { // asumsi header punya banyak kolom
    //             $headerRow = $row;
    //             break;
    //         }
    //     }

    //     if (!$headerRow) {
    //         dd('Header tidak ditemukan!');
    //     }

    //     // 🔥 Bersihin null
    //     $headers = array_values(array_filter($headerRow));

    //     $dbColumns = [
    //         'nama_agen',
    //         'kode_customer',
    //         'nama_customer',
    //         'alamat_customer',
    //         'nomor_telepon_hp_customer',
    //         'invoice_nomor_agen',
    //         'tanggal_invoice',
    //         'tipe_customer',
    //         'sales',
    //         'sku_kode_agen',
    //         'nama_sku',
    //         'qty_terjual',
    //         'diskon1',
    //         'diskon2',
    //         'diskon3',
    //         'diskon4',
    //         'diskon5',
    //         'diskon6',
    //         'quantity_bonus',
    //         'rafraksi',
    //         'total_invoice_value',
    //     ];


    //     $agents = User::role('Admin Agent')->get(['id', 'name']);
    //     // $agents = User::where('roles', 'Admin Agent')->get(['id', 'name']);

    //     return Inertia::render('Import/Mapping', [
    //         'excelHeaders' => $headers, // ⚠️ SESUAIKAN NAMA DENGAN VUE
    //         'tempPath' => $filePath,
    //         'dbColumns' => $dbColumns,
    //         'agents' => $agents
    //     ]);
    // }

    public function saveMapping(Request $request)
    {
        $request->validate([
            'mapping' => 'required|array',
            'agent_id' => 'required|exists:users,id',
        ]);

        $agentId = $request->agent_id;
        $mapping = $request->mapping;

        foreach ($mapping as $dbColumn => $excelHeader) {
            if (!$excelHeader) continue;

            UserMapping::updateOrCreate(
                ['user_id' => $agentId, 'db_column' => $dbColumn],
                ['excel_column' => $excelHeader]
            );
        }

        return back()->with('success', 'Mapping berhasil disimpan');
    }

    // public function saveMapping(Request $request)
    // {
    //     $request->validate([
    //         'mapping' => 'required|array', // ['db_column' => 'excel_column']
    //     ]);


    //     $userId = Auth::id();
    //     $mapping = $request->mapping;

    //     foreach ($mapping as $dbColumn => $excelHeader) {
    //         if (!$excelHeader) continue; // skip kolom yang kosong
    //         \App\Models\UserMapping::updateOrCreate(
    //             ['user_id' => $userId, 'db_column' => $dbColumn],
    //             ['excel_column' => $excelHeader]
    //         );
    //     }

    //     return response()->json(['message' => 'Mapping berhasil disimpan']);
    // }

    public function process(Request $request)
    {
        $path = $request->filePath;      // path file Excel
        $mapping = $request->mapping;    // mapping db => excel

        $dbColumns = array_keys($mapping); // semua kolom db

        $fullPath = storage_path('app/public/' . $path);
        $rows = Excel::toArray([], $fullPath)[0] ?? [];

        if (empty($rows)) {
            dd('Data kosong di sheet pertama');
        }

        // 🔥 DETEK HEADER (baris dengan paling banyak kolom terisi)
        $headerIndex = null;
        $maxFilled = 0;
        foreach ($rows as $i => $row) {
            $filled = count(array_filter($row));
            if ($filled > $maxFilled) {
                $maxFilled = $filled;
                $headerIndex = $i;
            }
        }

        if ($headerIndex === null) {
            dd('Header tidak ditemukan');
        }

        $headers = $rows[$headerIndex];

        // 🔥 PROSES DATA
        $result = [];
        foreach ($rows as $i => $row) {
            if ($i <= $headerIndex) continue;

            $assocRow = array_combine($headers, $row);

            $item = [];
            foreach ($mapping as $db => $excel) {
                $item[$db] = $excel ? ($assocRow[$excel] ?? null) : null;
            }

            // pastikan semua dbColumns ada
            foreach ($dbColumns as $col) {
                if (!isset($item[$col])) $item[$col] = null;
            }

            $result[] = $item;
        }

        // 🔥 SIMPAN DI SESSION supaya GET preview aman saat refresh
        session([
            'preview_data' => $result,
            'preview_columns' => $dbColumns
        ]);

        // redirect ke GET preview
        return redirect()->route('import.preview');
    }

    // 🔥 GET: tampilkan preview
    public function preview()
    {
        $data = session('preview_data', []);
        $dbColumns = session('preview_columns', []);

        return Inertia::render('Import/Preview', [
            'data' => $data,
            'dbColumns' => $dbColumns
        ]);
    }

    // public function process(Request $request)
    // {
    //     $path = $request->filePath;
    //     $mapping = $request->mapping;

    //     $fullPath = storage_path('app/public/' . $path);

    //     $rows = Excel::toArray([], $fullPath)[0];

    //     if (empty($rows)) {
    //         dd('Data kosong');
    //     }

    //     // 🔥 AUTO DETECT HEADER (cari baris yang banyak isinya)
    //     $headerIndex = null;

    //     foreach ($rows as $i => $row) {
    //         $filled = count(array_filter($row)); // hitung kolom yg ada isinya

    //         if ($filled > 5) { // threshold (bisa diubah)
    //             $headerIndex = $i;
    //             break;
    //         }
    //     }

    //     if ($headerIndex === null) {
    //         dd('Header tidak ditemukan');
    //     }

    //     $headers = $rows[$headerIndex];

    //     $result = [];

    //     foreach ($rows as $index => $row) {

    //         // skip sebelum header
    //         if ($index <= $headerIndex) continue;

    //         // 🔥 gabungkan header + data
    //         $assocRow = array_combine($headers, $row);

    //         $item = [];

    //         foreach ($mapping as $db => $excel) {
    //             if (!$excel) continue;

    //             $item[$db] = $assocRow[$excel] ?? null;
    //         }

    //         $result[] = $item;
    //     }

    //     return Inertia::render('Import/Preview', [
    //         'data' => $result
    //     ]);
    // }

    // public function preview()
    // {
    //     $data = session('preview_data', []);
    //     $dbColumns = session('preview_columns', []);

    //     return Inertia::render('Import/Preview', [
    //         'data' => $data,
    //         'dbColumns' => $dbColumns
    //     ]);
    // }
}

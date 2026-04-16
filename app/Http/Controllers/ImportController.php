<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMapping;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
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
            'no',
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
            // $result[] = [
            //     'no_urut' => count($result) + 1, // 🔥 increment
            //     ...$item
            // ];

            // dd([
            //     'headers' => $headers,
            //     'sample_assoc' => $assocRow,
            //     'mapping' => $mapping,
            //     'sample_item' => $item
            // ]);

            // dd([
            //     'total_result' => count($result),
            // ]);
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
            // $result[] = [
            //     'no_urut' => count($result) + 1, // 🔥 increment
            //     ...$item
            // ];

            // dd([
            //     'headers' => $headers,
            //     'sample_assoc' => $assocRow,
            //     'mapping' => $mapping,
            //     'sample_item' => $item
            // ]);

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

    // public function scanRawExcel(Request $request)
    // {
    //     // 0. Naikkan Limit Memori & Waktu (Penting untuk ribuan baris)
    //     ini_set('memory_limit', '1024M');
    //     set_time_limit(300);

    //     // 1. Proteksi Auth
    //     if (!Auth::check()) {
    //         return back()->withErrors(['message' => 'Sesi berakhir, silakan login ulang.']);
    //     }

    //     $filePath = $request->filePath;
    //     if (!$filePath || !Storage::disk('public')->exists($filePath)) {
    //         return back()->withErrors(['message' => 'File tidak ditemukan!']);
    //     }

    //     $fullPath = Storage::disk('public')->path($filePath);

    //     try {
    //         // 2. Baca Excel ke Array
    //         $sheets = Excel::toArray([], $fullPath);
    //         $rows = $sheets[0] ?? [];

    //         if (empty($rows)) {
    //             return back()->withErrors(['message' => 'File Excel kosong!']);
    //         }

    //         // 3. SNIFFER: Cari Baris Header (Mencari teks 'namabarang' atau 'netto')
    //         $headerIndex = collect($rows)->search(function ($row) {
    //             $searchable = collect($row)->map(fn($v) => strtolower(trim($v)));
    //             return $searchable->contains('namabarang') ||
    //                 $searchable->contains('nama barang') ||
    //                 $searchable->contains('netto');
    //         });

    //         // Default ke baris 3 (index 2) kalau tidak ketemu
    //         $headerIndex = ($headerIndex !== false) ? $headerIndex : 2;
    //         $headerRow = array_map('trim', $rows[$headerIndex]);

    //         // 4. PARSER: Mapping Data Secara Aman
    //         $allData = collect($rows)
    //             ->slice($headerIndex + 1)
    //             ->map(function ($row, $rowIndex) use ($headerRow) {
    //                 $finalRow = [];

    //                 foreach ($headerRow as $keyIndex => $headerName) {
    //                     // Beri nama unik jika header kosong untuk menghindari error JSON
    //                     $key = !empty($headerName) ? $headerName : "kolom_" . $keyIndex;
    //                     $value = $row[$keyIndex] ?? null;

    //                     $lowKey = strtolower($key);

    //                     // A. Konversi Tanggal (Format Excel 46112 -> YYYY-MM-DD)
    //                     if (str_contains($lowKey, 'tanggal') && is_numeric($value)) {
    //                         try {
    //                             $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
    //                         } catch (\Exception $e) {
    //                             // Biarkan value apa adanya jika gagal konversi
    //                         }
    //                     }

    //                     // B. Konversi Angka (Netto, Qty, Harga, dll)
    //                     if (in_array($lowKey, ['netto', 'qty', 'harga', 'bruto', 'totqty'])) {
    //                         // Bersihkan pemisah ribuan (titik/koma)
    //                         $cleanValue = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
    //                         $value = is_numeric($cleanValue) ? (float) $cleanValue : 0;
    //                     }

    //                     $finalRow[$key] = $value;
    //                 }
    //                 return $finalRow;
    //             })
    //             ->filter(fn($item) => !empty(array_filter($item))) // Buang baris kosong
    //             ->values();

    //         // 5. CACHING: Simpan Data Per User
    //         $cacheKey = 'excel_import_' . Auth::id();
    //         Cache::put($cacheKey, $allData, 600); // Simpan selama 10 menit

    //         // 6. FINAL RESPONSE: Kirim JSON ke Inertia
    //         return back()->with('excelData', [
    //             'preview'    => $allData->take(100), // Preview 100 baris saja biar browser enteng
    //             'total_rows' => $allData->count(),
    //             'headers'    => $headerRow,
    //             'cacheKey'   => $cacheKey
    //         ]);
    //     } catch (\Exception $e) {
    //         // Jika error, kirim pesan error asli ke session agar muncul di Vue
    //         return back()->withErrors(['message' => 'Gagal Parser: ' . $e->getMessage()]);
    //     }
    // }

    // use Maatwebsite\Excel\Facades\Excel;
    // use Carbon\Carbon;

    // public function scanRawExcel(Request $request)
    // {
    //     $filePath = $request->input('filePath'); // ✅ FIX

    //     // 1. Validasi file
    //     if (!$filePath || !Storage::disk('public')->exists($filePath)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'File tidak ditemukan',
    //             'filePath' => $filePath
    //         ], 404);
    //     }

    //     $fullPath = Storage::disk('public')->path($filePath);

    //     try {
    //         // 2. Ambil data excel
    //         $rows = Excel::toArray([], $fullPath)[0] ?? [];

    //         if (empty($rows)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Excel kosong'
    //             ], 400);
    //         }

    //         // 3. Cari header otomatis
    //         $headerRow = null;
    //         $headerIndex = 0;

    //         foreach ($rows as $index => $row) {
    //             $filled = array_filter($row, fn($v) => $v !== null && $v !== '');
    //             if (count($filled) > 3) {
    //                 $headerRow = $row;
    //                 $headerIndex = $index;
    //                 break;
    //             }
    //         }

    //         if (!$headerRow) {
    //             $headerRow = $rows[0];
    //         }

    //         // 4. Normalisasi header
    //         $headers = [];
    //         $used = [];

    //         foreach ($headerRow as $col) {
    //             $key = $col
    //                 ? strtolower(trim(str_replace([' ', '.', '/'], '_', $col)))
    //                 : 'kolom_' . uniqid();

    //             if (in_array($key, $used)) {
    //                 $key .= '_2';
    //             }

    //             $used[] = $key;
    //             $headers[] = $key;
    //         }

    //         // 5. Mapping data
    //         $result = [];

    //         foreach (array_slice($rows, $headerIndex + 1) as $row) {
    //             if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
    //                 continue;
    //             }

    //             $item = [];

    //             foreach ($headers as $i => $key) {
    //                 $val = $row[$i] ?? null;

    //                 // Convert Excel Date
    //                 if (is_numeric($val) && str_contains($key, 'tanggal')) {
    //                     try {
    //                         $val = Carbon::instance(
    //                             \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)
    //                         )->format('Y-m-d');
    //                     } catch (\Exception $e) {
    //                     }
    //                 }

    //                 $item[$key] = $val;
    //             }

    //             $result[] = $item;
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'headers' => $headers,
    //             'total' => count($result),
    //             'data' => $result
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function scanRawExcel(Request $request)
    {
        $filePath = $request->filePath;
        $fullPath = storage_path('app/public/' . $filePath);

        if (!file_exists($fullPath)) {
            return response()->json([
                'status' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $rows = Excel::toArray([], $fullPath)[0] ?? [];

        if (empty($rows)) {
            return response()->json([
                'status' => false,
                'message' => 'Excel kosong'
            ], 400);
        }

        // =========================
        // 1. AMBIL HEADER (baris pertama)
        // =========================
        $headers = array_map(function ($h) {
            return trim($h);
        }, array_shift($rows));

        // =========================
        // 2. KONVERSI DATA JADI ASSOC
        // =========================
        $data = [];

        foreach ($rows as $row) {
            $temp = [];

            foreach ($headers as $i => $header) {
                $key = $header ?: "column_" . $i; // fallback kalau kosong

                $temp[$key] = $row[$i] ?? null;
            }

            $data[] = $temp;
        }

        return response()->json([
            'status' => true,
            'headers' => $headers,
            'data' => $data,
            'total' => count($data)
        ]);
    }
}

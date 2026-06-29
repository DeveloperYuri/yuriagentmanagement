<?php

namespace App\Http\Controllers;

use App\Models\UploadtemplateyuriModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UploadtemplateyuriControlller extends Controller
{
    public function index(Request $request)
    {
        $query = UploadtemplateyuriModel::with('user');

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('period')) {
            [$month, $year] = explode('-', $request->period);

            $query->where('month', $month)
                ->where('year', $year);
        }

        $reports = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $agents = User::role('Admin Agent')
            ->select('id', 'kode_user', 'name')
            ->orderBy('name')
            ->get();

        $periods = UploadtemplateyuriModel::select('month', 'year')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return Inertia::render('Uploadtemplateyuri/Index', [
            'reports' => $reports,
            'agents'  => $agents,
            'periods' => $periods,
            'filters' => [
                'agent_id' => $request->agent_id,
                'period'   => $request->period,
            ],
        ]);
    }

    public function store(Request $request)
    {

        // dd($request->all(), $request->file('file'));

        // dd($request->all());

        $rules = [
            'user_id' => 'required|exists:users,id',
            'month'    => 'required|integer|between:1,12',
            'year'     => 'required|integer',
            'file'     => 'required|mimes:xlsx,xls,pdf,csv,txt|max:10240',
        ];

        // Tulis pesan custom di sini
        $messages = [
            'agent_id.required' => 'Field agent harus di pilih',
            'agent_id.exists'   => 'Agent tidak terdaftar di sistem.',
            'file.required'     => 'File laporan wajib diupload ya!',
            'file.mimes'        => 'Format file harus Excel (.xlsx, .xls).',
            'file.max'          => 'File size terlalu besar, maksimal 10MB.',
            'bulan.between'     => 'Bulan tidak valid.',
        ];

        $validated = $request->validate($rules, $messages);

        // Simpan File ke storage/app/public/reports/tahun/bulan
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store(
                "reports/datatemplateyuri/{$request->year}/{$request->month}",
                'public'
            );

            UploadtemplateyuriModel::create([
                'agent_id'  => $request->user_id,
                'month'     => $request->month,
                'year'      => $request->year,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
            ]);
        }

        return redirect()->back()->with('message', 'Laporan berhasil diupload!');
    }

    public function update(Request $request, UploadtemplateyuriModel $report)
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'month'   => 'required|integer|between:1,12',
            'year'    => 'required|integer',
            'file'    => 'nullable|mimes:xlsx,xls,pdf,csv,txt|max:10240',
        ];

        $messages = [
            'user_id.required' => 'Field agent harus dipilih.',
            'user_id.exists'   => 'Agent tidak terdaftar di sistem.',
            'file.mimes'       => 'Format file harus Excel (.xlsx, .xls), PDF, CSV atau TXT.',
            'file.max'         => 'Ukuran file maksimal 10 MB.',
            'month.between'    => 'Bulan tidak valid.',
        ];

        $request->validate($rules, $messages);

        // Update data
        $report->agent_id = $request->user_id;
        $report->month    = $request->month;
        $report->year     = $request->year;

        // Jika upload file baru
        if ($request->hasFile('file')) {

            // Hapus file lama
            if ($report->file_path && Storage::disk('public')->exists($report->file_path)) {
                Storage::disk('public')->delete($report->file_path);
            }

            $file = $request->file('file');

            $path = $file->store(
                "reports/datatemplateyuri/{$request->year}/{$request->month}",
                'public'
            );

            $report->file_name = $file->getClientOriginalName();
            $report->file_path = $path;
        }

        $report->save();

        return redirect()->back()->with('message', 'Laporan berhasil diupdate!');
    }

    public function download(UploadtemplateyuriModel $report)
    {
        $path = storage_path('app/public/' . $report->file_path);

        if (!file_exists($path)) {
            abort(404, 'File tidak ditemukan');
        }

        return response()->download($path, $report->file_name);
    }

    public function destroy(UploadtemplateyuriModel $report)
    {
        // 1. Hapus file fisik dari folder storage
        if (Storage::disk('public')->exists($report->file_path)) {
            Storage::disk('public')->delete($report->file_path);
        }

        // 2. Hapus data dari database
        $report->delete();

        return redirect()->back()->with('message', 'Laporan berhasil dihapus');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AgentReport;
use App\Models\MappingReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class MappingreportController extends Controller
{
    public function index(Request $request)
    {
        // $reports = MappingReport::latest()->get();
        $reports = MappingReport::with('user')->latest()->get();

        $agents = User::select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('ReportsMapping/Index', [
            'reports' => $reports,
            'agents' => $agents,
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
            'month.between'     => 'Bulan tidak valid.',
        ];

        $validated = $request->validate($rules, $messages);

        // Simpan File ke storage/app/public/reports/tahun/bulan
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store("reports/{$request->year}/{$request->month}", 'public');

            MappingReport::create([
                'user_id'   => $request->user_id,
                'month'     => $request->month,
                'year'      => $request->year,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'status'    => 'pending',
            ]);
        }

        return redirect()->back()->with('message', 'Laporan berhasil diupload!');
    }

    public function update(Request $request, MappingReport $report)
    {
        $request->validate([
            // 'agent_id' => 'required|exists:agents,id',
            'month'    => 'required|integer|min:1|max:12',
            'year'     => 'required|integer',
            'file'     => 'nullable|mimes:xlsx,xls,pdf|max:2048',
        ]);

        // Data dasar
        $report->agent_id = $request->agent_id;
        $report->month = $request->month;
        $report->year = $request->year;

        // Jika ada upload file baru
        if ($request->hasFile('file')) {
            // Hapus file lama dari storage agar tidak memenuhi disk
            if ($report->file_path) {
                Storage::disk('public')->delete($report->file_path);
            }

            $file = $request->file('file');
            $report->file_name = $file->getClientOriginalName();
            $report->file_path = $file->store('reports', 'public');
        }

        $report->save();

        return redirect()->back();
    }

    public function download(MappingReport $report)
    {
        $path = storage_path('app/public/' . $report->file_path);

        if (!file_exists($path)) {
            abort(404, 'File tidak ditemukan');
        }

        return response()->download($path, $report->file_name);
    }

    public function destroy(MappingReport $report)
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

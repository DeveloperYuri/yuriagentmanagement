<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class AgentReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index', [
            'reports' => AgentReport::with('agent')->latest()->get(),
            'agents' => Agent::all() // Untuk pilihan dropdown saat upload
        ]);
    }

    public function store(Request $request)
    {

        // dd($request->all(), $request->file('file'));

        $rules = [
            'agent_id' => 'required|exists:agents,id',
            'month'    => 'required|integer|between:1,12',
            'year'     => 'required|integer',
            'file'     => 'required|mimes:xlsx,xls,pdf,csv,txt|max:10240',
        ];

        // Tulis pesan custom di sini
        $messages = [
            'agent_id.required' => 'Waduh, Nama Agent-nya lupa dipilih nih.',
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

            AgentReport::create([
                'agent_id'  => $request->agent_id,
                'month'     => $request->month,
                'year'      => $request->year,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'status'    => 'pending',
            ]);
        }

        return redirect()->back()->with('message', 'Laporan berhasil diupload!');
    }

    public function update(Request $request, AgentReport $report)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
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

    public function download(AgentReport $report)
    {
        $path = storage_path('app/public/' . $report->file_path);

        if (!file_exists($path)) {
            abort(404, 'File tidak ditemukan');
        }

        return response()->download($path, $report->file_name);
    }

    public function destroy(AgentReport $report)
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

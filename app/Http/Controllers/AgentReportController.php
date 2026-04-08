<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class AgentReportController extends Controller
{
    // public function index()
    // {
    //     /** @var \App\Models\User $user */
    //     $user = Auth::user();

    //     $reports = AgentReport::with(['agent', 'user'])
    //         ->latest()
    //         // Sekarang garis merah pada hasAnyRole seharusnya hilang
    //         ->when(!$user->hasAnyRole(['Administrator', 'GM']), function ($query) use ($user) {
    //             return $query->where('user_id', $user->id);
    //         })
    //         ->get();

    //     return Inertia::render('Reports/Index', [
    //         'reports' => $reports,
    //         'agents' => Agent::all(),
    //     ]);
    // }

    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Ambil nama role pertama sebagai string (Spatie Way)
        $primaryRole = $user->getRoleNames()->first();

        // 2. Mulai query
        $query = AgentReport::with(['user', 'agent.user']);

        // 3. Tambahkan filter (Pastikan string ini SAMA PERSIS dengan kolom 'name' di tabel roles)
        if ($user->hasRole('Regional Sales Manager (RSM)')) {
            $regionalId = is_array($user->regional) ? $user->regional['id'] : $user->regional->id;

            $query->where(function ($q) use ($regionalId) {
                // 1. Ambil laporan yang punya Agent di regional tersebut
                $q->whereHas('agent', function ($queryAgent) use ($regionalId) {
                    $queryAgent->where('regional_id', $regionalId);
                })
                    // 2. ATAU ambil laporan yang di-upload oleh user (Admin/Staff) di regional tersebut
                    // (Asumsi: Tabel users juga punya kolom regional_id)
                    ->orWhereHas('user', function ($queryUser) use ($regionalId) {
                        $queryUser->where('regional_id', $regionalId);
                    });
            });
        } else if ($user->hasRole('Admin Agent')) {
            $query->where('user_id', $user->id);
        }
        // --- TAMBAHAN FILTER SUPERVISOR ---
        // else if ($user->hasRole('Supervisor')) {
        //     // Filter berdasarkan relasi 'supervisors' yang ada di model User
        //     $query->whereHas('user.supervisors', function ($q) use ($user) {
        //         $q->where('users.id', $user->id);
        //     });
        // }
        else if ($user->hasRole('Supervisor')) {
            // Ambil semua ID Agent yang di-assign ke supervisor ini
            $myAgentIds = $user->agents()->pluck('users.id')->toArray();

            // Filter laporan berdasarkan user_id yang ada di daftar agent tadi
            $query->whereIn('user_id', $myAgentIds);
        }

        $reports = $query->latest()->get();
        // CEK DI SINI
        // if ($user->hasRole('Supervisor')) {
        //     dd($reports); // Kalau di sini kosong, berarti query filter supervisor-mu yang salah
        // }

        // dd($user->agents()->pluck('name'));

        $reports = $query->latest()->get();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
        ]);
    }

    public function store(Request $request)
    {

        // dd($request->all(), $request->file('file'));

        $rules = [
            // 'agent_id' => 'required|exists:agents,id',
            'month'    => 'required|integer|between:1,12',
            'year'     => 'required|integer',
            'file'     => 'required|mimes:xlsx,xls,pdf,csv,txt|max:10240',
        ];

        // Tulis pesan custom di sini
        $messages = [
            // 'agent_id.required' => 'Waduh, Nama Agent-nya lupa dipilih nih.',
            // 'agent_id.exists'   => 'Agent tidak terdaftar di sistem.',
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
                'user_id' => Auth::id(),
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

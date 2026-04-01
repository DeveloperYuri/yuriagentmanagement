<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index()
    {
        // Ambil semua data agent, urutkan dari yang terbaru
        return Inertia::render('Agents/Index', [
            'agents' => Agent::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validasi Data
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:agents,code',
            'name' => 'required|string|max:255',
        ], [
            'code.unique' => 'Kode Agent ini sudah terdaftar!',
            'code.required' => 'Kode wajib diisi.',
            'name.required' => 'Nama wajib diisi.',
        ]);

        // 2. Simpan ke Database
        Agent::create($validated);

        // 3. Redirect kembali ke index
        // Inertia akan otomatis mengirimkan flash message atau update props terbaru
        return redirect()->route('agents.index')->with('message', 'Agent berhasil ditambahkan!');
    }

    public function update(Request $request, Agent $agent)
    {
        // 1. Validasi Data
        $validated = $request->validate([
            // 'unique:agents,code,' . $agent->id 
            // Artinya: Cek unik di tabel agents kolom code, TAPI abaikan ID agent ini sendiri.
            'code' => 'required|string|max:50|unique:agents,code,' . $agent->id,
            'name' => 'required|string|max:255',
        ], [
            'code.unique' => 'Kode Agent ini sudah digunakan oleh agent lain!',
            'code.required' => 'Kode wajib diisi.',
            'name.required' => 'Nama wajib diisi.',
        ]);

        // 2. Update ke Database
        $agent->update($validated);

        // 3. Redirect kembali ke halaman index
        return redirect()->route('agents.index')->with('message', 'Data agent berhasil diperbarui!');
    }

    public function destroy(Agent $agent)
    {
        $agent->delete();

        return redirect()->back()->with('message', 'Agent berhasil dihapus');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Regional;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;


class AgentController extends Controller
{
    public function index()
    {
        return Inertia::render('Agents/Index', [
            // Mengambil user dengan relasi role (Spatie) dan regional
            'users' => User::with(['roles', 'regional', 'supervisors'])
                // 1. Filter: Hanya ambil user yang rolenya "Admin Agent"
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'Admin Agent');
                })
                // 2. Filter: Kecualikan akun yang sedang login (ID saya sendiri jangan muncul)
                ->where('id', '!=', Auth::id())
                ->latest()
                ->paginate(10)
                ->withQueryString(),

            // Data khusus untuk isi Modal Assign Supervisor
            'all_supervisors' => User::role('Supervisor')->get(['id', 'name', 'email']),

            // Daftar role untuk dropdown (Tetap Sama)
            'roles' => Role::all()->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ]),

            // Daftar regional untuk dropdown (Tetap Sama)
            'regionals' => Regional::all()->map(fn($reg) => [
                'id' => $reg->id,
                'name' => $reg->name,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        // 1. Validasi Data
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:agents,code',
            'name' => 'required|string|max:255',
            'regional_id' => 'required|exists:regionals,id', // Tambahkan ini
        ], [
            'code.unique' => 'Kode Agent ini sudah terdaftar!',
            'code.required' => 'Kode wajib diisi.',
            'name.required' => 'Nama wajib diisi.',
            'regional_id.required' => 'Regional wajib dipilih.', // Custom message
            'regional_id.exists' => 'Regional yang dipilih tidak valid.', // Custom message
        ]);

        // 2. Simpan ke Database
        Agent::create($validated);

        // 3. Redirect kembali ke index
        // Inertia akan otomatis mengirimkan flash message atau update props terbaru
        return redirect()->route('agents.index')->with('message', 'Agent berhasil ditambahkan!');
    }

    // Ubah dari (Request $request, User $user) menjadi:
    public function update(Request $request, User $agent)
    {
        // Sekarang $agent->id TIDAK AKAN KOSONG lagi
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $agent->id,
            'role'  => 'required',
            'code'  => 'nullable|unique:users,code,' . $agent->id,
            'regional_id' => 'nullable',
            'password'    => 'nullable|min:8|confirmed',
        ]);

        // Update menggunakan variabel $agent
        $agent->name = $validated['name'];
        $agent->email = $validated['email'];
        $agent->role = $validated['role'];
        $agent->code = $validated['code'];
        $agent->regional_id = $validated['regional_id'];

        if ($request->filled('password')) {
            $agent->password = Hash::make($validated['password']);
        }

        $agent->save();

        return redirect()->back()->with('message', 'User berhasil diperbarui!');
    }

    // public function update(Request $request, Agent $agent)
    // {
    //     // 1. Validasi Data
    //     $validated = $request->validate([
    //         // 'unique:agents,code,' . $agent->id 
    //         // Artinya: Cek unik di tabel agents kolom code, TAPI abaikan ID agent ini sendiri.
    //         'code' => 'required|string|max:50|unique:agents,code,' . $agent->id,
    //         'name' => 'required|string|max:255',
    //     ], [
    //         'code.unique' => 'Kode Agent ini sudah digunakan oleh agent lain!',
    //         'code.required' => 'Kode wajib diisi.',
    //         'name.required' => 'Nama wajib diisi.',
    //     ]);

    //     // 2. Update ke Database
    //     $agent->update($validated);

    //     // 3. Redirect kembali ke halaman index
    //     return redirect()->route('agents.index')->with('message', 'Data agent berhasil diperbarui!');
    // }

    public function destroy(Agent $agent)
    {
        $agent->delete();

        return redirect()->back()->with('message', 'Agent berhasil dihapus');
    }

    // Sesuaikan nama method dengan yang ada di Route web.php
    public function updateSupervisors(Request $request, $id)
    {
        $agent = Agent::findOrFail($id);
        $agent->supervisors()->sync($request->supervisor_ids);

        return back();
    }
}

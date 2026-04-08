<?php

namespace App\Http\Controllers;

use App\Models\Regional;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            // Mengambil user dengan relasi role (Spatie) dan regional
            'users' => User::with(['roles', 'regional'])
                ->latest()
                ->paginate(10)
                ->withQueryString(),

            // Mengambil daftar role untuk dropdown di modal
            'roles' => Role::all()->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ]),

            // Mengambil daftar regional untuk dropdown di modal
            'regionals' => Regional::all()->map(fn($reg) => [
                'id' => $reg->id,
                'name' => $reg->name,
            ]),
        ]);
    }

    public function store(Request $request)
    {

        // dd($request->all());
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name',
            'regional_id' => 'nullable|exists:regionals,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'regional_id' => $request->regional_id,
        ]);

        // Assign role menggunakan Spatie
        $user->assignRole($request->role);

        return redirect()->back();
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|exists:roles,name',
            'regional_id' => 'nullable|exists:regionals,id',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'regional_id' => $request->regional_id,
        ]);

        // Update password hanya jika diisi
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Sinkronisasi role (menghapus role lama, ganti dengan yang baru)
        $user->syncRoles($request->role);

        return redirect()->back();
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->back();
    }

    public function assignSupervisor(Request $request, User $user)
    {
        $request->validate([
            'supervisor_ids' => 'array',
            'supervisor_ids.*' => 'exists:users,id',
        ]);

        // $user di sini adalah Agent yang dipilih
        // sync akan menghapus relasi lama dan memasukkan yang baru dari array
        $user->supervisors()->sync($request->supervisor_ids);

        return back()->with('message', 'Supervisor berhasil diupdate!');
    }
}

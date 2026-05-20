<?php

namespace App\Http\Controllers;

use App\Models\MappingProduk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MappingProdukController extends Controller
{
    public function index(Request $request)
    {
        $masters = DB::table('items')
            ->select('item_name')
            ->orderBy('item_name')
            ->get();

        $aliases = DB::table('item_aliases')
            ->when($request->search, function ($query, $search) {
                $query->where('agent_name', 'ILIKE', "%{$search}%")
                    ->orWhere('master_name', 'ILIKE', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('MappingProduk/Index', [
            'masters' => $masters,
            'aliases' => $aliases,
        ]);
    }

    // function lama kalo udah stabil jgn lupa di hapus ya
    // public function index()
    // {
    //     $masters = DB::table('items')->get();
    //     $aliases = DB::table('item_aliases')
    //         ->orderBy('id', 'desc')
    //         ->paginate(10); //
    //     // $aliases = DB::table('item_aliases')->get();

    //     return Inertia::render('MappingProduk/Index', [
    //         'masters' => $masters,
    //         'aliases' => $aliases,
    //     ]);
    // }

    public function store(Request $request)
    {
        // dd($request->all());

        $request->validate([
            'agent_name' => 'required|string',
            'master_name' => 'required|string',
        ]);

        // normalize (HARUS sama kayak Python)
        $clean = strtolower($request->agent_name);
        $clean = preg_replace('/[^a-z0-9 ]/', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        // CEK DUPLIKAT
        $exists = DB::table('item_aliases')
            ->where('clean_name', $clean)
            ->first();

        if ($exists) {
            return back()->with('error', 'Mapping sudah ada!');
        }

        // ambil item_code dari master (opsional tapi bagus)
        $master = DB::table('items')
            ->where('item_name', $request->master_name)
            ->first();

        DB::table('item_aliases')->insert([
            'agent_name' => $request->agent_name,
            'clean_name' => $clean,
            'master_name' => $request->master_name,
            'item_code' => $master->item_code ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Mapping berhasil disimpan!');
    }

    public function update(Request $request, $id)
    {
        $mapping = MappingProduk::findOrFail($id);

        $mapping->update([
            'agent_name' => $request->agent_name,
            'master_name' => $request->master_name,
        ]);

        return back()->with('success', 'Mapping produk berhasil diupdate!');
    }

    public function destroy($id)
    {
        $mapping = MappingProduk::findOrFail($id);

        $mapping->delete();

        return back()->with('success', 'Mapping produk berhasil di delete!');
    }
}

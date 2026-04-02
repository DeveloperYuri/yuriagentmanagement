<?php

namespace App\Http\Controllers;

use App\Models\Regional;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegionalController extends Controller
{
    public function index()
    {
        return Inertia::render('Regional/Index', [
            'regional' => Regional::latest()->get()
        ]);
    }

    /**
     * Simpan data regional baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:regionals,name',
        ]);

        Regional::create($validated);

        return redirect()->back();
    }

    /**
     * Update data regional yang sudah ada.
     */
    public function update(Request $request, Regional $regional)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:regionals,name,' . $regional->id,
        ]);

        $regional->update($validated);

        return redirect()->back();
    }

    /**
     * Hapus data regional.
     */
    public function destroy(Regional $regional)
    {
        $regional->delete();

        return redirect()->back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ItemGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemGroupController extends Controller
{
    public function index()
    {
        return Inertia::render('ItemsGroups/Index', [
            'groups' => ItemGroup::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:items_groups,name'
        ]);

        ItemGroup::create($request->all());

        return back()->with('success', 'Group berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $group = ItemGroup::findOrFail($id);

        $request->validate([
            'name' => 'required|unique:items_groups,name,' . $id
        ]);

        $group->update($request->all());

        return back()->with('success', 'Group berhasil diupdate');
    }

    public function destroy($id)
    {
        ItemGroup::findOrFail($id)->delete();

        return back()->with('success', 'Group berhasil dihapus');
    }
}

<?php

namespace App\Http\Controllers;

use App\Imports\ItemsImport;
use App\Models\Item;
use App\Models\ItemGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::latest()->paginate(10);

        return Inertia::render('Items/Index', [
            'items' => $items, // ✅ WAJIB INI
            'groups' => ItemGroup::select('id', 'name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Items/Form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_code' => 'required|unique:items,item_code',
            'item_name' => 'required',
            'item_per_box' => 'required|integer',
            'item_group' => 'nullable',
        ]);

        Item::create($request->all());

        return redirect()->route('items.index')
            ->with('success', 'Item berhasil ditambahkan');
    }

    public function edit($id)
    {
        return Inertia::render('Items/Form', [
            'item' => Item::findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $request->validate([
            'item_code' => 'required|unique:items,item_code,' . $id,
            'item_name' => 'required',
            'item_per_box' => 'required|integer',
            'item_group' => 'nullable',
        ]);

        $item->update($request->all());

        return redirect()->route('items.index')
            ->with('success', 'Item berhasil diupdate');
    }

    public function destroy($id)
    {
        Item::findOrFail($id)->delete();

        return redirect()->route('items.index')
            ->with('success', 'Item berhasil dihapus');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        Excel::import(new ItemsImport, $request->file('file'));

        return back()->with('success', 'Import berhasil!');
    }
}

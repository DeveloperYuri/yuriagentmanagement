<?php

namespace App\Http\Controllers;

use App\Models\Customeritemminstocks;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomeritemminstocksController extends Controller
{
    public function index(Request $request)
    {
        $query = Customeritemminstocks::query()
            ->with([
                'user:id,name,kode_user',
                'item:id,item_code,item_name'
            ]);

        // SEARCH
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->whereHas('user', function ($userQuery) use ($search) {

                    $userQuery->where(
                        'name',
                        'ilike',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'kode_user',
                            'ilike',
                            "%{$search}%"
                        );
                })
                    ->orWhereHas('item', function ($itemQuery) use ($search) {

                        $itemQuery->where(
                            'item_name',
                            'ilike',
                            "%{$search}%"
                        )
                            ->orWhere(
                                'item_code',
                                'ilike',
                                "%{$search}%"
                            );
                    });
            });
        }

        // TABLE DATA
        $minimumStocks = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // AUTOCOMPLETE CUSTOMER
        $users = User::select(
            'id',
            'name',
            'kode_user'
        )
            ->whereNotNull('kode_user')
            ->orderBy('name')
            ->get();

        // AUTOCOMPLETE ITEM
        $items = Item::select(
            'id',
            'item_code',
            'item_name'
        )
            ->orderBy('item_name')
            ->get();

        return Inertia::render(
            'CustomerItemMinStocks/Index',
            [
                'minimumStocks' => $minimumStocks,

                'users' => $users,

                'items' => $items,

                'filters' => [
                    'search' => $request->search,
                ],
            ]
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'item_id' => 'required|exists:items,id',

            'minimum_stock' => 'required|integer|min:0',
        ]);

        Customeritemminstocks::create([
            'user_id' => $request->user_id,

            'item_id' => $request->item_id,

            'minimum_stock' => $request->minimum_stock,
        ]);

        return redirect()
            ->route('customer-item-min-stocks.index')
            ->with(
                'success',
                'Minimum stock berhasil ditambahkan'
            );
    }

    // public function edit($id)
    // {
    //     return Inertia::render('Items/Form', [
    //         'item' => Item::findOrFail($id)
    //     ]);
    // }

    // public function update(Request $request, $id)
    // {
    //     $item = Item::findOrFail($id);

    //     $request->validate([
    //         'item_code' => 'required|unique:items,item_code,' . $id,
    //         'item_name' => 'required',
    //         'item_per_box' => 'required|integer',
    //         'item_group' => 'nullable',
    //     ]);

    //     $item->update($request->all());

    //     return redirect()->route('items.index')
    //         ->with('success', 'Item berhasil diupdate');
    // }

    public function destroy($id)
    {
        Customeritemminstocks::findOrFail($id)->delete();

        return redirect()
            ->route('customer-item-min-stocks.index')
            ->with(
                'success',
                'Data berhasil dihapus'
            );
    }

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:xlsx,xls'
    //     ]);

    //     Excel::import(new ItemsImport, $request->file('file'));

    //     return back()->with('success', 'Import berhasil!');
    // }
}

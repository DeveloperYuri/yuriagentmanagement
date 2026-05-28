<?php

namespace App\Http\Controllers;

use App\Imports\AgentItemMappingImport;
use App\Models\AgentItemMapping;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AgentItemMappingController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentItemMapping::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('agent_sku', 'ilike', '%' . $request->search . '%')
                    ->orWhere('item_code', 'ilike', '%' . $request->search . '%')
                    ->orWhere('item_name', 'ilike', '%' . $request->search . '%');
            });
        }

        // MASTER ITEMS
        $masters = Item::query()
            ->select([
                'item_code',
                'item_name',
                'item_per_box',
                'item_group',
            ])
            ->orderBy('item_name')
            ->get();

        $mappings = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('AgentItemMappings/Index', [

            'mappings' => $mappings,

            'masters' => $masters,

            'filters' => [
                'search' => $request->search,
            ]

        ]);
    }

    public function create()
    {
        return Inertia::render('AgentItemMappings/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_sku' => 'required|string|max:255|unique:agent_item_mappings,agent_sku',
            'item_code' => 'required|string|max:255',
            'item_name' => 'nullable|string',
            'item_per_box' => 'nullable|integer',
            'item_group' => 'nullable|string|max:255',
        ]);

        AgentItemMapping::create($validated);

        return redirect()
            ->route('agent-item-mappings.index')
            ->with('success', 'Mapping berhasil ditambahkan');
    }

    public function edit(AgentItemMapping $agentItemMapping)
    {
        return Inertia::render('AgentItemMappings/Edit', [
            'mapping' => $agentItemMapping
        ]);
    }

    public function update(Request $request, AgentItemMapping $agentItemMapping)
    {
        $validated = $request->validate([
            'agent_sku' => 'required|string|max:255|unique:agent_item_mappings,agent_sku,' . $agentItemMapping->id,
            'item_code' => 'required|string|max:255',
            'item_name' => 'nullable|string',
            'item_per_box' => 'nullable|integer',
            'item_group' => 'nullable|string|max:255',
        ]);

        $agentItemMapping->update($validated);

        return redirect()
            ->route('agent-item-mappings.index')
            ->with('success', 'Mapping berhasil diupdate');
    }

    public function destroy(AgentItemMapping $agentItemMapping)
    {
        $agentItemMapping->delete();

        return redirect()
            ->route('agent-item-mappings.index')
            ->with('success', 'Mapping berhasil dihapus');
    }

    public function import(Request $request)
    {
        try {

            $request->validate([
                'file' => 'required|mimes:xlsx,xls'
            ]);

            Excel::import(
                new AgentItemMappingImport(),
                $request->file('file')
            );

            return response()->json([
                'success' => true,
                'message' => 'Import berhasil'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

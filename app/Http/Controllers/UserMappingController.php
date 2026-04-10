<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMapping;
use Illuminate\Http\Request;

class UserMappingController extends Controller
{
    // public function index($userId)
    // {
    //     $this->authorize('adminOnly'); // pastikan pakai Gate / Policy
    //     $user = User::findOrFail($userId);
    //     $mappings = UserMapping::where('user_id', $userId)->get();

    //     return view('mapping.index', compact('user', 'mappings'));
    // }

    // public function store(Request $request)
    // {
    //     $this->authorize('adminOnly');

    //     $request->validate([
    //         'user_id' => 'required|exists:users,id',
    //         'db_column' => 'required|string',
    //         'excel_column' => 'required|string',
    //     ]);

    //     UserMapping::updateOrCreate(
    //         [
    //             'user_id' => $request->user_id,
    //             'db_column' => $request->db_column
    //         ],
    //         [
    //             'excel_column' => $request->excel_column
    //         ]
    //     );

    //     return redirect()->back()->with('success', 'Mapping berhasil disimpan');
    // }
}

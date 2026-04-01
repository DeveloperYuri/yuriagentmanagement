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
}

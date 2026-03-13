<?php

namespace App\Http\Controllers;

use App\Models\Agency;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::active()
            ->withCount('activeTours')
            ->orderBy('name')
            ->get();

        return view('agencies.index', compact('agencies'));
    }

    public function show(Agency $agency)
    {
        $agency->load(['activeTours' => fn($q) => $q->orderBy('price')]);

        return view('agencies.show', compact('agency'));
    }
}

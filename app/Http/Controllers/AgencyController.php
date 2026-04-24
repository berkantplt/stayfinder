<?php

namespace App\Http\Controllers;

use App\Models\Agency;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::active()
            ->withCount([
                'tours as active_tours_count' => fn($query) => $query->active(),
            ])
            ->orderBy('name')
            ->get();

        return view('agencies.index', compact('agencies'));
    }

    public function show(Agency $agency)
    {
        abort_unless($agency->is_active, 404);

        $agency->setRelation(
            'activeTours',
            $agency->tours()
                ->active()
                ->orderBy('price')
                ->get()
        );

        return view('agencies.show', compact('agency'));
    }
}

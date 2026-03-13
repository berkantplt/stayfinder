<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Tour;

class DestinationController extends Controller
{
    public function show(Destination $destination)
    {
        $tours = Tour::active()
            ->where('destination', 'like', '%' . $destination->name . '%')
            ->with('agency')
            ->orderBy('price')
            ->get();

        $minPrice   = $tours->min('price');
        $tourCount  = $tours->count();
        $agencies   = $tours->pluck('agency')->unique('id')->values();
        $avgPrice   = $tours->avg('price');

        return view('destinations.show', compact(
            'destination', 'tours', 'minPrice', 'tourCount', 'agencies', 'avgPrice'
        ));
    }
}

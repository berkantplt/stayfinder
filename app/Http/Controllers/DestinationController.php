<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Tour;
use App\Support\DestinationFilter;

class DestinationController extends Controller
{
    public function show(Destination $destination)
    {
        // Kelime sınırlı: naif LIKE'ta "Kos" destinasyonu "Kosova" turlarını da
        // listeliyordu. Ana sayfa kart sayacıyla artık aynı mantık.
        $tours = DestinationFilter::apply(Tour::active(), $destination->name)
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

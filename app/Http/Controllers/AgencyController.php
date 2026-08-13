<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;

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

    public function show(Request $request, Agency $agency)
    {
        abort_unless($agency->is_active, 404);

        // SEO: kanonik adres slug. Eski /acentalar/{id} bağlantıları 301 ile taşınır.
        $routeValue = (string) $request->route()?->originalParameter('agency', '');
        if (! empty($agency->slug) && $routeValue !== $agency->slug) {
            return redirect()->route('agencies.show', $agency, 301);
        }

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

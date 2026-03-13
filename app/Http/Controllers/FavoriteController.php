<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Tour $tour)
    {
        $user = auth()->user();

        if ($user->favoriteTours()->where('tour_id', $tour->id)->exists()) {
            $user->favoriteTours()->detach($tour->id);
            $message = 'Favorilerden çıkarıldı.';
        } else {
            $user->favoriteTours()->attach($tour->id);
            $message = 'Favorilere eklendi!';
        }

        return back()->with('success', $message);
    }

    public function index()
    {
        $favorites = auth()->user()
            ->favoriteTours()
            ->with('agency')
            ->active()
            ->latest('favorites.created_at')
            ->get();

        return view('favorites.index', compact('favorites'));
    }
}

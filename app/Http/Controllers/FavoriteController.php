<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Tour $tour)
    {
        abort_unless($tour->isPubliclyVisible() && $tour->agency?->is_active, 404);

        $user = auth()->user();

        if ($user->favoriteTours()->where('tour_id', $tour->id)->exists()) {
            $user->favoriteTours()->detach($tour->id);
            $message = 'Favorilerden çıkarıldı.';
        } else {
            // Eklendiği andaki fiyat + birim: favoriler sayfası "eklediğinden beri %N düştü" der
            $user->favoriteTours()->attach($tour->id, ['price_at_save' => $tour->price, 'currency_at_save' => $tour->currency]);
            $message = 'Favorilere eklendi!';
        }

        // Kart üstündeki kalp AJAX ile çağırır: yönlendirme yerine durum döner
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'favorited' => $user->favoriteTours()->where('tour_id', $tour->id)->exists(),
                'message' => $message,
            ]);
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

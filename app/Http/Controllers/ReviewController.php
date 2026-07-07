<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        abort_unless($tour->isPubliclyVisible() && $tour->agency?->is_active, 404);

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        // Check if already reviewed
        $existing = Review::where('user_id', auth()->id())
            ->where('tour_id', $tour->id)
            ->first();

        if ($existing) {
            return back()->withErrors(['comment' => 'Bu tura zaten yorum yaptınız.']);
        }

        Review::create([
            'user_id' => auth()->id(),
            'tour_id' => $tour->id,
            'rating'  => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return back()->withFragment('yorumlar')->with('success', 'Yorumunuz eklendi, teşekkürler!');
    }

    public function destroy(Review $review)
    {
        if ($review->user_id !== auth()->id()) abort(403);
        $tour = $review->tour;
        $review->delete();
        return redirect()->route('tours.show', $tour)->withFragment('yorumlar')->with('success', 'Yorum silindi.');
    }
}

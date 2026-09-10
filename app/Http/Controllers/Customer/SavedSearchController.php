<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\Request;

/** Kayıtlı aramalar: /turlar'dan kaydet, listele, sil. */
class SavedSearchController extends Controller
{
    public function index()
    {
        $searches = auth()->user()->savedSearches()->latest()->get();

        return view('customer.saved-searches.index', compact('searches'));
    }

    public function store(Request $request)
    {
        $params = SavedSearch::paramsFrom((array) $request->input('params', []));

        if ($params === []) {
            return back()->withErrors(['params' => 'Kaydedilecek bir filtre yok; önce bir seçim yap.']);
        }

        $user = auth()->user();
        $mevcut = $user->savedSearches()->get()->first(fn (SavedSearch $s) => $s->params === $params);
        if ($mevcut) {
            return back()->with('success', 'Bu arama zaten kayıtlı: '.$mevcut->name);
        }

        if ($user->savedSearches()->count() >= 20) {
            return back()->withErrors(['params' => 'En fazla 20 kayıtlı arama tutulabilir; önce birini sil.']);
        }

        $search = $user->savedSearches()->create([
            'name' => SavedSearch::nameFor($params),
            'params' => $params,
            'last_checked_at' => now(),
        ]);

        return back()->with('success', 'Arama kaydedildi: '.$search->name.'. Uyan yeni tur eklenince bildirim alacaksın.');
    }

    public function destroy(SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === auth()->id(), 403);
        $savedSearch->delete();

        return redirect()->route('customer.saved-searches.index')->with('success', 'Kayıtlı arama silindi.');
    }
}

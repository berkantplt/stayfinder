<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\TourView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user()->load([]);

        $favorites = $user->favoriteTours()->with('agency')->active()->latest('favorites.created_at')->get();
        $reviews = Review::where('user_id', $user->id)->with('tour.agency')->latest()->get();
        $viewCount = TourView::where('user_id', $user->id)->count();

        return view('profile.show', compact('user', 'favorites', 'reviews', 'viewCount'));
    }

    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:500',
            'birth_date' => 'nullable|date|before:today',
            'avatar_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
        ]);

        if ($request->boolean('remove_avatar')) {
            if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = null;
        } elseif ($request->hasFile('avatar_file')) {
            if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar_file')->store('avatars', 'public');
        }

        unset($validated['avatar_file'], $validated['remove_avatar']);

        $user->update($validated);

        return redirect()->route('profile.show')->with('success', 'Profiliniz güncellendi!');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Mevcut şifre hatalı.']);
        }

        auth()->user()->update(['password' => Hash::make($request->password)]);

        return redirect()->route('profile.show')->with('success', 'Şifreniz güncellendi!');
    }
}

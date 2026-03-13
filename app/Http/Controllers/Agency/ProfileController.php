<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit()
    {
        $agency = auth()->user()->agency;
        return view('agency.profile', compact('agency'));
    }

    public function update(Request $request)
    {
        $agency = auth()->user()->agency;

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:30',
            'email'       => 'nullable|email|max:255',
            'website_url' => 'nullable|url|max:500',
            'address'     => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'logo'        => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            if ($agency->logo && str_starts_with($agency->logo, '/storage/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $agency->logo));
            }
            $validated['logo'] = '/storage/' . $request->file('logo')->store('agencies', 'public');
        } else {
            unset($validated['logo']);
        }

        $agency->update($validated);

        return back()->with('success', 'Profil güncellendi.');
    }
}

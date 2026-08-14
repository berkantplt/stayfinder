<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('sort_order')->get();
        return view('admin.banners', compact('banners'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'image'      => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
            'blur'       => 'nullable|integer|min:0|max:20',
            'darkness'   => 'nullable|integer|min:0|max:100',
            'white_veil' => 'nullable|integer|min:0|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $path = $request->file('image')->store('banners', 'public');

        Banner::create([
            'title'      => $validated['title'],
            'image'      => $path,
            'blur'       => $validated['blur'] ?? 0,
            'darkness'   => $validated['darkness'] ?? 40,
            'white_veil' => $validated['white_veil'] ?? 100,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner eklendi.');
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
            'blur'       => 'nullable|integer|min:0|max:20',
            'darkness'   => 'nullable|integer|min:0|max:100',
            'white_veil' => 'nullable|integer|min:0|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = $request->file('image')->store('banners', 'public');
        } else {
            unset($validated['image']);
        }

        $banner->update($validated);

        return redirect()->route('admin.banners.index')
            ->with('success', $banner->title . ' güncellendi.');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        return redirect()->route('admin.banners.index')
            ->with('success', $banner->title . ($banner->is_active ? ' aktifleştirildi.' : ' pasifleştirildi.'));
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }
        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner silindi.');
    }
}

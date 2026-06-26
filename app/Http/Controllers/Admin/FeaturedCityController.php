<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeaturedCity;
use App\Models\FeaturedCityImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeaturedCityController extends Controller
{
    public function index()
    {
        $cities = FeaturedCity::with('images')->orderBy('sort_order')->get();

        return view('admin.featured_cities.index', compact('cities'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
        ]);

        FeaturedCity::create($validated);

        return redirect()->route('admin.featured_cities.index')
            ->with('success', 'Şehir eklendi. Şimdi görsellerini ekleyebilirsiniz.');
    }

    public function update(Request $request, FeaturedCity $city)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $city->update($validated);

        return redirect()->route('admin.featured_cities.index')
            ->with('success', 'Şehir güncellendi.');
    }

    public function destroy(FeaturedCity $city)
    {
        // Delete all images first
        foreach ($city->images as $image) {
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }
        $city->delete();

        return redirect()->route('admin.featured_cities.index')
            ->with('success', 'Şehir ve tüm görselleri silindi.');
    }

    public function addImage(Request $request, FeaturedCity $city)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
        ]);

        $path = $request->file('image')->store('featured_cities', 'public');

        $city->images()->create([
            'image_path' => $path,
            'sort_order' => $city->images()->count(),
        ]);

        return back()->with('success', 'Görsel eklendi.');
    }

    public function destroyImage(FeaturedCityImage $image)
    {
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }
        $image->delete();

        return back()->with('success', 'Görsel silindi.');
    }
}

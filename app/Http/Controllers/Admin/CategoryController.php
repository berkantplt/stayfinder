<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parent', 'children')->orderBy('sort_order')->get();
        $parentCategories = Category::parents()->orderBy('name')->get();

        return view('admin.categories.index', compact('categories', 'parentCategories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'icon'        => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori oluşturuldu.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'icon'        => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori güncellendi.');
    }

    public function destroy(Category $category)
    {
        if ($category->children()->count() > 0 || $category->tours()->count() > 0) {
            return redirect()->route('admin.categories.index')
                ->withErrors('Bu kategoriye bağlı alt kategoriler veya turlar olduğu için silinemez.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Kategori silindi.');
    }

    public function toggle(Category $category)
    {
        $category->update(['is_active' => !$category->is_active]);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori durumu güncellendi.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        $posts = Post::latest()->paginate(15);
        return view('admin.blog.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.blog.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'content'          => 'required|string',
            'excerpt'          => 'nullable|string|max:500',
            'image'            => 'nullable|url',
            'category'         => 'required|string|max:100',
            'meta_description' => 'nullable|string|max:160',
            'is_published'     => 'nullable',
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        Post::create($validated);

        return redirect()->route('admin.blog.index')->with('success', 'Yazı oluşturuldu.');
    }

    public function edit(Post $post)
    {
        return view('admin.blog.form', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'content'          => 'required|string',
            'excerpt'          => 'nullable|string|max:500',
            'image'            => 'nullable|url',
            'category'         => 'required|string|max:100',
            'meta_description' => 'nullable|string|max:160',
            'is_published'     => 'nullable',
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        $post->update($validated);

        return redirect()->route('admin.blog.index')->with('success', 'Yazı güncellendi.');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect()->route('admin.blog.index')->with('success', 'Yazı silindi.');
    }
}

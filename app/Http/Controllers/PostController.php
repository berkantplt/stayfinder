<?php

namespace App\Http\Controllers;

use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::published()->paginate(9);
        $categories = Post::published()->distinct()->pluck('category');
        return view('blog.index', compact('posts', 'categories'));
    }

    public function show(Post $post)
    {
        if (!$post->is_published) abort(404);
        $related = Post::published()
            ->where('category', $post->category)
            ->where('id', '!=', $post->id)
            ->limit(3)
            ->get();
        return view('blog.show', compact('post', 'related'));
    }
}

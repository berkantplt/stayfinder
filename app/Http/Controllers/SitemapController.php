<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Post;
use App\Models\Destination;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = cache()->remember('sitemap_xml', now()->addHours(6), function () {
            $tours = Tour::active()
                ->whereHas('agency', fn($agencyQuery) => $agencyQuery->active())
                ->get();
            $posts = Post::latest()->get();
            $destinations = Destination::all();

            return view('sitemap', [
                'tours' => $tours,
                'posts' => $posts,
                'destinations' => $destinations,
            ])->render();
        });

        return response($xml)->header('Content-Type', 'text/xml');
    }
}

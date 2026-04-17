<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'agencies'    => Agency::count(),
            'tours'       => Tour::count(),
            'activeTours' => Tour::active()->count(),
            'clicks'      => \App\Models\TourClick::count(),
            'views'       => \App\Models\TourView::count(),
        ];

        $agencies = Agency::withCount('tours')->orderByDesc('tours_count')->get();

        // Daily clicks + views for 30 days
        $dailyClicks = \App\Models\TourClick::where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')->pluck('total', 'date');

        $dailyViews = \App\Models\TourView::where('viewed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')->pluck('total', 'date');

        $chartLabels = $clickData = $viewData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('d M');
            $clickData[]   = $dailyClicks[$d] ?? 0;
            $viewData[]    = $dailyViews[$d] ?? 0;
        }

        // Top destinations by tour count
        $topDestinations = Tour::active()
            ->selectRaw('destination, COUNT(*) as total')
            ->groupBy('destination')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'destination');

        return view('admin.dashboard', compact(
            'stats', 'agencies', 'chartLabels', 'clickData', 'viewData', 'topDestinations'
        ));
    }

    public function agencies(Request $request)
    {
        $query = Agency::withCount('tours');

        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'like', '%' . $searchTerm . '%');
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'passive') {
                $query->where('is_active', false);
            }
        }

        $agencies = $query->orderBy('name')->paginate(15)->withQueryString();
        return view('admin.agencies', compact('agencies'));
    }

    public function createAgency()
    {
        return view('admin.agency-create');
    }

    public function storeAgency(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string',
            'email'       => 'nullable|email',
            'website_url' => 'nullable|url',
            'description' => 'nullable|string',
        ]);

        $agency = Agency::create($validated);

        User::create([
            'name'      => $agency->name . ' Yönetici',
            'email'     => 'admin@' . strtolower(str_replace(' ', '', $agency->name)) . '.com',
            'password'  => Hash::make('password'),
            'role'      => 'agency',
            'agency_id' => $agency->id,
        ]);

        return redirect()->route('admin.agencies')
            ->with('success', 'Acenta oluşturuldu.');
    }

    public function toggleAgency(Agency $agency)
    {
        $agency->update(['is_active' => !$agency->is_active]);

        return redirect()->route('admin.agencies')
            ->with('success', $agency->name . ' ' . ($agency->is_active ? 'aktifleştirildi' : 'pasifleştirildi') . '.');
    }

    public function tours(Request $request)
    {
        $query = Tour::with('agency', 'dates'); // Eager load dates as well

        // 1. Arama (q) - Başlık veya destinasyon
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        // 2. Durum (status)
        if ($request->filled('status') && $request->status !== 'all') {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        // 3. Acenta (agency_id)
        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        // 4. Destinasyon (destination)
        if ($request->filled('destination')) {
            $query->where('destination', $request->destination);
        }

        // 5. Tarih Aralığı (date_start, date_end)
        if ($request->filled('date_start')) {
            $query->where(function ($q) use ($request) {
                $q->where('departure_date', '>=', $request->date_start)
                  ->orWhereHas('dates', fn($dq) => $dq->where('departure_date', '>=', $request->date_start));
            });
        }
        if ($request->filled('date_end')) {
            $query->where(function ($q) use ($request) {
                $q->where('departure_date', '<=', $request->date_end)
                  ->orWhereHas('dates', fn($dq) => $dq->where('departure_date', '<=', $request->date_end));
            });
        }

        // 6. Tur Süresi (min_days, max_days)
        if ($request->filled('min_days')) {
            $query->where('duration_days', '>=', $request->min_days);
        }
        if ($request->filled('max_days')) {
            $query->where('duration_days', '<=', $request->max_days);
        }

        // 7. Sıralama (sort)
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'date'       => $query->orderBy('departure_date'),
            default      => $query->orderByDesc('created_at'),
        };

        $tours = $query->paginate(15)->withQueryString();

        // Dropdown data
        $agencies = Agency::orderBy('name')->get();
        $destinations = Tour::select('destination')->distinct()->whereNotNull('destination')->orderBy('destination')->pluck('destination');

        return view('admin.tours', compact('tours', 'agencies', 'destinations'));
    }

    // --- Destination Management ---

    public function destinations()
    {
        $destinations = Destination::orderBy('sort_order')->get();
        return view('admin.destinations', compact('destinations'));
    }

    public function updateDestination(Request $request, Destination $destination)
    {
        $validated = $request->validate([
            'image'      => 'nullable|url',
            'sort_order' => 'nullable|integer',
        ]);

        $destination->update($validated);

        return redirect()->route('admin.destinations')
            ->with('success', $destination->name . ' güncellendi.');
    }

    public function toggleDestination(Destination $destination)
    {
        $destination->update(['is_active' => !$destination->is_active]);

        return redirect()->route('admin.destinations')
            ->with('success', $destination->name . ' ' . ($destination->is_active ? 'aktifleştirildi' : 'pasifleştirildi') . '.');
    }
}

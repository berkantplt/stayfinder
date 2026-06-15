<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\CategoryRequest;
use Illuminate\Http\Request;

class CategoryRequestController extends Controller
{
    public function index()
    {
        $agency = $this->currentAgency();

        $requests = CategoryRequest::where('agency_id', $agency->id)
            ->with(['parent', 'createdCategory'])
            ->latest()
            ->get();

        return view('agency.category-requests.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $agency = $this->currentAgency();

        $validated = $request->validate([
            'requested_name' => 'required|string|max:100',
            'note' => 'nullable|string|max:1000',
        ]);

        $name = trim($validated['requested_name']);

        // Aynı isimde bekleyen talep varsa tekrar açtırma
        $duplicate = CategoryRequest::where('agency_id', $agency->id)
            ->where('status', CategoryRequest::STATUS_PENDING)
            ->whereRaw('LOWER(requested_name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return back()->withErrors('Bu isimde bekleyen bir talebiniz zaten var.');
        }

        CategoryRequest::create([
            'agency_id' => $agency->id,
            'requested_name' => $name,
            'note' => $validated['note'] ?? null,
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Kategori talebiniz alındı. Admin onayından sonra turlarınızda kullanabileceksiniz.');
    }

    private function currentAgency(): Agency
    {
        $agency = auth()->user()?->agency;

        abort_unless($agency instanceof Agency, 403, 'Acenta kaydı bulunamadı.');

        return $agency;
    }
}

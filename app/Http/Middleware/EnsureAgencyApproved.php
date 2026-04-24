<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAgencyApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isAgency()) {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        if (!$user->agency || !$user->agency->isApproved()) {
            return redirect()->route('agency.application.status');
        }

        return $next($request);
    }
}

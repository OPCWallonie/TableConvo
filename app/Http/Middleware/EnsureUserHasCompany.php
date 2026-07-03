<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('status', 'admin_no_purchase');
        }

        if ($user && ! $user->company) {
            return redirect()->route('espace.profil')
                ->with('status', 'company_missing');
        }

        return $next($request);
    }
}

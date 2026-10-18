<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFeature
{
    public function handle(Request $request, Closure $next, string ...$features)
    {
        $tenant = tenant();
        foreach ($features as $feature) {
            if (! $tenant || ! $tenant->hasFeature($feature)) {
                return response()->view('app.feature-locked', ['feature' => $feature], 403);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * This app is mirrored under a handful of domains, each showing its own name/logo instead of
 * the built-in Hopenalytics branding — see Admin\BrandController. A domain with no matching
 * `brands` row (including the primary domain, which never needs one) just falls back to the
 * built-in branding, so adding a new mirror is the only thing that ever needs a row here.
 */
class ResolveBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        // Domains are stored already normalized (see Admin\BrandController), so this stays a
        // single indexed lookup rather than loading every row to compare in PHP.
        $host = Brand::normalizeDomain($request->getHost());
        $brand = Brand::where('domain', $host)->first() ?? new Brand(['name' => config('app.name')]);

        // Overriding app.name itself (rather than only sharing $currentBrand) means every one
        // of the ~48 existing "{$title} — {{ config('app.name') }}" page titles picks up the
        // resolved brand automatically, with no per-view changes needed.
        config(['app.name' => $brand->name]);
        View::share('currentBrand', $brand);

        return $next($request);
    }
}

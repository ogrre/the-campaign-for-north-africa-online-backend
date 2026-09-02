<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MapReviewAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('cna.map_review_enabled') && $request->user(), 404);

        return $next($request);
    }
}

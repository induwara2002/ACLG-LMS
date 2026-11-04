<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePradeshiyaSabhaAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->user()?->isPradeshiyaSabhaAdmin(), 403);

        return $next($request);
    }
}

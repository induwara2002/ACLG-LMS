<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = $roles !== [] ? $roles : ['ADMIN'];
        $normalized = [];

        foreach ($allowed as $roleGroup) {
            foreach (preg_split('/[,|]/', $roleGroup) as $role) {
                $role = strtoupper(trim((string) $role));

                if ($role !== '') {
                    $normalized[] = $role;
                }
            }
        }

        if (! in_array(strtoupper((string) $user->role), $normalized, true)) {
            return redirect($user->dashboardRoute());
        }

        return $next($request);
    }
}

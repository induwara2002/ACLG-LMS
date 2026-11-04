<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_active === false) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        if (in_array(strtoupper((string) $user->role), [
            \App\Models\User::ROLE_SABHA_ADMIN,
            \App\Models\User::ROLE_PRADESHIYA_SABHA_ADMIN,
        ], true) && ! $user->isPradeshiyaSabhaAdmin()) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        $allowedRoles = $roles;
        if ($allowedRoles === []) {
            $allowedRoles = ['ADMIN'];
        }

        $normalized = [];
        foreach ($allowedRoles as $roleGroup) {
            foreach (preg_split('/[|,]/', $roleGroup) as $role) {
                $role = strtoupper(trim((string) $role));

                if ($role !== '') {
                    $normalized[] = $role;
                }
            }
        }

        if (! $user->hasRole(...$normalized)) {
            return redirect($user->dashboardRoute());
        }

        return $next($request);
    }
}

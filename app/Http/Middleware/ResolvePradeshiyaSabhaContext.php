<?php

namespace App\Http\Middleware;

use App\Models\PradeshiyaSabha;
use App\Services\PradeshiyaSabhaContext;
use Closure;
use Illuminate\Http\Request;

class ResolvePradeshiyaSabhaContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        $context = app(PradeshiyaSabhaContext::class);
        $context->setOverride(null);
        $user = $request->user();

        if (! $user?->isMainAdmin() || $request->routeIs('admin.sabhas.dashboard')) {
            return $next($request);
        }

        if ($request->routeIs('admin.dashboard')) {
            $requestedId = $request->integer('sabha_id');
            if ($requestedId > 0) {
                abort_unless(PradeshiyaSabha::query()->where('is_active', true)->whereKey($requestedId)->exists(), 404);
                $request->session()->put('active_pradeshiya_sabha_id', $requestedId);
            } else {
                $request->session()->forget('active_pradeshiya_sabha_id');
            }
        } elseif ($request->routeIs('admin.sabhas.*', 'admin.audit-logs.*', 'admin.settings.*')) {
            $request->session()->forget('active_pradeshiya_sabha_id');
        }

        $requestedSabhaId = $request->integer('sabha_id');
        if ($requestedSabhaId > 0 && ! $request->routeIs('admin.sabhas.*')) {
            abort_unless(PradeshiyaSabha::query()->where('is_active', true)->whereKey($requestedSabhaId)->exists(), 404);
            $context->setOverride($requestedSabhaId);

            return $next($request);
        }

        $selectedId = (int) $request->session()->get('active_pradeshiya_sabha_id', 0);
        if ($selectedId > 0 && PradeshiyaSabha::query()->where('is_active', true)->whereKey($selectedId)->exists()) {
            $context->setOverride($selectedId);
        } elseif ($selectedId > 0) {
            $request->session()->forget('active_pradeshiya_sabha_id');
        }

        return $next($request);
    }
}
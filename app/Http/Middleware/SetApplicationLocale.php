<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $request->session()->get('locale')
            ?? $request->cookie('ui_locale')
            ?? config('app.locale', 'en');

        if (! in_array($locale, ['en', 'si'], true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}

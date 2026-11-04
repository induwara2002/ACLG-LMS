<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,si'],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back()->withCookie(cookie(
            'ui_locale',
            $validated['locale'],
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        ));
    }
}

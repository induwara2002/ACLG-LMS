<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditLogService $auditLogService): RedirectResponse
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user || ! $user->is_active) {
                Auth::logout();

                return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
            }

            $request->session()->regenerate();
            $auditLogService->record('auth.login', $user, $user, 'User signed in');

            return redirect()->to($user->dashboardRoute());
        }

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    public function destroy(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $user = $request->user();

        try {
            $auditLogService->record('auth.logout', $user, $user, 'User signed out');
        } finally {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}

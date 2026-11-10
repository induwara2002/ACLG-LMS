<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\Member;
use App\Models\User;
use App\Services\PradeshiyaSabhaContext;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService, PradeshiyaSabhaContext $context)
    {
        $year = (int) $request->query('year', now()->year);
        $user = $request->user();
        $selectedId = app(PradeshiyaSabhaContext::class)->id();
        $sabha = $user->isPradeshiyaSabhaAdmin()
            ? $user->pradeshiyaSabha
            : ($selectedId ? PradeshiyaSabha::query()->find($selectedId) : null);
        $data = $sabha
            ? $context->runFor($sabha, fn () => $dashboardService->admin($year, $user->isPradeshiyaSabhaAdmin()))
            : $dashboardService->admin($year);
        $data['pradeshiyaSabha'] = $sabha;
        $data['libraryName'] = $sabha?->setting('library_name') ?: $sabha?->name;
        $data['logoUrl'] = $sabha?->logo_path
            ? route('sabhas.logo', $sabha)
            : asset('images/government-logo.png');
        $data['sabhaTotals'] = $user->isMainAdmin() ? [
            'total' => PradeshiyaSabha::query()->count(),
            'active' => PradeshiyaSabha::query()->where('is_active', true)->count(),
            'libraries' => PradeshiyaSabha::query()
                ->whereHas('settings', fn ($query) => $query
                    ->where('key', 'library_name')
                    ->whereNotNull('value')
                    ->where('value', '<>', ''))
                ->count(),
            'activeAdmins' => PradeshiyaSabhaAdminAssignment::query()
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', 'PRADESHIYA_SABHA_ADMIN'))
                ->count(),
        ] : null;
        if ($user->isPradeshiyaSabhaAdmin()) {
            $data['activeLibrarianCount'] = $sabha->users()
                ->where('role', User::ROLE_LIBRARIAN)
                ->where('is_active', true)
                ->count();
            $data['pendingApplicationCount'] = Member::query()
                ->where('pradeshiya_sabha_id', $user->pradeshiya_sabha_id)
                ->where('status', 'PENDING')
                ->count();
            $data['pendingApplications'] = Member::query()
                ->where('pradeshiya_sabha_id', $user->pradeshiya_sabha_id)
                ->where('status', 'PENDING')
                ->with('membershipType')
                ->latest('registration_date')
                ->limit(5)
                ->get();
        }
        $data['sabhas'] = $user->isMainAdmin() && $selectedId === null
            ? PradeshiyaSabha::query()
                ->with('adminAssignment.user')
                ->withCount([
                    'members',
                    'books',
                    'borrowings as active_borrowings_count' => fn ($query) => $query->whereNull('return_date'),
                    'borrowings as overdue_count' => fn ($query) => $query->whereNull('return_date')->whereDate('due_date', '<', today()),
                    'payments',
                ])
                ->orderBy('name')
                ->get()
            : collect();

        return view($user->isPradeshiyaSabhaAdmin() ? 'admin.sabha-dashboard' : 'admin.dashboard', $data);
    }
}

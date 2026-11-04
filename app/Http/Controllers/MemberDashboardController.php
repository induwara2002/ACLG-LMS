<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Services\DashboardService;

class MemberDashboardController extends Controller
{
    public function index(DashboardService $dashboardService)
    {
        $member = Auth::user()->member()->with('membershipType')->firstOrFail();
        $dashboard = $dashboardService->member($member);

        return view('member.dashboard', compact('member') + $dashboard);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class LibrarianDashboardController extends Controller
{
    public function index(DashboardService $dashboardService)
    {
        return view('librarian.dashboard', $dashboardService->librarian());
    }
}

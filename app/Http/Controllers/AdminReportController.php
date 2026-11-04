<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Book;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Services\PradeshiyaSabhaContext;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminReportController extends Controller
{
    public const REPORTS = [
        'books', 'members', 'borrowings', 'overdue', 'returns',
        'payments', 'fines', 'lost-books', 'damaged-books', 'compensation',
    ];

    public function index(Request $request)
    {
        return view('reports.index', [
            'reports' => self::REPORTS,
            'routePrefix' => $request->routeIs('librarian.*') ? 'librarian' : 'admin',
        ]);
    }

    public function show(string $report, ReportRequest $request, ReportService $reportService)
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);

        $method = str_replace('-', '', ucwords($report, '-'));
        $records = $reportService->{$method}($request->validated());
        $sabhaId = app(PradeshiyaSabhaContext::class)->id();
        $reportSabha = $sabhaId === null
            ? null
            : PradeshiyaSabha::query()->with('settings')->findOrFail($sabhaId);

        return view('reports.'.$report, [
            'report' => $report,
            'records' => $records,
            'filters' => $request->validated(),
            'reportSabha' => $reportSabha,
            'reportSettings' => $reportSabha?->settings->pluck('value', 'key') ?? collect(),
            'reportLogoAvailable' => $reportSabha?->logo_path !== null
                && Storage::disk('local')->exists($reportSabha->logo_path),
            'members' => Member::query()->select('id', 'full_name', 'member_id')->orderBy('full_name')->get(),
            'books' => Book::query()->select('id', 'title')->orderBy('title')->get(),
            'membershipTypes' => MembershipType::orderBy('name')->get(),
            'sabhas' => $request->user()->isMainAdmin()
                ? PradeshiyaSabha::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'routePrefix' => $request->routeIs('librarian.*') ? 'librarian' : 'admin',
        ]);
    }
}

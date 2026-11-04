<?php

namespace App\Http\Controllers;

use App\Exceptions\PradeshiyaSabhaConfigurationIncompleteException;
use App\Services\PradeshiyaSabhaSettingsService;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function index(Request $request, PradeshiyaSabhaSettingsService $settingsService)
    {
        $sabha = $request->user()->pradeshiyaSabha;
        abort_unless($sabha && $sabha->is_active, 404);

        return view('admin.settings.index', [
            'sabha' => $sabha->load('settings'),
            'settingsStatus' => $settingsService->configurationStatus($sabha),
        ]);
    }
}

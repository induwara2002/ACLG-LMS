<?php

namespace App\Http\Controllers;

use App\Models\PradeshiyaSabha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SabhaLogoController extends Controller
{
    public function show(Request $request, PradeshiyaSabha $sabha): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(
            $user->is_active && (
                $user->isMainAdmin()
                || ($sabha->is_active && (int) $user->pradeshiya_sabha_id === (int) $sabha->id)
            ),
            404
        );

        abort_unless($sabha->logo_path && Storage::disk('local')->exists($sabha->logo_path), 404);

        return response()->file(Storage::disk('local')->path($sabha->logo_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
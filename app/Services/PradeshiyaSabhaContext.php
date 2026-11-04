<?php

namespace App\Services;

use App\Models\PradeshiyaSabha;
use Closure;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PradeshiyaSabhaContext
{
    private ?int $overrideId = null;

    public function setOverride(?int $sabhaId): void
    {
        $this->overrideId = $sabhaId;
    }

    public function id(): ?int
    {
        $user = Auth::user();

        if ($user?->isMainAdmin()) {
            return $this->overrideId;
        }

        if ($user?->pradeshiya_sabha_id !== null) {
            return (int) $user->pradeshiya_sabha_id;
        }

        return $this->overrideId;
    }

    public function runFor(PradeshiyaSabha $sabha, Closure $callback): mixed
    {
        $user = Auth::user();
        if ($user?->isPradeshiyaSabhaAdmin() && (int) $user->pradeshiya_sabha_id !== (int) $sabha->id) {
            throw new AccessDeniedHttpException();
        }

        $previousId = $this->overrideId;
        $this->overrideId = (int) $sabha->id;

        try {
            return $callback();
        } finally {
            $this->overrideId = $previousId;
        }
    }

    public function runForId(?int $sabhaId, Closure $callback): mixed
    {
        if ($sabhaId === null) {
            return $callback();
        }

        return $this->runFor(PradeshiyaSabha::query()->findOrFail($sabhaId), $callback);
    }
}
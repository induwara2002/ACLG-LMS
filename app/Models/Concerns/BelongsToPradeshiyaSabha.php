<?php

namespace App\Models\Concerns;

use App\Models\Scopes\PradeshiyaSabhaScope;
use App\Services\PradeshiyaSabhaContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait BelongsToPradeshiyaSabha
{
    protected static function bootBelongsToPradeshiyaSabha(): void
    {
        static::addGlobalScope(new PradeshiyaSabhaScope());

        static::saving(function (Model $model) {
            $user = Auth::user();
            $contextId = app(PradeshiyaSabhaContext::class)->id();
            $sabhaId = $user?->isPradeshiyaSabhaAdmin()
                ? (int) $user->pradeshiya_sabha_id
                : $contextId;

            if ($sabhaId === null) {
                return;
            }

            if ($model->exists && (int) $model->getRawOriginal('pradeshiya_sabha_id') !== $sabhaId) {
                throw new AccessDeniedHttpException();
            }

            if ($model->getAttribute('pradeshiya_sabha_id') !== null
                && (int) $model->getAttribute('pradeshiya_sabha_id') !== $sabhaId) {
                throw new AccessDeniedHttpException();
            }

            $model->setAttribute('pradeshiya_sabha_id', $sabhaId);
        });
    }

    public function pradeshiyaSabha()
    {
        return $this->belongsTo(\App\Models\PradeshiyaSabha::class);
    }
}
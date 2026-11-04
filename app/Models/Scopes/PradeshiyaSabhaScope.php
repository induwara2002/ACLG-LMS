<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Services\PradeshiyaSabhaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class PradeshiyaSabhaScope implements Scope
{
    private static bool $resolvingUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        $isUserQuery = $model instanceof User;
        if ($isUserQuery && self::$resolvingUser) {
            return;
        }

        $wasResolvingUser = self::$resolvingUser;
        if ($isUserQuery) {
            self::$resolvingUser = true;
        }

        try {
            $user = Auth::user();
            $sabhaId = app(PradeshiyaSabhaContext::class)->id();
        } finally {
            self::$resolvingUser = $wasResolvingUser;
        }

        if ($sabhaId !== null) {
            $builder->where($model->qualifyColumn('pradeshiya_sabha_id'), $sabhaId);
        } elseif ($user !== null && ! $user->isMainAdmin()) {
            $builder->whereNull($model->qualifyColumn('pradeshiya_sabha_id'));
        }
    }
}
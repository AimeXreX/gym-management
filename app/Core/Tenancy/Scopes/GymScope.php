<?php

namespace App\Core\Tenancy\Scopes;

use App\Core\Tenancy\Exceptions\MissingGymContext;
use App\Core\Tenancy\GymContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class GymScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(GymContext::class);

        if (! $context->has()) {
            throw MissingGymContext::make();
        }

        $builder->where($model->qualifyColumn('gym_id'), $context->id());
    }
}

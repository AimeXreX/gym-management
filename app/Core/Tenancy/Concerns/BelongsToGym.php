<?php

namespace App\Core\Tenancy\Concerns;

use App\Core\Tenancy\GymContext;
use App\Core\Tenancy\Scopes\GymScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToGym
{
    protected static function bootBelongsToGym(): void
    {
        static::addGlobalScope(new GymScope);

        static::creating(function (Model $model): void {
            $model->setAttribute('gym_id', app(GymContext::class)->id());
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('gym_id')) {
                throw new LogicException('Changing tenant ownership is not allowed.');
            }
        });
    }
}

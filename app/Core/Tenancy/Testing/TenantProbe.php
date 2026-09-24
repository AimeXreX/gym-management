<?php

namespace App\Core\Tenancy\Testing;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label'])]
class TenantProbe extends Model
{
    use BelongsToGym;
}

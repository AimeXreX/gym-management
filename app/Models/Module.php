<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'description', 'version', 'metadata_hash'])]
class Module extends Model
{
    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_modules')
            ->withPivot(['enabled', 'config_json', 'enabled_at'])
            ->withTimestamps();
    }
}

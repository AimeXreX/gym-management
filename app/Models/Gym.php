<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['owner_id', 'name', 'slug', 'status', 'timezone', 'locale', 'currency', 'settings_json', 'theme_preset', 'brand_primary', 'brand_accent', 'brand_tagline', 'ui_radius', 'ui_density'])]
class Gym extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['settings_json' => 'array'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(GymUser::class)
            ->withPivot(['id', 'status', 'role', 'permissions_json', 'joined_at'])
            ->withTimestamps();
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'gym_modules')
            ->withPivot(['enabled', 'config_json', 'enabled_at'])
            ->withTimestamps();
    }
}

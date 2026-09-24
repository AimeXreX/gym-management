<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GymUser extends Pivot
{
    protected $table = 'gym_user';

    public $incrementing = true;

    protected $fillable = ['gym_id', 'user_id', 'status', 'role', 'permissions_json', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'permissions_json' => 'array'];
    }
}

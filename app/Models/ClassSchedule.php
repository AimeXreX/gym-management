<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['gym_class_id', 'weekday', 'starts_at', 'status'])]
class ClassSchedule extends Model
{
    use BelongsToGym;

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class);
    }
}

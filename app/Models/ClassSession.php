<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['gym_class_id', 'class_schedule_id', 'starts_at', 'ends_at', 'status'])]
class ClassSession extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ClassEnrollment::class);
    }
}

<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'is_system_default', 'status', 'phone', 'manager_name', 'manager_mobile', 'address', 'operating_hours'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToGym, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_system_default' => 'boolean', 'operating_hours' => 'array'];
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function coaches()
    {
        return $this->hasMany(Coach::class);
    }

    public function gymClasses()
    {
        return $this->hasMany(GymClass::class);
    }
}

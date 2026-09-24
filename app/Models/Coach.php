<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Database\Factories\CoachFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'branch_id', 'first_name', 'last_name', 'mobile', 'email', 'avatar_path', 'specialty', 'coach_type', 'biography', 'hire_date', 'status', 'compensation_notes'])]
class Coach extends Model
{
    /** @use HasFactory<CoachFactory> */
    use BelongsToGym, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['hire_date' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function classes()
    {
        return $this->hasMany(GymClass::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function memberAssignments()
    {
        return $this->hasMany(CoachMemberAssignment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getCoachTypeLabelAttribute(): string
    {
        return match ($this->coach_type) {
            'training' => 'مربی تمرین',
            'nutrition' => 'مربی تغذیه',
            'both' => 'مربی تمرین و تغذیه',
            default => $this->coach_type ?: 'مربی',
        };
    }

    public function handlesDomain(string $domain): bool
    {
        return $this->status === 'active'
            && in_array($domain, ['training', 'nutrition'], true)
            && ($this->coach_type === 'both' || $this->coach_type === $domain);
    }
}

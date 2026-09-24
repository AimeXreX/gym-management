<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'branch_id', 'membership_code', 'public_token', 'first_name', 'last_name', 'mobile', 'secondary_mobile', 'email', 'national_id', 'birth_date', 'gender', 'avatar_path', 'emergency_contact_name', 'emergency_contact_mobile', 'height_cm', 'weight_kg', 'medical_notes', 'notes', 'status', 'joined_at', 'last_attended_at', 'created_by'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToGym, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'joined_at' => 'date', 'last_attended_at' => 'datetime', 'height_cm' => 'decimal:2', 'weight_kg' => 'decimal:2'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberships()
    {
        return $this->hasMany(MemberMembership::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function user() { return $this->belongsTo(User::class); }
    public function coachAssignments() { return $this->hasMany(CoachMemberAssignment::class); }
    public function nutritionPlans() { return $this->hasMany(NutritionPlan::class); }
    public function foodLogs() { return $this->hasMany(FoodLog::class); }
    public function workoutSessions() { return $this->hasMany(WorkoutSession::class); }
    public function workoutPrograms() { return $this->hasMany(WorkoutProgram::class); }
    public function coachRequests() { return $this->hasMany(CoachRequest::class); }
    public function bodyMeasurements() { return $this->hasMany(BodyMeasurement::class); }
    public function progressPhotos() { return $this->hasMany(ProgressPhoto::class); }
    public function injuryRecords() { return $this->hasMany(InjuryRecord::class); }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}

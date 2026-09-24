<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['branch_id', 'name', 'description', 'duration_days', 'session_limit', 'price', 'currency', 'is_active', 'sort_order'])]
class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use BelongsToGym, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'decimal:2'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberships()
    {
        return $this->hasMany(MemberMembership::class);
    }
}

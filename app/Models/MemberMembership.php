<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['member_id', 'membership_plan_id', 'branch_id', 'reference', 'starts_at', 'ends_at', 'status', 'agreed_price', 'discount_amount', 'payable_amount', 'paid_amount', 'session_limit', 'sessions_used', 'frozen_at', 'freeze_ends_at', 'cancelled_at', 'notes', 'created_by'])]
class MemberMembership extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'frozen_at' => 'datetime', 'freeze_ends_at' => 'datetime', 'cancelled_at' => 'datetime', 'agreed_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'payable_amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'membership_id');
    }

    public function getRemainingAmountAttribute(): string
    {
        return number_format(max(0, (float) $this->payable_amount - (float) $this->paid_amount), 2, '.', '');
    }

    public function getEffectiveStatusAttribute(): string
    {
        if (in_array($this->status, ['cancelled', 'frozen'], true)) {
            return $this->status;
        }if ($this->starts_at->isFuture()) {
            return 'pending';
        }if ($this->ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}

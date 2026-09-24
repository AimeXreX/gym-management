<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['member_id', 'membership_id', 'branch_id', 'amount', 'payment_method', 'reference_number', 'paid_at', 'status', 'notes', 'received_by', 'voided_at', 'voided_by', 'void_reason'])]
class Payment extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function membership()
    {
        return $this->belongsTo(MemberMembership::class, 'membership_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['member_id', 'branch_id', 'membership_id', 'class_session_id', 'checked_in_at', 'checked_out_at', 'method', 'recorded_by', 'notes'])]
class Attendance extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function membership()
    {
        return $this->belongsTo(MemberMembership::class, 'membership_id');
    }
}

<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['class_session_id', 'member_id', 'status', 'enrolled_at'])]
class ClassEnrollment extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['enrolled_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

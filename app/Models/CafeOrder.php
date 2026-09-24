<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['member_id', 'branch_id', 'order_number', 'total', 'payment_method', 'status', 'created_by'])]
class CafeOrder extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['total' => 'decimal:2'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function items()
    {
        return $this->hasMany(CafeOrderItem::class);
    }
}

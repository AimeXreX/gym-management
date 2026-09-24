<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['wallet_id', 'member_id', 'type', 'amount', 'balance_after', 'reason', 'reference', 'source_type', 'source_id', 'description', 'created_by'])]
class WalletTransaction extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

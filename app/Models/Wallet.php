<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['member_id', 'balance', 'currency', 'status'])]
class Wallet extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}

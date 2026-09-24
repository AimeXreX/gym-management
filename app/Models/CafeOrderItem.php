<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cafe_order_id', 'cafe_product_id', 'product_name', 'unit_price', 'quantity', 'line_total'])]
class CafeOrderItem extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(CafeOrder::class, 'cafe_order_id');
    }
}

<?php

namespace App\Models;

use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['cafe_category_id', 'name', 'description', 'price', 'stock', 'is_active'])]
class CafeProduct extends Model
{
    use BelongsToGym, SoftDeletes;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(CafeCategory::class, 'cafe_category_id');
    }
}

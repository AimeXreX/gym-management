<?php

namespace App\Modules\Commercial\Application;

use App\Models\CafeOrder;
use App\Models\CafeOrderItem;
use App\Models\CafeProduct;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CafeCheckout
{
    public function __construct(private readonly WalletLedger $ledger) {}

    public function handle(Member $member, array $lines, int $actorId): CafeOrder
    {
        return DB::transaction(function () use ($member, $lines, $actorId) {
            $items = collect($lines)->filter(fn ($quantity) => (int) $quantity > 0)->map(function ($quantity, $productId) {
                $product = CafeProduct::query()->lockForUpdate()->where('is_active', true)->findOrFail($productId);
                $quantity = (int) $quantity;
                if ($product->stock !== null && $product->stock < $quantity) {
                    throw ValidationException::withMessages(['items' => "موجودی «{$product->name}» کافی نیست."]);
                }

                return [$product, $quantity, round((float) $product->price * $quantity, 2)];
            });
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'حداقل یک محصول انتخاب کنید.']);
            }
            $order = CafeOrder::query()->create([
                'member_id' => $member->id, 'branch_id' => $member->branch_id,
                'order_number' => 'CF-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'total' => $items->sum(fn ($line) => $line[2]), 'payment_method' => 'wallet',
                'status' => 'completed', 'created_by' => $actorId,
            ]);
            foreach ($items as [$product, $quantity, $lineTotal]) {
                CafeOrderItem::query()->create(['cafe_order_id' => $order->id, 'cafe_product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => $product->price, 'quantity' => $quantity, 'line_total' => $lineTotal]);
                if ($product->stock !== null) {
                    $product->decrement('stock', $quantity);
                }
            }
            $this->ledger->debit($member, (float) $order->total, 'cafe-order:'.$order->id, 'cafe_purchase', $actorId, 'خرید '.$order->order_number);

            return $order;
        }, 3);
    }
}

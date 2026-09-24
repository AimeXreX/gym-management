<?php

namespace App\Modules\Commercial\Application;

use App\Models\Member;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletLedger
{
    public function credit(Member $member, float $amount, string $reference, string $reason, ?int $actorId = null, ?string $description = null): WalletTransaction
    {
        return $this->post($member, abs($amount), 'credit', $reference, $reason, $actorId, $description);
    }

    public function debit(Member $member, float $amount, string $reference, string $reason, ?int $actorId = null, ?string $description = null): WalletTransaction
    {
        return $this->post($member, -abs($amount), 'debit', $reference, $reason, $actorId, $description);
    }

    private function post(Member $member, float $signedAmount, string $type, string $reference, string $reason, ?int $actorId, ?string $description): WalletTransaction
    {
        if ($signedAmount == 0.0) {
            throw ValidationException::withMessages(['amount' => 'مبلغ باید بیشتر از صفر باشد.']);
        }

        return DB::transaction(function () use ($member, $signedAmount, $type, $reference, $reason, $actorId, $description) {
            $existing = WalletTransaction::query()->where('reference', $reference)->first();
            if ($existing) {
                if ($existing->member_id !== $member->id || $existing->type !== $type || (float) $existing->amount !== abs($signedAmount)) {
                    throw ValidationException::withMessages(['reference' => 'این شناسه قبلاً برای تراکنش دیگری استفاده شده است.']);
                }

                return $existing;
            }

            $wallet = Wallet::query()->firstOrCreate(['member_id' => $member->id], ['balance' => 0, 'currency' => 'IRR', 'status' => 'active']);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            if ($wallet->status !== 'active') {
                throw ValidationException::withMessages(['wallet' => 'کیف پول فعال نیست.']);
            }
            $balance = round((float) $wallet->balance + $signedAmount, 2);
            if ($balance < 0) {
                throw ValidationException::withMessages(['wallet' => 'موجودی کیف پول کافی نیست.']);
            }
            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id, 'member_id' => $member->id, 'type' => $type,
                'amount' => abs($signedAmount), 'balance_after' => $balance, 'reason' => $reason,
                'reference' => $reference, 'description' => $description, 'created_by' => $actorId,
            ]);
            $wallet->update(['balance' => $balance]);

            return $transaction;
        }, 3);
    }
}

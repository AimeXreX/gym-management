<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\CafeCategory;
use App\Models\CafeOrder;
use App\Models\CafeProduct;
use App\Models\Member;
use App\Models\Wallet;
use App\Modules\Commercial\Application\CafeCheckout;
use App\Modules\Commercial\Application\WalletLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WalletCafeController extends Controller
{
    public function wallets(Request $request)
    {
        $members = Member::query()->with('wallet')->when($request->search, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('first_name', 'like', "%{$value}%")->orWhere('last_name', 'like', "%{$value}%")->orWhere('membership_code', 'like', "%{$value}%")))->latest()->paginate(20)->withQueryString();

        return view('commercial.wallets.index', compact('members'));
    }

    public function wallet(Member $member)
    {
        $wallet = Wallet::query()->firstOrCreate(['member_id' => $member->id], ['balance' => 0, 'currency' => 'IRR', 'status' => 'active']);
        $wallet->load(['transactions' => fn ($query) => $query->latest()->limit(50)]);

        return view('commercial.wallets.show', compact('member', 'wallet'));
    }

    public function charge(Request $request, Member $member, WalletLedger $ledger, AuditLogger $audit)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:999999999999'], 'description' => ['nullable', 'string', 'max:500']]);
        $transaction = $ledger->credit($member, (float) $data['amount'], 'manual-charge:'.Str::uuid(), 'manual_charge', $request->user()->id, $data['description'] ?? null);
        $audit->record('wallet.charged', $request, $request->user(), $member->gym_id, $transaction, ['member_id' => $member->id, 'amount' => $data['amount']]);

        return back()->with('status', 'کیف پول با موفقیت شارژ شد.');
    }

    public function cafe(Request $request)
    {
        return view('commercial.cafe.index', [
            'categories' => CafeCategory::with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->where('is_active', true)->orderBy('sort_order')->get(),
            'members' => Member::where('status', 'active')->orderBy('last_name')->limit(200)->get(),
            'orders' => CafeOrder::with('member')->latest()->limit(12)->get(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        CafeCategory::create($data + ['is_active' => true]);

        return back()->with('status', 'دسته منو اضافه شد.');
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'cafe_category_id' => ['nullable', Rule::exists('cafe_categories', 'id')],
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'], 'stock' => ['nullable', 'integer', 'min:0'],
        ]);
        CafeProduct::create($data + ['is_active' => true]);

        return back()->with('status', 'محصول به منوی کافه اضافه شد.');
    }

    public function checkout(Request $request, CafeCheckout $checkout, AuditLogger $audit)
    {
        $data = $request->validate(['member_id' => ['required', Rule::exists('members', 'id')], 'items' => ['required', 'array'], 'items.*' => ['nullable', 'integer', 'min:0', 'max:100']]);
        $member = Member::findOrFail($data['member_id']);
        $order = $checkout->handle($member, $data['items'], $request->user()->id);
        $audit->record('cafe.order_completed', $request, $request->user(), $member->gym_id, $order, ['member_id' => $member->id, 'total' => $order->total]);

        return back()->with('status', "سفارش {$order->order_number} با کیف پول پرداخت شد.");
    }
}

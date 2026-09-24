<x-layouts.app title="کیف پول اعضا">
<div class="page-hero"><div><p class="section-kicker">اعتبار اعضا</p><h1 class="page-title">کیف پول</h1><p class="page-subtitle">مانده شفاف، تاریخچه تغییرناپذیر و آماده اتصال به درگاه پرداخت.</p></div></div>
<x-card><form class="mb-5 flex gap-3"><x-input name="search" :value="request('search')" placeholder="نام یا کد عضویت"/><x-button type="submit" icon="search">جست‌وجو</x-button></form>
<div class="table-wrap"><table><thead><tr><th>عضو</th><th>کد</th><th>موجودی</th><th></th></tr></thead><tbody>@forelse($members as $member)<tr><td class="font-bold">{{ $member->full_name }}</td><td>{{ $member->membership_code }}</td><td>{{ number_format((float) optional($member->wallet)->balance) }} ریال</td><td><x-button variant="secondary" :href="route('tenant.wallets.show',$member)">مشاهده</x-button></td></tr>@empty<tr><td colspan="4"><x-empty-state title="عضوی پیدا نشد"/></td></tr>@endforelse</tbody></table></div>{{ $members->links() }}</x-card>
</x-layouts.app>

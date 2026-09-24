<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Modules\Commercial\Application\GymSettings;
use App\Core\Authorization\TenantRoleService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(GymSettings $settings, Request $request, TenantRoleService $roles)
    {
        $role = $roles->role($request->user());
        if ($role === 'member') {
            return redirect()->route('tenant.wellness.index');
        }
        if (in_array($role, ['coach', 'nutrition_coach'], true)) {
            return redirect()->route('tenant.wellness.dashboard');
        }
        $days = (int) $settings->get('expiry_warning_days', 14);
        $metrics = [
            'active_members' => Member::where('status', 'active')->count(),
            'new_members' => Member::whereBetween('joined_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'active_memberships' => MemberMembership::whereDate('starts_at', '<=', today())->whereDate('ends_at', '>=', today())->whereNotIn('status', ['cancelled', 'frozen'])->count(),
            'expiring' => MemberMembership::whereBetween('ends_at', [today(), today()->addDays($days)])->whereNotIn('status', ['cancelled'])->count(),
            'today_attendance' => Attendance::whereDate('checked_in_at', today())->count(),
            'collections' => Payment::where('status', 'completed')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'outstanding' => MemberMembership::whereNotIn('status', ['cancelled'])->selectRaw('COALESCE(SUM(payable_amount-paid_amount),0) total')->value('total'),
            'today_classes' => ClassSession::whereDate('starts_at', today())->where('status', 'scheduled')->count(),
        ];
        $trend = collect(range(6, 0))->map(fn ($d) => ['date' => today()->subDays($d)->format('m/d'), 'count' => Attendance::whereDate('checked_in_at', today()->subDays($d))->count()]);

        return view('dashboard', [
            'metrics' => $metrics, 'trend' => $trend,
            'expiring' => MemberMembership::with('member')->whereBetween('ends_at', [today(), today()->addDays($days)])->limit(6)->get(),
            'payments' => Payment::with('member')->where('status', 'completed')->latest('paid_at')->limit(6)->get(),
            'classes' => ClassSession::with('gymClass.coach')->where('starts_at', '>=', now())->where('status', 'scheduled')->orderBy('starts_at')->limit(6)->get(),
        ]);
    }
}

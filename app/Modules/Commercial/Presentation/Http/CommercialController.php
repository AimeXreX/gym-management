<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\TenantRoleService;
use App\Core\Modules\ModuleManager;
use App\Core\Tenancy\GymContext;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClassEnrollment;
use App\Models\ClassSchedule;
use App\Models\ClassSession;
use App\Models\Coach;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommercialController extends Controller
{
    public function members(Request $r)
    {
        $q = Member::with(['branch', 'memberships' => fn ($q) => $q->latest('ends_at')->limit(1)])->when($r->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%")->orWhere('mobile', 'like', "%$v%")->orWhere('membership_code', 'like', "%$v%")))->when($r->status, fn ($q, $v) => $q->where('status', $v))->latest();

        return view('commercial.members.index', ['members' => $q->paginate(15)->withQueryString(), 'branches' => Branch::where('status', 'active')->get()]);
    }

    public function createMember()
    {
        return view('commercial.members.create', ['branches' => Branch::where('status', 'active')->orderByDesc('is_system_default')->get(), 'plans' => MembershipPlan::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function storeMember(Request $r, CommercialWorkflows $workflows)
    {
        $data = $r->validate(['branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', app(GymContext::class)->id())->where('status', 'active'))], 'membership_code' => ['nullable', 'max:40', Rule::unique('members')->where('gym_id', app(GymContext::class)->id())], 'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'mobile' => 'required|max:30', 'email' => 'nullable|email', 'birth_date' => 'nullable|date', 'gender' => 'nullable|in:male,female,other', 'height_cm' => 'nullable|numeric|min:0|max:300', 'weight_kg' => 'nullable|numeric|min:0|max:1000', 'joined_at' => 'required|date', 'status' => 'required|in:active,inactive', 'notes' => 'nullable|max:3000', 'medical_notes' => 'nullable|max:3000', 'membership_plan_id' => 'nullable|integer', 'starts_at' => 'nullable|date', 'discount_amount' => 'nullable|numeric|min:0', 'initial_payment' => 'nullable|numeric|min:0', 'payment_method' => 'nullable|in:cash,card,transfer,other']);
        $member = $workflows->createMember($data, $r);

        return redirect()->route('tenant.members.show', $member)->with('status', 'عضو با موفقیت ثبت شد.');
    }

    public function showMember(Member $member, TenantRoleService $roles)
    {
        $member->load(['branch', 'memberships.plan', 'payments' => fn ($q) => $q->latest('paid_at'), 'attendances' => fn ($q) => $q->latest('checked_in_at')->limit(10), 'coachAssignments.coach', 'coachRequests.coach', 'bodyMeasurements' => fn ($q) => $q->latest('measured_on')->limit(12), 'progressPhotos' => fn ($q) => $q->where('visibility', 'coaches')->latest('captured_on')->limit(12)]);

        return view('commercial.members.show', ['member' => $member, 'coaches' => Coach::where('status', 'active')->get(), 'isManager' => $roles->isManager(auth()->user())]);
    }

    public function editMember(Member $member)
    {
        return view('commercial.members.edit', ['member' => $member, 'branches' => Branch::where('status', 'active')->get()]);
    }

    public function updateMember(Request $r, Member $member, AuditLogger $audit)
    {
        $data = $r->validate(['branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $member->gym_id)->where('status', 'active'))], 'membership_code' => ['required', 'max:40', Rule::unique('members')->where('gym_id', $member->gym_id)->ignore($member)], 'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'mobile' => 'required|max:30', 'email' => 'nullable|email', 'birth_date' => 'nullable|date', 'gender' => 'nullable|in:male,female,other', 'height_cm' => 'nullable|numeric|min:0|max:300', 'weight_kg' => 'nullable|numeric|min:0|max:1000', 'joined_at' => 'required|date', 'status' => 'required|in:active,inactive', 'notes' => 'nullable|max:3000', 'medical_notes' => 'nullable|max:3000']);
        $member->update($data);
        $audit->record('member.updated', $r, $r->user(), $member->gym_id, $member, ['fields' => array_keys($data)]);

        return redirect()->route('tenant.members.show', $member)->with('status', 'اطلاعات عضو به‌روزرسانی شد.');
    }

    public function renew(Request $r, Member $member, CommercialWorkflows $w)
    {
        $data = $r->validate(['membership_plan_id' => 'required|integer', 'starts_at' => 'nullable|date', 'discount_amount' => 'nullable|numeric|min:0', 'initial_payment' => 'nullable|numeric|min:0', 'payment_method' => 'nullable|in:cash,card,transfer,other']);
        $m = DB::transaction(function () use ($data, $r, $member, $w) {
            $m = $w->assignMembership($member, MembershipPlan::findOrFail($data['membership_plan_id']), $data, $r);
            if (($data['initial_payment'] ?? 0) > 0) {
                $w->recordPayment($m, (float) $data['initial_payment'], $data['payment_method'] ?? 'cash', null, $r);
            }

            return $m;
        });

        return back()->with('status', 'عضویت جدید ثبت شد.');
    }

    public function plans()
    {
        return view('commercial.plans.index', ['plans' => MembershipPlan::with('branch')->withCount('memberships')->orderBy('sort_order')->paginate(20), 'branches' => Branch::where('status', 'active')->get()]);
    }

    public function storePlan(Request $r, AuditLogger $audit)
    {
        $gym = app(GymContext::class)->id();
        $d = $r->validate(['name' => 'required|max:255', 'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'description' => 'nullable|max:2000', 'duration_days' => 'required|integer|min:1|max:3650', 'session_limit' => 'nullable|integer|min:1', 'price' => 'required|numeric|min:0', 'currency' => 'required|size:3', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0']);
        $d['is_active'] = $r->boolean('is_active');
        $p = MembershipPlan::create($d);
        $audit->record('membership_plan.created', $r, $r->user(), $p->gym_id, $p, ['name' => $p->name]);

        return back()->with('status', 'طرح عضویت ایجاد شد.');
    }

    public function payments()
    {
        return view('commercial.payments.index', ['payments' => Payment::with(['member', 'membership'])->latest('paid_at')->paginate(20)]);
    }

    public function storePayment(Request $r, MemberMembership $membership, CommercialWorkflows $w)
    {
        $d = $r->validate(['amount' => 'required|numeric|min:1', 'payment_method' => 'required|in:cash,card,transfer,other', 'reference_number' => 'nullable|max:100']);
        $w->recordPayment($membership, (float) $d['amount'], $d['payment_method'], $d['reference_number'] ?? null, $r);

        return back()->with('status', 'پرداخت ثبت شد.');
    }

    public function attendance(Request $r)
    {
        $search = $r->search;
        $matches = $search ? Member::when($search, fn ($q, $v) => $q->where(fn ($x) => $x->where('membership_code', $v)->orWhere('public_token', $v)->orWhere('mobile', 'like', "%$v%")->orWhere('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%")))->limit(8)->get() : collect();

        return view('commercial.attendance.index', ['matches' => $matches, 'recent' => Attendance::with('member')->latest('checked_in_at')->limit(15)->get(), 'openVisits' => Attendance::with('member')->whereNull('checked_out_at')->latest('checked_in_at')->limit(15)->get()]);
    }

    public function checkIn(Request $r, Member $member, CommercialWorkflows $w)
    {
        $w->checkIn($member, $r->input('method', 'manual'), $r);

        return back()->with('status', 'ورود '.$member->full_name.' ثبت شد.');
    }

    public function voidPayment(Request $r, Payment $payment, CommercialWorkflows $w)
    {
        $data = $r->validate(['reason' => 'required|string|min:5|max:500']);
        $w->voidPayment($payment, $data['reason'], $r);

        return back()->with('status', 'پرداخت با حفظ سابقه باطل شد.');
    }

    public function checkOut(Request $r, Attendance $attendance, CommercialWorkflows $w)
    {
        $w->checkOut($attendance, $r);

        return back()->with('status', 'خروج عضو ثبت شد.');
    }

    public function rotateToken(Request $r, Member $member, AuditLogger $audit)
    {
        $member->update(['public_token' => Str::random(48)]);
        $audit->record('member.qr_rotated', $r, $r->user(), $member->gym_id, $member);

        return back()->with('status', 'توکن ورود عضو تغییر کرد؛ توکن قبلی دیگر معتبر نیست.');
    }

    public function qrCard(Member $member)
    {
        $member->load(['memberships' => fn ($q) => $q->latest('ends_at')->limit(1)]);

        return view('commercial.members.qr-card', compact('member'));
    }

    public function branches(Request $r)
    {
        return view('commercial.branches.index', ['branches' => Branch::withCount(['members', 'coaches', 'gymClasses'])->when($r->status, fn ($q, $v) => $q->where('status', $v))->when($r->search, fn ($q, $v) => $q->where('name', 'like', "%$v%"))->orderByDesc('is_system_default')->paginate(20)->withQueryString()]);
    }

    public function storeBranch(Request $r, AuditLogger $audit)
    {
        $d = $r->validate(['name' => 'required|max:255', 'phone' => 'nullable|max:30', 'manager_name' => 'nullable|max:255', 'manager_mobile' => 'nullable|max:30', 'address' => 'nullable|max:2000', 'status' => 'required|in:active,inactive']);
        $b = Branch::create($d);
        $audit->record('branch.created', $r, $r->user(), $b->gym_id, $b, ['name' => $b->name]);

        return back()->with('status', 'شعبه ایجاد شد.');
    }

    public function coaches(Request $r)
    {
        $coaches = Coach::query()
            ->with(['branch', 'user'])
            ->withCount(['classes', 'memberAssignments' => fn ($q) => $q->where('is_active', true)])
            ->when($r->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%")->orWhere('mobile', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))
            ->when($r->branch_id, fn ($q, $v) => $q->where('branch_id', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $gym = app(GymContext::class)->gym();
        $linkedUserIds = Coach::query()->whereNotNull('user_id')->pluck('user_id')->toArray();
        $availableUsers = $gym->users()->wherePivot('status', 'active')->whereNotIn('users.id', $linkedUserIds)->orderBy('users.name')->get();

        return view('commercial.coaches.index', ['coaches' => $coaches, 'branches' => Branch::where('status', 'active')->orderByDesc('is_system_default')->get(), 'availableUsers' => $availableUsers]);
    }

    public function storeCoach(Request $r, AuditLogger $audit)
    {
        $gym = app(GymContext::class)->id();
        $d = $r->validate(['branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'mobile' => 'required|max:30', 'email' => 'nullable|email', 'specialty' => 'nullable|max:255', 'coach_type' => ['required', Rule::in(['training', 'nutrition', 'both'])], 'biography' => 'nullable|max:3000', 'hire_date' => 'nullable|date', 'compensation_notes' => 'nullable|max:3000', 'user_id' => ['nullable', Rule::exists('gym_user', 'user_id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active')), Rule::unique('coaches', 'user_id')->where('gym_id', $gym)], 'status' => 'required|in:active,inactive', 'avatar' => 'nullable|file|mimetypes:image/jpeg,image/png,image/webp|mimes:jpg,jpeg,png,webp|max:2048']);
        if ($r->hasFile('avatar')) {
            $d['avatar_path'] = $r->file('avatar')->store("gyms/{$gym}/coaches", 'public');
        }
        unset($d['avatar']);
        $c = Coach::create($d);
        $audit->record('coach.created', $r, $r->user(), $c->gym_id, $c, ['name' => $c->full_name]);

        return back()->with('status', 'مربی ثبت شد.');
    }

    public function classes()
    {
        return view('commercial.classes.index', ['classes' => GymClass::with(['branch', 'coach', 'schedules'])->paginate(20), 'branches' => Branch::where('status', 'active')->orderByDesc('is_system_default')->get(), 'coaches' => Coach::where('status', 'active')->get()]);
    }

    public function storeClass(Request $r, AuditLogger $audit, CommercialWorkflows $workflows)
    {
        $gym = app(GymContext::class)->id();
        $d = $r->validate(['branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'coach_id' => ['required', Rule::exists('coaches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'name' => 'required|max:255', 'capacity' => 'required|integer|min:1|max:500', 'duration_minutes' => 'required|integer|min:15|max:480', 'level' => 'nullable|max:30', 'weekday' => 'required|integer|min:0|max:6', 'starts_at' => 'required|date_format:H:i']);
        $class = DB::transaction(function () use ($d) {
            $class = GymClass::create($d + ['status' => 'active']);
            $schedule = ClassSchedule::create(['gym_class_id' => $class->id, 'weekday' => $d['weekday'], 'starts_at' => $d['starts_at'], 'status' => 'active']);
            $next = now()->startOfDay();
            while ($next->dayOfWeek !== $d['weekday']) {
                $next->addDay();
            }$start = $next->setTimeFromTimeString($d['starts_at']);
            ClassSession::create(['gym_class_id' => $class->id, 'class_schedule_id' => $schedule->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($d['duration_minutes']), 'status' => 'scheduled']);

            return $class;
        });
        $audit->record('class.created', $r, $r->user(), $class->gym_id, $class, ['name' => $class->name]);
        $workflows->notifyClassSession($class->sessions()->latest('starts_at')->first(), 'scheduled');

        return back()->with('status', 'کلاس و نوبت بعدی ثبت شد.');
    }

    public function enroll(Request $r, ClassSession $session, AuditLogger $audit)
    {
        $d = $r->validate(['member_id' => 'required|integer']);
        $member = Member::findOrFail($d['member_id']);
        $session->loadCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])->load('gymClass');
        if ($session->enrollments_count >= $session->gymClass->capacity) {
            return back()->withErrors(['member_id' => 'ظرفیت کلاس تکمیل است.']);
        }$e = ClassEnrollment::firstOrCreate(['class_session_id' => $session->id, 'member_id' => $member->id], ['status' => 'enrolled', 'enrolled_at' => now()]);
        $audit->record('class.member_enrolled', $r, $r->user(), $e->gym_id, $e, ['member_id' => $member->id]);

        return back()->with('status', 'عضو در کلاس ثبت‌نام شد.');
    }

    public function reports(Request $r)
    {
        $from = $r->date('from') ?? now()->startOfMonth();
        $to = $r->date('to') ?? now();
        $metrics = ['members' => Member::count(), 'active_memberships' => MemberMembership::whereDate('starts_at', '<=', $to)->whereDate('ends_at', '>=', $from)->whereNotIn('status', ['cancelled'])->count(), 'collections' => Payment::where('status', 'completed')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount'), 'attendance' => Attendance::whereBetween('checked_in_at', [$from->startOfDay(), $to->endOfDay()])->count(), 'outstanding' => MemberMembership::whereNotIn('status', ['cancelled'])->selectRaw('COALESCE(SUM(payable_amount-paid_amount),0) total')->value('total')];

        return view('commercial.reports.index', compact('metrics', 'from', 'to'));
    }

    public function exportPayments(Request $r)
    {
        $rows = Payment::with('member')->where('status', 'completed')->orderBy('paid_at')->get();

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            $f = fopen('php://output', 'w');
            fputcsv($f, ['تاریخ', 'عضو', 'مبلغ', 'روش', 'مرجع']);
            foreach ($rows as $p) {
                fputcsv($f, [$p->paid_at->format('Y-m-d H:i'), $p->member->full_name, $p->amount, $p->payment_method, $p->reference_number]);
            }fclose($f);
        }, 'payments.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function activity(Request $r)
    {
        return view('commercial.activity.index', ['logs' => AuditLog::with('user')->where('gym_id', app(GymContext::class)->id())->when($r->search, fn ($q, $v) => $q->where('event', 'like', "%$v%"))->latest()->paginate(30)]);
    }

    public function search(Request $r, ModuleManager $modules)
    {
        $q = trim((string) $r->query('q'));
        $results = [];
        if (mb_strlen($q) >= 2) {
            if ($modules->enabled('members')) {
                $results['اعضا'] = Member::where(fn ($x) => $x->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")->orWhere('mobile', 'like', "%$q%")->orWhere('membership_code', 'like', "%$q%"))->limit(8)->get()->map(fn ($m) => ['title' => $m->full_name, 'subtitle' => $m->membership_code.' · '.$m->mobile, 'url' => route('tenant.members.show', $m)]);
            }if ($modules->enabled('coaches')) {
                $results['مربیان'] = Coach::where(fn ($x) => $x->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")->orWhere('mobile', 'like', "%$q%"))->limit(6)->get()->map(fn ($c) => ['title' => $c->full_name, 'subtitle' => $c->specialty, 'url' => route('tenant.coaches.index')]);
            }if ($modules->enabled('classes')) {
                $results['کلاس‌ها'] = GymClass::where('name', 'like', "%$q%")->limit(6)->get()->map(fn ($c) => ['title' => $c->name, 'subtitle' => 'کلاس', 'url' => route('tenant.classes.index')]);
            }if ($modules->enabled('payments')) {
                $results['پرداخت‌ها'] = Payment::with('member')->where('reference_number', 'like', "%$q%")->limit(6)->get()->map(fn ($p) => ['title' => $p->reference_number, 'subtitle' => $p->member->full_name, 'url' => route('tenant.payments.index')]);
            }
        }

        return view('commercial.search', compact('q', 'results'));
    }
}

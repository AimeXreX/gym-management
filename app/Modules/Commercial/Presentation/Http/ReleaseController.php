<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\TenantRoleService;
use App\Core\Tenancy\GymContext;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\ClassEnrollment;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReleaseController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function updateBranch(Request $request, Branch $branch)
    {
        $data = $request->validate(['name' => 'required|max:255', 'phone' => 'nullable|max:30', 'manager_name' => 'nullable|max:255', 'manager_mobile' => 'nullable|max:30', 'address' => 'nullable|max:2000']);
        $branch->update($data);
        $this->log('branch.updated', $request, $branch, ['fields' => array_keys($data)]);

        return back()->with('status', 'اطلاعات شعبه به‌روزرسانی شد.');
    }

    public function branchStatus(Request $request, Branch $branch)
    {
        $data = $request->validate(['status' => 'required|in:active,inactive', 'replacement_branch_id' => 'nullable|integer']);
        if ($data['status'] === 'inactive') {
            if (Branch::where('status', 'active')->whereKeyNot($branch->id)->doesntExist()) {
                throw ValidationException::withMessages(['status' => 'حداقل یک شعبه فعال باید باقی بماند.']);
            }
            if ($branch->is_system_default) {
                $replacement = Branch::where('status', 'active')->whereKey($data['replacement_branch_id'] ?? 0)->first();
                if (! $replacement) {
                    throw ValidationException::withMessages(['replacement_branch_id' => 'یک شعبه فعال جایگزین انتخاب کنید.']);
                }
                DB::transaction(function () use ($branch, $replacement) {
                    $branch->update(['is_system_default' => false, 'status' => 'inactive']);
                    $replacement->update(['is_system_default' => true]);
                });
            } else {
                $branch->update(['status' => 'inactive']);
            }
        } else {
            $branch->update(['status' => 'active']);
        }
        $this->log('branch.status_changed', $request, $branch, ['status' => $data['status']]);

        return back()->with('status', 'وضعیت شعبه تغییر کرد.');
    }

    public function defaultBranch(Request $request, Branch $branch)
    {
        if ($branch->status !== 'active') {
            throw ValidationException::withMessages(['branch' => 'شعبه پیش‌فرض باید فعال باشد.']);
        }
        DB::transaction(function () use ($branch) {
            Branch::query()->update(['is_system_default' => false]);
            $branch->update(['is_system_default' => true]);
        });
        $this->log('branch.default_changed', $request, $branch);

        return back()->with('status', 'شعبه پیش‌فرض تغییر کرد.');
    }

    public function updatePlan(Request $request, MembershipPlan $plan)
    {
        $gym = app(GymContext::class)->id();
        $data = $request->validate(['name' => 'required|max:255', 'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'description' => 'nullable|max:2000', 'duration_days' => 'required|integer|min:1|max:3650', 'session_limit' => 'nullable|integer|min:1', 'price' => 'required|decimal:0,2|min:0', 'currency' => 'required|size:3', 'sort_order' => 'nullable|integer|min:0']);
        $plan->update($data);
        $this->log('membership_plan.updated', $request, $plan, ['fields' => array_keys($data)]);

        return back()->with('status', 'طرح به‌روزرسانی شد؛ عضویت‌های قبلی بدون تغییر باقی ماندند.');
    }

    public function planStatus(Request $request, MembershipPlan $plan)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $plan->update(['is_active' => (bool) $data['is_active']]);
        $this->log('membership_plan.status_changed', $request, $plan, $data);

        return back()->with('status', 'وضعیت طرح تغییر کرد.');
    }

    public function updateCoach(Request $request, Coach $coach)
    {
        $data = $this->coachData($request, $coach);
        $this->replaceImage($request, $coach, 'avatar', 'avatar_path', 'coaches');
        $coach->update($data);
        $this->log('coach.updated', $request, $coach, ['fields' => array_keys($data)]);

        return back()->with('status', 'مربی به‌روزرسانی شد.');
    }

    public function coachStatus(Request $request, Coach $coach)
    {
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $coach->update($data);
        $this->log('coach.status_changed', $request, $coach, $data);

        return back()->with('status', 'وضعیت مربی تغییر کرد.');
    }

    public function coachDetail(Coach $coach, TenantRoleService $roles)
    {
        $coach->load([
            'branch',
            'user',
            'memberAssignments' => fn ($q) => $q->where('is_active', true)->with('member.branch'),
            'classes.sessions' => fn ($q) => $q->where('starts_at', '>=', now())->orderBy('starts_at')->limit(20),
        ]);

        return view('commercial.coaches.show', ['coach' => $coach, 'isManager' => $roles->isManager(auth()->user())]);
    }

    public function updateClass(Request $request, GymClass $class)
    {
        $data = $this->classData($request);
        $maxEnrollment = ClassEnrollment::whereHas('session', fn ($q) => $q->where('gym_class_id', $class->id))->where('status', 'enrolled')->selectRaw('COUNT(*) count')->groupBy('class_session_id')->orderByDesc('count')->value('count') ?? 0;
        if ($data['capacity'] < $maxEnrollment) {
            throw ValidationException::withMessages(['capacity' => "ظرفیت نمی‌تواند کمتر از {$maxEnrollment} ثبت‌نام فعال باشد."]);
        }
        DB::transaction(function () use ($class, $data) {
            $class->update(collect($data)->except(['weekday', 'starts_at'])->all());
            $class->schedules()->updateOrCreate(['gym_class_id' => $class->id], ['weekday' => $data['weekday'], 'starts_at' => $data['starts_at'], 'status' => $class->status]);
        });
        $this->log('class.updated', $request, $class, ['fields' => array_keys($data)]);

        return back()->with('status', 'کلاس به‌روزرسانی شد.');
    }

    public function classStatus(Request $request, GymClass $class)
    {
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        DB::transaction(function () use ($class, $data) {
            $class->update($data);
            $class->schedules()->update(['status' => $data['status']]);
        });
        $this->log('class.status_changed', $request, $class, $data);

        return back()->with('status', 'وضعیت کلاس تغییر کرد.');
    }

    public function classDetail(GymClass $class)
    {
        $class->load(['branch', 'coach', 'schedules', 'sessions' => fn ($q) => $q->with('enrollments.member')->orderByDesc('starts_at')->limit(30)]);

        return view('commercial.classes.show', compact('class'));
    }

    public function cancelSession(Request $request, ClassSession $session, CommercialWorkflows $workflows)
    {
        if ($session->starts_at->isPast()) {
            throw ValidationException::withMessages(['session' => 'جلسه تاریخی قابل لغو نیست.']);
        }
        $session->update(['status' => 'cancelled']);
        $this->log('class_session.cancelled', $request, $session);
        $workflows->notifyClassSession($session, 'cancelled');

        return back()->with('status', 'جلسه لغو شد و سابقه آن حفظ شد.');
    }

    public function removeEnrollment(Request $request, ClassEnrollment $enrollment)
    {
        $enrollment->update(['status' => 'cancelled']);
        $this->log('class.enrollment_removed', $request, $enrollment);

        return back()->with('status', 'ثبت‌نام لغو شد و سابقه آن حفظ شد.');
    }

    public function uploadMemberAvatar(Request $request, Member $member)
    {
        $request->validate(['avatar' => $this->imageRules()]);
        $this->replaceImage($request, $member, 'avatar', 'avatar_path', 'members');
        $this->log('member.avatar_updated', $request, $member);

        return back()->with('status', 'تصویر عضو ذخیره شد.');
    }

    public function memberImage(Member $member)
    {
        abort_unless($member->avatar_path && Storage::disk('public')->exists($member->avatar_path), 404);

        return Storage::disk('public')->response($member->avatar_path);
    }

    public function coachImage(Coach $coach)
    {
        abort_unless($coach->avatar_path && Storage::disk('public')->exists($coach->avatar_path), 404);

        return Storage::disk('public')->response($coach->avatar_path);
    }

    public function exportMembers(Request $request)
    {
        $query = $this->memberFilter($request)->with(['branch', 'memberships.plan']);

        return response()->streamDownload(function () use ($query) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['کد عضویت', 'نام', 'نام خانوادگی', 'موبایل', 'ایمیل', 'شعبه', 'وضعیت', 'تاریخ عضویت', 'طرح جاری', 'شروع عضویت', 'پایان عضویت', 'مانده بدهی']);
            $query->chunk(200, function ($members) use ($out) {
                foreach ($members as $m) {
                    $membership = $m->memberships->sortByDesc('ends_at')->first();
                    fputcsv($out, array_map([$this, 'safeCsv'], [$m->membership_code, $m->first_name, $m->last_name, $m->mobile, $m->email, $m->branch?->name, $m->status, $m->joined_at?->format('Y-m-d'), $membership?->plan?->name, $membership?->starts_at?->format('Y-m-d'), $membership?->ends_at?->format('Y-m-d'), $membership ? bcsub((string) $membership->payable_amount, (string) $membership->paid_amount, 2) : '0.00']));
                }
            });
            fclose($out);
        }, 'members-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importForm()
    {
        return view('commercial.members.import');
    }

    public function importTemplate()
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            $f = fopen('php://output', 'w');
            fputcsv($f, ['membership_code', 'first_name', 'last_name', 'mobile', 'email', 'branch', 'status', 'joined_at']);
            fputcsv($f, ['GYM-1001', 'علی', 'رضایی', '09120000000', 'ali@example.test', 'شعبه مرکزی', 'active', now()->format('Y-m-d')]);
            fclose($f);
        }, 'member-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function previewImport(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimetypes:text/plain,text/csv,application/csv|max:2048']);
        [$rows,$errors] = $this->parseImport($request->file('csv')->getRealPath());
        $request->session()->put('member_import_preview', $rows);

        return view('commercial.members.import', compact('rows', 'errors'));
    }

    public function confirmImport(Request $request)
    {
        $rows = $request->session()->pull('member_import_preview', []);
        abort_if(! $rows, 422, 'پیش‌نمایش واردات منقضی شده است.');
        $created = 0;
        $skipped = 0;
        $failed = [];
        foreach (array_chunk($rows, 100) as $chunk) {
            foreach ($chunk as $row) {
                if (! empty($row['_errors'])) {
                    $failed[] = $row;

                    continue;
                }
                if (Member::where('membership_code', $row['membership_code'])->orWhere(fn ($q) => $q->whereNotNull('email')->where('email', $row['email']))->exists()) {
                    $skipped++;

                    continue;
                }
                Member::create($row + ['branch_id' => $row['_branch_id'], 'public_token' => str()->random(48), 'created_by' => $request->user()->id]);
                $created++;
            }
        }
        $this->audit->record('members.imported', $request, $request->user(), app(GymContext::class)->id(), null, compact('created', 'skipped') + ['failed' => count($failed)]);

        $request->session()->put('member_import_errors', $failed);

        return redirect()->route('tenant.members.import')->with('status', "ایجاد: {$created}، تکراری: {$skipped}، ناموفق: ".count($failed))->with('has_import_errors', count($failed) > 0);
    }

    public function importErrors(Request $request)
    {
        $rows = $request->session()->get('member_import_errors', []);
        abort_if(! $rows, 404);

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            $file = fopen('php://output', 'w');
            fputcsv($file, ['membership_code', 'first_name', 'last_name', 'mobile', 'email', 'branch', 'status', 'joined_at', 'errors']);
            foreach ($rows as $row) {
                fputcsv($file, array_map([$this, 'safeCsv'], [$row['membership_code'], $row['first_name'], $row['last_name'], $row['mobile'], $row['email'], $row['branch'], $row['status'], $row['joined_at'], implode('|', $row['_errors'])]));
            }
            fclose($file);
        }, 'member-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function settings(Request $request)
    {
        return view('commercial.settings', ['gym' => app(GymContext::class)->gym(), 'branches' => Branch::where('status', 'active')->get()]);
    }

    public function updateSettings(Request $request)
    {
        $gym = app(GymContext::class)->gym();
        $data = $request->validate(['timezone' => 'required|timezone', 'currency' => 'required|size:3', 'default_branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym->id)->where('status', 'active'))], 'expiry_warning_days' => 'required|integer|min:1|max:365', 'membership_code_prefix' => 'required|alpha_dash|max:12', 'duplicate_checkin_minutes' => 'required|integer|min:1|max:1440', 'qr_checkin_enabled' => 'nullable|boolean', 'expired_checkin_policy' => 'required|in:block,allow', 'logo' => 'nullable|file|mimetypes:image/jpeg,image/png,image/webp|max:2048']);
        $settings = $gym->settings_json ?? [];
        foreach (['expiry_warning_days', 'membership_code_prefix', 'duplicate_checkin_minutes', 'expired_checkin_policy'] as $key) {
            $settings[$key] = $data[$key];
        }
        $settings['qr_checkin_enabled'] = $request->boolean('qr_checkin_enabled');
        if ($request->hasFile('logo')) {
            $old = $settings['logo_path'] ?? null;
            $settings['logo_path'] = $request->file('logo')->store("gyms/{$gym->id}/logo", 'public');
            if ($old && str_starts_with($old, "gyms/{$gym->id}/")) {
                Storage::disk('public')->delete($old);
            }
        }
        DB::transaction(function () use ($gym, $data, $settings) {
            $gym->update(['timezone' => $data['timezone'], 'currency' => strtoupper($data['currency']), 'settings_json' => $settings]);
            Branch::query()->update(['is_system_default' => false]);
            Branch::findOrFail($data['default_branch_id'])->update(['is_system_default' => true]);
        });
        $this->audit->record('gym.settings_updated', $request, $request->user(), $gym->id, $gym, ['keys' => array_keys($data)]);

        return back()->with('status', 'تنظیمات ذخیره و فعال شد.');
    }

    public function gymLogo()
    {
        $path = app(GymContext::class)->gym()->settings_json['logo_path'] ?? null;
        abort_unless($path && str_starts_with($path, 'gyms/'.app(GymContext::class)->id().'/') && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['type' => 'nullable|in:members,attendance,financial,classes', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'branch_id' => 'nullable|integer', 'status' => 'nullable|string|max:30']);
        $type = $data['type'] ?? 'members';
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $branch = $data['branch_id'] ?? null;
        $metrics = match ($type) {
            'attendance' => ['کل ورود' => Attendance::whereBetween('checked_in_at', ["$from 00:00:00", "$to 23:59:59"])->when($branch, fn ($q) => $q->where('branch_id', $branch))->count(), 'مراجعه باز' => Attendance::whereNull('checked_out_at')->when($branch, fn ($q) => $q->where('branch_id', $branch))->count(), 'اعضای یکتا' => Attendance::whereBetween('checked_in_at', ["$from 00:00:00", "$to 23:59:59"])->distinct()->count('member_id')],
            'financial' => ['دریافتی' => Payment::where('status', 'completed')->whereBetween('paid_at', ["$from 00:00:00", "$to 23:59:59"])->when($branch, fn ($q) => $q->where('branch_id', $branch))->sum('amount'), 'باطل‌شده' => Payment::where('status', 'voided')->whereBetween('paid_at', ["$from 00:00:00", "$to 23:59:59"])->when($branch, fn ($q) => $q->where('branch_id', $branch))->sum('amount'), 'مانده بدهی' => MemberMembership::when($branch, fn ($q) => $q->where('branch_id', $branch))->sum(DB::raw('payable_amount - paid_amount'))],
            'classes' => ['ثبت‌نام فعال' => ClassEnrollment::where('status', 'enrolled')->whereHas('session', fn ($q) => $q->whereBetween('starts_at', ["$from 00:00:00", "$to 23:59:59"]))->count(), 'جلسه لغوشده' => ClassSession::where('status', 'cancelled')->whereBetween('starts_at', ["$from 00:00:00", "$to 23:59:59"])->count(), 'جلسه برنامه‌ریزی‌شده' => ClassSession::where('status', 'scheduled')->whereBetween('starts_at', ["$from 00:00:00", "$to 23:59:59"])->count()],
            default => ['فعال' => Member::where('status', 'active')->when($branch, fn ($q) => $q->where('branch_id', $branch))->count(), 'غیرفعال' => Member::where('status', 'inactive')->when($branch, fn ($q) => $q->where('branch_id', $branch))->count(), 'عضو جدید' => Member::whereBetween('joined_at', [$from, $to])->when($branch, fn ($q) => $q->where('branch_id', $branch))->count(), 'بدون عضویت فعال' => Member::whereDoesntHave('memberships', fn ($q) => $q->whereDate('starts_at', '<=', $to)->whereDate('ends_at', '>=', $to)->whereNotIn('status', ['cancelled']))->when($branch, fn ($q) => $q->where('branch_id', $branch))->count()],
        };
        $details = $this->reportQuery($type, $from, $to, $branch, $data['status'] ?? null)->paginate(25)->withQueryString();

        return view('commercial.reports.index', compact('type', 'from', 'to', 'branch', 'metrics', 'details') + ['branches' => Branch::all()]);
    }

    public function exportReport(Request $request)
    {
        $type = $request->validate(['type' => 'required|in:members,attendance,financial,classes', 'from' => 'required|date', 'to' => 'required|date', 'branch_id' => 'nullable|integer', 'status' => 'nullable|string'])['type'];
        $rows = $this->reportQuery($type, $request->from, $request->to, $request->branch_id, $request->status)->get();

        return response()->streamDownload(function () use ($rows, $type) {
            echo "\xEF\xBB\xBF";
            $f = fopen('php://output', 'w');
            fputcsv($f, ['نوع', 'شناسه', 'عنوان', 'تاریخ', 'وضعیت', 'مقدار']);
            foreach ($rows as $row) {
                fputcsv($f, array_map([$this, 'safeCsv'], [$type, $row->id, $row->report_title, $row->report_date, $row->report_status, $row->report_value]));
            }fclose($f);
        }, "report-{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportQuery(string $type, string $from, string $to, ?int $branch, ?string $status)
    {
        return match ($type) {
            'attendance' => Attendance::with('member')->whereBetween('checked_in_at', ["$from 00:00:00", "$to 23:59:59"])->when($branch, fn ($q) => $q->where('branch_id', $branch))->when($status === 'open', fn ($q) => $q->whereNull('checked_out_at'))->select('*')->selectRaw("'attendance' report_title, checked_in_at report_date, CASE WHEN checked_out_at IS NULL THEN 'open' ELSE 'closed' END report_status, 1 report_value")->latest('checked_in_at'),
            'financial' => Payment::with('member')->whereBetween('paid_at', ["$from 00:00:00", "$to 23:59:59"])->when($branch, fn ($q) => $q->where('branch_id', $branch))->when($status, fn ($q, $v) => $q->where('status', $v))->select('*')->selectRaw('payment_method report_title, paid_at report_date, status report_status, amount report_value')->latest('paid_at'),
            'classes' => ClassSession::with('gymClass')->whereBetween('starts_at', ["$from 00:00:00", "$to 23:59:59"])->when($status, fn ($q, $v) => $q->where('status', $v))->select('*')->selectRaw("'class' report_title, starts_at report_date, status report_status, 1 report_value")->latest('starts_at'),
            default => Member::with('branch')->whereBetween('joined_at', [$from, $to])->when($branch, fn ($q) => $q->where('branch_id', $branch))->when($status, fn ($q, $v) => $q->where('status', $v))->select('*')->selectRaw('membership_code report_title, joined_at report_date, status report_status, 1 report_value')->latest('joined_at'),
        };
    }

    private function coachData(Request $r, ?Coach $coach = null): array
    {
        $gym = app(GymContext::class)->id();

        return $r->validate(['branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'mobile' => 'required|max:30', 'email' => 'nullable|email', 'specialty' => 'nullable|max:255', 'coach_type' => ['required', Rule::in(['training', 'nutrition', 'both'])], 'biography' => 'nullable|max:3000', 'hire_date' => 'nullable|date', 'compensation_notes' => 'nullable|max:3000', 'user_id' => ['nullable', Rule::exists('gym_user', 'user_id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active')), Rule::unique('coaches', 'user_id')->where('gym_id', $gym)->ignore($coach?->id)]]);
    }

    private function classData(Request $r): array
    {
        $gym = app(GymContext::class)->id();

        return $r->validate(['branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'coach_id' => ['required', Rule::exists('coaches', 'id')->where(fn ($q) => $q->where('gym_id', $gym)->where('status', 'active'))], 'name' => 'required|max:255', 'description' => 'nullable|max:2000', 'capacity' => 'required|integer|min:1|max:500', 'duration_minutes' => 'required|integer|min:15|max:480', 'level' => 'nullable|max:30', 'weekday' => 'required|integer|min:0|max:6', 'starts_at' => 'required|date_format:H:i']);
    }

    private function imageRules(): array
    {
        return ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
    }

    private function replaceImage(Request $r, $model, string $input, string $column, string $folder): void
    {
        if (! $r->hasFile($input)) {
            return;
        } $r->validate([$input => $this->imageRules()]);
        $old = $model->{$column};
        $path = $r->file($input)->store("gyms/{$model->gym_id}/{$folder}", 'public');
        $model->update([$column => $path]);
        if ($old && str_starts_with($old, "gyms/{$model->gym_id}/")) {
            Storage::disk('public')->delete($old);
        }
    }

    private function memberFilter(Request $r)
    {
        return Member::query()->when($r->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('membership_code', 'like', "%$v%")->orWhere('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%")))->when($r->status, fn ($q, $v) => $q->where('status', $v))->when($r->branch_id, fn ($q, $v) => $q->where('branch_id', $v));
    }

    public function safeCsv($value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }

    private function parseImport(string $path): array
    {
        $f = fopen($path, 'r');
        $header = fgetcsv($f);
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        } $required = ['membership_code', 'first_name', 'last_name', 'mobile', 'email', 'branch', 'status', 'joined_at'];
        if ($header !== $required) {
            throw ValidationException::withMessages(['csv' => 'سرستون‌های CSV معتبر نیستند. از فایل نمونه استفاده کنید.']);
        } $rows = [];
        $errors = [];
        $limit = (int) config('modules.member_import_max_rows', 500);
        while (($values = fgetcsv($f)) !== false) {
            if (count($rows) >= $limit) {
                throw ValidationException::withMessages(['csv' => "حداکثر {$limit} ردیف مجاز است."]);
            } $row = array_combine($required, array_pad($values, count($required), ''));
            $row = array_map(fn ($v) => trim((string) $v), $row);
            $branch = Branch::where('name', $row['branch'])->where('status', 'active')->first();
            $row['_errors'] = [];
            if (! $row['membership_code'] || ! $row['first_name'] || ! $row['last_name'] || ! $row['mobile']) {
                $row['_errors'][] = 'فیلدهای الزامی ناقص است';
            } if (! in_array($row['status'], ['active', 'inactive'], true)) {
                $row['_errors'][] = 'وضعیت نامعتبر';
            } if ($row['email'] && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $row['_errors'][] = 'ایمیل نامعتبر';
            } if (! $branch) {
                $row['_errors'][] = 'شعبه فعال پیدا نشد';
            } if (! strtotime($row['joined_at'])) {
                $row['_errors'][] = 'تاریخ نامعتبر';
            } $row['_branch_id'] = $branch?->id;
            $row['email'] = $row['email'] ?: null;
            $rows[] = $row;
        } fclose($f);

        return [$rows, $errors];
    }

    private function log(string $event, Request $request, $subject, array $meta = []): void
    {
        $this->audit->record($event, $request, $request->user(), $subject->gym_id, $subject, $meta);
    }
}

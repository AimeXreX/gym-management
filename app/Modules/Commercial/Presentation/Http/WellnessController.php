<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Core\Authorization\TenantRoleService;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Coach;
use App\Models\CoachRequest;
use App\Models\Member;
use App\Models\NutritionPlan;
use App\Models\ProgressPhoto;
use App\Models\WorkoutProgram;
use App\Models\WorkoutProgramDay;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Modules\Commercial\Application\CommercialWorkflows;
use App\Modules\Commercial\Application\WellnessAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WellnessController extends Controller
{
    public function index(Request $request, WellnessAccess $access, ?Member $member = null)
    {
        $coach = $access->coachFor($request->user());
        $roster = $coach
            ? $coach->memberAssignments()->where('is_active', true)->with('member')->get()->pluck('member')->unique('id')->values()
            : collect();
        $member ??= $access->memberFor($request->user()) ?? $roster->first();

        abort_unless($member && $access->canView($request->user(), $member), 403);
        $owner = $member->user_id === $request->user()->id;

        return view('commercial.wellness.index', [
            'member' => $member,
            'isOwner' => $owner,
            'roster' => $owner ? collect() : $roster,
            'canTraining' => $access->canCoachDomain($request->user(), $member, 'training'),
            'canNutrition' => $access->canCoachDomain($request->user(), $member, 'nutrition'),
            'plans' => $member->nutritionPlans()->with('items')->latest('starts_on')->get(),
            'foodLogs' => $member->foodLogs()->when(! $owner, fn ($q) => $q->where('coach_visibility', true))->latest('consumed_at')->limit(30)->get(),
            'sessions' => $member->workoutSessions()->with('sets')->latest('performed_on')->limit(20)->get(),
            'programs' => $member->workoutPrograms()->with(['days.sets', 'days.sessions', 'feedback.user'])->latest('starts_on')->get(),
            'requests' => $member->coachRequests()->with('coach')->latest()->get(),
            'coaches' => Coach::where('status', 'active')->get(),
            'attendances' => $member->attendances()->latest('checked_in_at')->limit(20)->get(),
            'measurements' => $member->bodyMeasurements()->latest('measured_on')->limit(24)->get()->sortBy('measured_on')->values(),
            'photos' => $member->progressPhotos()->when(! $owner, fn ($q) => $q->where('visibility', 'coaches'))->latest('captured_on')->limit(12)->get(),
            'injuries' => $member->injuryRecords()->when(! $owner, fn ($q) => $q->where('training_coach_visibility', true))->latest('occurred_on')->get(),
        ]);
    }

    public function dashboard(Request $request, WellnessAccess $access, CommercialWorkflows $flow)
    {
        $coach = $access->coachFor($request->user());
        abort_unless($coach, 403);

        $assignments = $coach->memberAssignments()->where('is_active', true)->get();
        $roster = Member::query()
            ->whereIn('id', $assignments->pluck('member_id')->unique())
            ->with(['bodyMeasurements' => fn ($q) => $q->latest('measured_on')->limit(1)])
            ->with(['workoutPrograms' => fn ($q) => $q->where('status', 'active')->latest('starts_on')->limit(1)])
            ->orderBy('first_name')
            ->get();
        $domains = $assignments->groupBy('member_id')->map(fn ($group) => $group->pluck('domain')->unique()->values());
        $trainingMemberIds = $assignments->where('domain', 'training')->pluck('member_id')->unique();
        $nutritionMemberIds = $assignments->where('domain', 'nutrition')->pluck('member_id')->unique();

        $todayClasses = ClassSession::query()
            ->whereDate('starts_at', today())
            ->whereHas('gymClass', fn ($q) => $q->where('coach_id', $coach->id))
            ->with(['gymClass' => fn ($q) => $q->with('branch')])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])
            ->orderBy('starts_at')
            ->get();

        return view('commercial.wellness.dashboard', [
            'coach' => $coach,
            'roster' => $roster,
            'domains' => $domains,
            'canTraining' => $coach->handlesDomain('training'),
            'canNutrition' => $coach->handlesDomain('nutrition'),
            'trainingMembersCount' => $trainingMemberIds->count(),
            'nutritionMembersCount' => $nutritionMemberIds->count(),
            'activeProgramsCount' => WorkoutProgram::query()->whereIn('member_id', $trainingMemberIds)->where('status', 'active')->count(),
            'activeNutritionPlansCount' => NutritionPlan::query()->whereIn('member_id', $nutritionMemberIds)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->count(),
            'templatesCount' => $coach->handlesDomain('training') ? WorkoutTemplate::query()->where(fn ($q) => $q->whereNull('coach_id')->orWhere('coach_id', $coach->id))->count() : 0,
            'pendingRequests' => $flow->pendingReviewableRequests($coach),
            'todayClasses' => $todayClasses,
            'upcomingClasses' => ClassSession::query()
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', now())
                ->where('starts_at', '<', now()->addDays(7))
                ->whereHas('gymClass', fn ($q) => $q->where('coach_id', $coach->id))
                ->with(['gymClass' => fn ($q) => $q->with('branch')])
                ->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function requests(Request $request, WellnessAccess $access, CommercialWorkflows $flow)
    {
        $coach = $access->coachFor($request->user());
        abort_unless($coach, 403);

        return view('commercial.wellness.requests', [
            'coach' => $coach,
            'pendingRequests' => $flow->pendingReviewableRequests($coach),
            'history' => CoachRequest::query()->where('reviewed_by', $request->user()->id)->with(['member', 'coach'])->latest()->limit(20)->get(),
        ]);
    }

    public function food(Request $request, Member $member)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        $data = $request->validate(['consumed_at' => ['required', 'date'], 'meal_name' => ['nullable', 'string', 'max:80'], 'foods' => ['required', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:1000'], 'coach_visibility' => ['nullable', 'boolean']]);
        $member->foodLogs()->create($data + ['coach_visibility' => $request->boolean('coach_visibility')]);

        return back()->with('status', 'خوراک روزانه ثبت شد.');
    }

    public function plan(Request $request, Member $member, WellnessAccess $access)
    {
        abort_unless($access->canCoachDomain($request->user(), $member, 'nutrition'), 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'goal' => ['nullable', 'string', 'max:200'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.meal_name' => ['required', 'string', 'max:80'], 'items.*.suggested_at' => ['nullable', 'date_format:H:i'], 'items.*.foods' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($data, $member, $request) {
            $plan = NutritionPlan::create(['member_id' => $member->id, 'created_by' => $request->user()->id, 'title' => $data['title'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null, 'goal' => $data['goal'] ?? null, 'notes' => $data['notes'] ?? null]);
            foreach ($data['items'] as $i => $item) {
                $plan->items()->create($item + ['sort_order' => $i]);
            }
        });

        return back()->with('status', 'برنامه غذایی ذخیره شد.');
    }

    public function workout(Request $request, Member $member, WellnessAccess $access)
    {
        abort_unless($member->user_id === $request->user()->id || $access->canCoachDomain($request->user(), $member, 'training'), 403);
        $programIds = $member->workoutPrograms()->pluck('id');
        $data = $request->validate(['performed_on' => ['required', 'date'], 'title' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000'], 'program_day_id' => ['nullable', 'integer', Rule::exists('workout_program_days', 'id')->where(fn ($q) => $q->whereIn('workout_program_id', $programIds))], 'sets' => ['required', 'array', 'min:1', 'max:60'], 'sets.*.exercise_name' => ['required', 'string', 'max:150'], 'sets.*.set_number' => ['required', 'integer', 'min:1', 'max:100'], 'sets.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'], 'sets.*.repetitions' => ['nullable', 'integer', 'min:0', 'max:10000']]);
        $programDay = isset($data['program_day_id']) ? WorkoutProgramDay::find($data['program_day_id']) : null;
        DB::transaction(function () use ($data, $member, $request, $programDay) {
            $session = WorkoutSession::create(['member_id' => $member->id, 'recorded_by' => $request->user()->id, 'performed_on' => $data['performed_on'], 'title' => $data['title'] ?? null, 'notes' => $data['notes'] ?? null, 'program_id' => $programDay?->workout_program_id, 'program_day_id' => $programDay?->id]);
            foreach ($data['sets'] as $set) {
                $session->sets()->create($set);
            }
        });

        return back()->with('status', 'رکورد جلسه تمرین ثبت شد.');
    }

    public function workoutProgram(Request $request, Member $member, WellnessAccess $access, CommercialWorkflows $flow)
    {
        abort_unless($member->user_id === $request->user()->id || $access->canCoachDomain($request->user(), $member, 'training'), 403);
        $data = $this->programData($request);
        $flow->createWorkoutProgram($member, $access->coachFor($request->user()), $data, $request);

        return back()->with('status', 'برنامه تمرینی ذخیره شد.');
    }

    public function workoutProgramStatus(Request $request, WorkoutProgram $program, WellnessAccess $access, CommercialWorkflows $flow)
    {
        $member = $program->member;
        $isCoach = $access->canCoachDomain($request->user(), $member, 'training');
        $isOwnProgram = $member->user_id === $request->user()->id && $program->coach_id === null;
        abort_unless($isCoach || $isOwnProgram, 403);
        $data = $request->validate(['status' => ['required', 'in:draft,active,completed,archived']]);
        $flow->updateWorkoutProgramStatus($program, $data['status'], $request);

        return back()->with('status', 'وضعیت برنامه تغییر کرد.');
    }

    public function editWorkoutProgram(Request $request, WorkoutProgram $program, WellnessAccess $access)
    {
        $member = $program->member;
        abort_unless($access->canCoachDomain($request->user(), $member, 'training') || ($member->user_id === $request->user()->id && $program->coach_id === null), 403);
        $program->load('days.sets');

        return view('commercial.wellness.program-edit', ['program' => $program, 'member' => $member]);
    }

    public function updateWorkoutProgram(Request $request, WorkoutProgram $program, WellnessAccess $access, CommercialWorkflows $flow)
    {
        $member = $program->member;
        abort_unless($access->canCoachDomain($request->user(), $member, 'training') || ($member->user_id === $request->user()->id && $program->coach_id === null), 403);
        $data = $this->programData($request);
        $flow->updateWorkoutProgram($program, $data, $request);

        return redirect()->route('tenant.wellness.index', $member)->with('status', 'برنامه تمرینی به‌روزرسانی شد.');
    }

    public function editNutritionPlan(Request $request, NutritionPlan $plan, WellnessAccess $access)
    {
        $member = $plan->member;
        abort_unless($access->canCoachDomain($request->user(), $member, 'nutrition'), 403);
        $plan->load('items');

        return view('commercial.wellness.plan-edit', ['plan' => $plan, 'member' => $member]);
    }

    public function updateNutritionPlan(Request $request, NutritionPlan $plan, WellnessAccess $access, CommercialWorkflows $flow)
    {
        $member = $plan->member;
        abort_unless($access->canCoachDomain($request->user(), $member, 'nutrition'), 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'goal' => ['nullable', 'string', 'max:200'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.meal_name' => ['nullable', 'string', 'max:80'], 'items.*.suggested_at' => ['nullable', 'date_format:H:i'], 'items.*.foods' => ['nullable', 'string', 'max:2000']]);
        $items = [];
        foreach ($data['items'] as $item) {
            if (empty($item['meal_name']) && empty($item['foods'])) {
                continue;
            }
            if (empty($item['meal_name'])) {
                throw ValidationException::withMessages(['items' => 'نام وعده لازم است.']);
            }
            if (empty($item['foods'])) {
                throw ValidationException::withMessages(['items' => 'مواد غذایی لازم است.']);
            }
            $items[] = $item;
        }
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'حداقل یک وعده لازم است.']);
        }
        $data['items'] = $items;
        $flow->updateNutritionPlan($plan, $data, $request);

        return redirect()->route('tenant.wellness.index', $member)->with('status', 'برنامه غذایی به‌روزرسانی شد.');
    }

    public function programFeedback(Request $request, WorkoutProgram $program, WellnessAccess $access, CommercialWorkflows $flow)
    {
        $member = $program->member;
        abort_unless($member->user_id === $request->user()->id || $access->canCoachDomain($request->user(), $member, 'training'), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $flow->addProgramFeedback($program, $data['body'], $request);

        return back()->with('status', 'بازخورد ثبت شد.');
    }

    public function profile(Request $request, WellnessAccess $access)
    {
        $coach = $access->coachFor($request->user());
        abort_unless($coach, 403);

        return view('commercial.wellness.profile', ['coach' => $coach]);
    }

    public function updateProfile(Request $request, WellnessAccess $access)
    {
        $coach = $access->coachFor($request->user());
        abort_unless($coach, 403);
        $data = $request->validate(['mobile' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'specialty' => ['nullable', 'string', 'max:255'], 'biography' => ['nullable', 'string', 'max:3000'], 'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        if ($request->hasFile('avatar')) {
            $old = $coach->avatar_path;
            $data['avatar_path'] = $request->file('avatar')->store('gyms/'.$coach->gym_id.'/coaches', 'public');
            if ($old && str_starts_with($old, 'gyms/'.$coach->gym_id.'/')) {
                Storage::disk('public')->delete($old);
            }
        }
        unset($data['avatar']);
        $coach->update($data);

        return back()->with('status', 'پروفایل شما به‌روزرسانی شد.');
    }

    private function programData(Request $request): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'goal' => ['nullable', 'string', 'max:200'], 'notes' => ['nullable', 'string', 'max:2000'], 'days' => ['required', 'array', 'min:1', 'max:31'], 'days.*.day_number' => ['nullable', 'integer', 'min:1', 'max:366'], 'days.*.title' => ['nullable', 'string', 'max:100'], 'days.*.notes' => ['nullable', 'string', 'max:2000'], 'days.*.sets' => ['nullable', 'array', 'max:60'], 'days.*.sets.*.exercise_name' => ['nullable', 'string', 'max:150'], 'days.*.sets.*.set_number' => ['nullable', 'integer', 'min:1', 'max:100'], 'days.*.sets.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'], 'days.*.sets.*.repetitions' => ['nullable', 'integer', 'min:0', 'max:10000']]);
        $days = [];
        foreach ($data['days'] as $day) {
            $sets = [];
            foreach ($day['sets'] ?? [] as $set) {
                if (empty($set['exercise_name'])) {
                    continue;
                }
                if (empty($set['set_number'])) {
                    throw ValidationException::withMessages(['days' => 'برای هر حرکت شماره ست لازم است.']);
                }
                $sets[] = $set;
            }
            $day['sets'] = $sets;
            if (empty($day['title']) && empty($day['notes']) && empty($sets)) {
                continue;
            }
            if (empty($day['day_number'])) {
                throw ValidationException::withMessages(['days' => 'شماره روز لازم است.']);
            }
            $days[] = $day;
        }
        if (empty($days)) {
            throw ValidationException::withMessages(['days' => 'حداقل یک روز با حرکت لازم است.']);
        }
        $data['days'] = $days;

        return $data;
    }

    public function measurement(Request $request, Member $member, WellnessAccess $access)
    {
        abort_unless($member->user_id === $request->user()->id || $access->canCoach($request->user(), $member), 403);
        $data = $request->validate(['measured_on' => ['required', 'date'], 'weight_kg' => ['nullable', 'numeric', 'between:20,500'], 'body_fat_percent' => ['nullable', 'numeric', 'between:1,80'], 'waist_cm' => ['nullable', 'numeric', 'between:20,300'], 'chest_cm' => ['nullable', 'numeric', 'between:20,300'], 'arm_cm' => ['nullable', 'numeric', 'between:10,150'], 'thigh_cm' => ['nullable', 'numeric', 'between:10,200'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $member->bodyMeasurements()->updateOrCreate(['measured_on' => $data['measured_on']], $data + ['recorded_by' => $request->user()->id]);

        return back()->with('status', 'اندازه‌گیری ثبت شد.');
    }

    public function photo(Request $request, Member $member)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        $data = $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'captured_on' => ['required', 'date'], 'view_type' => ['required', Rule::in(['front', 'side', 'back'])], 'visibility' => ['required', Rule::in(['private', 'coaches'])], 'consent' => ['accepted']]);
        $file = $data['photo'];
        $path = 'gyms/'.$member->gym_id.'/members/'.$member->id.'/progress/'.Str::uuid().'.'.$file->extension();
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));
        $member->progressPhotos()->create(['uploaded_by' => $request->user()->id, 'captured_on' => $data['captured_on'], 'view_type' => $data['view_type'], 'path' => $path, 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'visibility' => $data['visibility'], 'consented_at' => now()]);

        return back()->with('status', 'تصویر پیشرفت به‌صورت خصوصی ذخیره شد.');
    }

    public function showPhoto(Request $request, ProgressPhoto $photo, WellnessAccess $access, TenantRoleService $roles)
    {
        $photo->loadMissing('member');
        $owner = $photo->member->user_id === $request->user()->id;
        $coach = $photo->visibility === 'coaches' && $access->canCoach($request->user(), $photo->member);
        $manager = $photo->visibility === 'coaches' && $roles->isManager($request->user());
        abort_unless($owner || $coach || $manager, 403);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, ['Content-Type' => $photo->mime_type, 'Cache-Control' => 'private, no-store', 'Content-Disposition' => 'inline']);
    }

    public function injury(Request $request, Member $member)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        $data = $request->validate(['body_area' => ['required', 'string', 'max:100'], 'title' => ['required', 'string', 'max:150'], 'severity' => ['required', Rule::in(['mild', 'moderate', 'severe'])], 'occurred_on' => ['required', 'date'], 'expected_recovery_on' => ['nullable', 'date', 'after_or_equal:occurred_on'], 'restrictions' => ['required', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'], 'training_coach_visibility' => ['nullable', 'boolean']]);
        $visible = $request->boolean('training_coach_visibility');
        $member->injuryRecords()->create($data + ['recorded_by' => $request->user()->id, 'training_coach_visibility' => $visible, 'consented_at' => $visible ? now() : null]);

        return back()->with('status', 'آسیب‌دیدگی و محدودیت تمرینی ثبت شد.');
    }

    public function checkIn(Request $request, Member $member, CommercialWorkflows $flow)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        abort_if($member->attendances()->whereNull('checked_out_at')->exists(), 422, 'یک ورود باز دارید.');
        $flow->checkIn($member, 'self', $request);

        return back()->with('status', 'زمان ورود ثبت شد.');
    }

    public function checkOut(Request $request, Member $member, CommercialWorkflows $flow)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        $attendance = $member->attendances()->whereNull('checked_out_at')->latest('checked_in_at')->firstOrFail();
        $flow->checkOut($attendance, $request);

        return back()->with('status', 'زمان خروج ثبت شد.');
    }
}

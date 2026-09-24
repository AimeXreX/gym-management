<?php

namespace App\Modules\Commercial\Application;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\TenantRoleService;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\CoachRequest;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\NutritionPlan;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkoutProgram;
use App\Models\WorkoutTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialWorkflows
{
    public function __construct(private AuditLogger $audit, private GymSettings $settings, private TenantRoleService $roles) {}

    public function createMember(array $data, Request $request): Member
    {
        return DB::transaction(function () use ($data, $request) {
            $data['mobile'] = $this->normalizeMobile($data['mobile']);
            if (empty($data['membership_code'])) {
                $prefix = strtoupper((string) $this->settings->get('membership_code_prefix', 'GYM'));
                $sequence = Member::query()->where('membership_code', 'like', $prefix.'-%')->count() + 1;
                do {
                    $data['membership_code'] = $prefix.'-'.str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT);
                } while (Member::query()->where('membership_code', $data['membership_code'])->exists());
            }
            $data['public_token'] = Str::random(48);
            $data['created_by'] = $request->user()->id;
            $member = Member::query()->create($data);
            $this->audit->record('member.created', $request, $request->user(), $member->gym_id, $member, ['membership_code' => $member->membership_code]);
            if (! empty($data['membership_plan_id'])) {
                $membership = $this->assignMembership($member, MembershipPlan::findOrFail($data['membership_plan_id']), $data, $request);
                if ((float) ($data['initial_payment'] ?? 0) > 0) {
                    $this->recordPayment($membership, (float) $data['initial_payment'], $data['payment_method'] ?? 'cash', null, $request);
                }
            }

            return $member;
        });
    }

    public function assignMembership(Member $member, MembershipPlan $plan, array $data, Request $request): MemberMembership
    {
        if (! $plan->is_active || ($plan->branch_id && $plan->branch_id !== $member->branch_id)) {
            throw ValidationException::withMessages(['membership_plan_id' => 'این طرح برای عضو قابل انتخاب نیست.']);
        }
        $discount = (float) ($data['discount_amount'] ?? 0);
        $price = (float) $plan->price;
        if ($discount < 0 || $discount > $price) {
            throw ValidationException::withMessages(['discount_amount' => 'تخفیف نامعتبر است.']);
        }
        $start = isset($data['starts_at']) ? now()->parse($data['starts_at'])->startOfDay() : now()->startOfDay();
        $latest = $member->memberships()->whereNotIn('status', ['cancelled'])->orderByDesc('ends_at')->first();
        if ($latest && $latest->ends_at->gte($start)) {
            $start = $latest->ends_at->copy()->addDay();
        }
        $membership = MemberMembership::query()->create([
            'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'branch_id' => $member->branch_id,
            'reference' => 'SUB-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
            'starts_at' => $start, 'ends_at' => $start->copy()->addDays($plan->duration_days - 1),
            'status' => $start->isFuture() ? 'pending' : 'active', 'agreed_price' => $price, 'discount_amount' => $discount,
            'payable_amount' => $price - $discount, 'paid_amount' => 0, 'session_limit' => $plan->session_limit,
            'created_by' => $request->user()->id, 'notes' => $data['membership_notes'] ?? null,
        ]);
        $this->audit->record($latest ? 'membership.renewed' : 'membership.created', $request, $request->user(), $member->gym_id, $membership, ['member_id' => $member->id, 'plan_id' => $plan->id]);

        return $membership;
    }

    public function recordPayment(MemberMembership $membership, float $amount, string $method, ?string $reference, Request $request): Payment
    {
        return DB::transaction(function () use ($membership, $amount, $method, $reference, $request) {
            $membership = MemberMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $remaining = (float) $membership->payable_amount - (float) $membership->paid_amount;
            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'مبلغ باید مثبت و حداکثر برابر مانده بدهی باشد.']);
            }
            $payment = Payment::query()->create(['member_id' => $membership->member_id, 'membership_id' => $membership->id, 'branch_id' => $membership->branch_id, 'amount' => $amount, 'payment_method' => $method, 'reference_number' => $reference, 'paid_at' => now(), 'status' => 'completed', 'received_by' => $request->user()->id]);
            $membership->increment('paid_amount', $amount);
            $this->audit->record('payment.recorded', $request, $request->user(), $membership->gym_id, $payment, ['membership_id' => $membership->id, 'amount' => $amount]);

            return $payment;
        });
    }

    public function checkIn(Member $member, string $method, Request $request, ?int $classSessionId = null): Attendance
    {
        return DB::transaction(function () use ($member, $method, $request, $classSessionId) {
            if ($method === 'qr' && ! $this->settings->get('qr_checkin_enabled', true)) {
                throw ValidationException::withMessages(['member' => 'ورود با QR در تنظیمات باشگاه غیرفعال است.']);
            }
            $membershipQuery = $member->memberships()->whereNotIn('status', ['cancelled', 'frozen'])->whereDate('starts_at', '<=', today())->orderByDesc('ends_at')->lockForUpdate();
            if ($this->settings->get('expired_checkin_policy', 'block') === 'block') {
                $membershipQuery->whereDate('ends_at', '>=', today());
            }
            $membership = $membershipQuery->first();
            if (! $membership) {
                throw ValidationException::withMessages(['member' => 'عضویت فعال برای این عضو وجود ندارد.']);
            }
            if ($membership->session_limit !== null && $membership->sessions_used >= $membership->session_limit) {
                throw ValidationException::withMessages(['member' => 'جلسات این عضویت به پایان رسیده است.']);
            }
            $window = (int) $this->settings->get('duplicate_checkin_minutes', 15);
            $duplicate = $member->attendances()->where('checked_in_at', '>=', now()->subMinutes($window))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['member' => 'برای این عضو در ۱۵ دقیقه اخیر ورود ثبت شده است.']);
            }
            $attendance = Attendance::query()->create(['member_id' => $member->id, 'branch_id' => $member->branch_id, 'membership_id' => $membership->id, 'class_session_id' => $classSessionId, 'checked_in_at' => now(), 'method' => $method, 'recorded_by' => $request->user()->id]);
            $member->update(['last_attended_at' => now()]);
            if ($membership->session_limit !== null) {
                $membership->increment('sessions_used');
            }
            $this->audit->record('attendance.checked_in', $request, $request->user(), $member->gym_id, $attendance, ['member_id' => $member->id, 'method' => $method]);

            return $attendance;
        });
    }

    public function voidPayment(Payment $payment, string $reason, Request $request): void
    {
        DB::transaction(function () use ($payment, $reason, $request) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'completed') {
                throw ValidationException::withMessages(['reason' => 'این پرداخت قبلاً باطل شده است.']);
            }
            $membership = $payment->membership()->lockForUpdate()->first();
            $payment->update(['status' => 'voided', 'voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $reason]);
            $membership?->decrement('paid_amount', $payment->amount);
            $this->audit->record('payment.voided', $request, $request->user(), $payment->gym_id, $payment, ['reason' => mb_substr($reason, 0, 200)]);
        });
    }

    public function checkOut(Attendance $attendance, Request $request): void
    {
        if ($attendance->checked_out_at) {
            throw ValidationException::withMessages(['attendance' => 'خروج این مراجعه قبلاً ثبت شده است.']);
        }
        $attendance->update(['checked_out_at' => now()]);
        $this->audit->record('attendance.checked_out', $request, $request->user(), $attendance->gym_id, $attendance, ['member_id' => $attendance->member_id]);
    }

    public function assignCoach(Member $member, Coach $coach, string $domain, bool $makeDefault, Request $request): CoachMemberAssignment
    {
        if (! in_array($domain, ['training', 'nutrition'], true)) {
            throw ValidationException::withMessages(['domain' => 'دامنه نامعتبر است.']);
        }
        if ($coach->status !== 'active') {
            throw ValidationException::withMessages(['coach_id' => 'مربی انتخاب‌شده فعال نیست.']);
        }
        if ($coach->coach_type !== 'both' && $coach->coach_type !== $domain) {
            throw ValidationException::withMessages(['coach_id' => 'این مربی برای دامنه انتخابی تعریف نشده است.']);
        }

        return DB::transaction(function () use ($member, $coach, $domain, $makeDefault, $request) {
            if ($makeDefault) {
                CoachMemberAssignment::query()
                    ->where('member_id', $member->id)
                    ->where('domain', $domain)
                    ->update(['is_default' => false]);
            }

            $assignment = CoachMemberAssignment::query()->updateOrCreate(
                ['coach_id' => $coach->id, 'member_id' => $member->id, 'domain' => $domain],
                ['is_active' => true, 'is_default' => $makeDefault]
            );

            $this->audit->record('coach.assigned', $request, $request->user(), $member->gym_id, $assignment, ['coach_id' => $coach->id, 'member_id' => $member->id, 'domain' => $domain, 'is_default' => $makeDefault]);

            return $assignment;
        });
    }

    public function unassignCoach(CoachMemberAssignment $assignment, Request $request): void
    {
        $assignment->update(['is_active' => false, 'is_default' => false]);
        $this->audit->record('coach.unassigned', $request, $request->user(), $assignment->gym_id, $assignment, ['coach_id' => $assignment->coach_id, 'member_id' => $assignment->member_id, 'domain' => $assignment->domain]);
    }

    public function setDefaultCoach(CoachMemberAssignment $assignment, Request $request): void
    {
        if (! $assignment->is_active) {
            throw ValidationException::withMessages(['assignment' => 'مربی باید فعال باشد تا پیش‌فرض شود.']);
        }
        DB::transaction(function () use ($assignment) {
            CoachMemberAssignment::query()
                ->where('member_id', $assignment->member_id)
                ->where('domain', $assignment->domain)
                ->update(['is_default' => false]);
            $assignment->update(['is_default' => true]);
        });
        $this->audit->record('coach.default_set', $request, $request->user(), $assignment->gym_id, $assignment, ['coach_id' => $assignment->coach_id, 'member_id' => $assignment->member_id, 'domain' => $assignment->domain]);
    }

    public function createWorkoutProgram(Member $member, ?Coach $coach, array $data, Request $request): WorkoutProgram
    {
        return DB::transaction(function () use ($member, $coach, $data, $request) {
            $program = WorkoutProgram::query()->create([
                'member_id' => $member->id,
                'coach_id' => $coach?->id,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'goal' => $data['goal'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['days'] as $day) {
                $dayModel = $program->days()->create([
                    'day_number' => $day['day_number'],
                    'title' => $day['title'] ?? null,
                    'notes' => $day['notes'] ?? null,
                ]);
                foreach ($day['sets'] ?? [] as $set) {
                    $dayModel->sets()->create($set);
                }
            }

            $this->audit->record('workout_program.created', $request, $request->user(), $member->gym_id, $program, ['member_id' => $member->id, 'coach_id' => $coach?->id, 'days' => count($data['days'])]);

            if ($coach && $member->user_id) {
                $this->notify($member->gym_id, $member->user_id, 'workout_program.created', 'برنامه تمرینی جدید', $coach->full_name.' برنامه «'.$data['title'].'» را برای شما نوشت.', route('tenant.wellness.index', $member));
            }

            return $program;
        });
    }

    public function updateWorkoutProgramStatus(WorkoutProgram $program, string $status, Request $request): void
    {
        if (! in_array($status, ['draft', 'active', 'completed', 'archived'], true)) {
            throw ValidationException::withMessages(['status' => 'وضعیت نامعتبر است.']);
        }
        $wasActive = $program->status === 'active';
        $program->update(['status' => $status]);
        $this->audit->record('workout_program.status_changed', $request, $request->user(), $program->gym_id, $program, ['status' => $status]);

        if ($status === 'active' && ! $wasActive && $program->coach_id && $program->member?->user_id) {
            $this->notify($program->gym_id, $program->member->user_id, 'workout_program.published', 'برنامه تمرینی شما فعال شد', 'مربی برنامه «'.$program->title.'» را منتشر کرد.', route('tenant.wellness.index', $program->member));
        }
    }

    public function updateWorkoutProgram(WorkoutProgram $program, array $data, Request $request): WorkoutProgram
    {
        return DB::transaction(function () use ($program, $data, $request) {
            $program->update([
                'title' => $data['title'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'goal' => $data['goal'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $program->days()->delete();
            foreach ($data['days'] as $day) {
                $dayModel = $program->days()->create([
                    'day_number' => $day['day_number'],
                    'title' => $day['title'] ?? null,
                    'notes' => $day['notes'] ?? null,
                ]);
                foreach ($day['sets'] ?? [] as $set) {
                    $dayModel->sets()->create($set);
                }
            }
            $this->audit->record('workout_program.updated', $request, $request->user(), $program->gym_id, $program, ['member_id' => $program->member_id, 'days' => count($data['days'])]);

            return $program;
        });
    }

    public function updateNutritionPlan(NutritionPlan $plan, array $data, Request $request): NutritionPlan
    {
        return DB::transaction(function () use ($plan, $data, $request) {
            $plan->update([
                'title' => $data['title'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'goal' => $data['goal'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $plan->items()->delete();
            foreach ($data['items'] as $i => $item) {
                $plan->items()->create($item + ['sort_order' => $i]);
            }
            $this->audit->record('nutrition_plan.updated', $request, $request->user(), $plan->gym_id, $plan, ['member_id' => $plan->member_id, 'items' => count($data['items'])]);

            return $plan;
        });
    }

    public function addProgramFeedback(WorkoutProgram $program, string $body, Request $request): void
    {
        $feedback = $program->feedback()->create(['user_id' => $request->user()->id, 'body' => $body]);
        $this->audit->record('workout_program.feedback_added', $request, $request->user(), $program->gym_id, $program, ['feedback_id' => $feedback->id]);
    }

    public function notifyClassSession(ClassSession $session, string $event): void
    {
        $session->loadMissing('gymClass.coach');
        $coach = $session->gymClass?->coach;
        if (! $coach?->user_id) {
            return;
        }
        $name = $session->gymClass->name;
        $when = $session->starts_at->format('Y-m-d H:i');
        if ($event === 'cancelled') {
            $this->notify($session->gym_id, $coach->user_id, 'class_session.cancelled', 'کلاس شما لغو شد', 'کلاس «'.$name.'» در تاریخ '.$when.' لغو شد.', route('tenant.wellness.dashboard'));
        } else {
            $this->notify($session->gym_id, $coach->user_id, 'class_session.scheduled', 'کلاس جدید برای شما برنامه‌ریزی شد', 'کلاس «'.$name.'» در تاریخ '.$when.' برگزار می‌شود.', route('tenant.wellness.dashboard'));
        }
    }

    public function createExercise(array $data, Request $request): Exercise
    {
        $exercise = Exercise::query()->create($data + ['created_by' => $request->user()->id]);
        $this->audit->record('exercise.created', $request, $request->user(), $exercise->gym_id, $exercise, ['name' => $exercise->name]);

        return $exercise;
    }

    public function createWorkoutTemplate(?Coach $coach, array $data, Request $request): WorkoutTemplate
    {
        return DB::transaction(function () use ($coach, $data, $request) {
            $template = WorkoutTemplate::query()->create([
                'coach_id' => $coach?->id,
                'name' => $data['name'],
                'goal' => $data['goal'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['days'] as $day) {
                $dayModel = $template->days()->create([
                    'phase' => $day['phase'] ?? null,
                    'day_number' => $day['day_number'],
                    'title' => $day['title'] ?? null,
                    'notes' => $day['notes'] ?? null,
                ]);
                foreach ($day['sets'] ?? [] as $set) {
                    $dayModel->sets()->create($set);
                }
            }

            $this->audit->record('workout_template.created', $request, $request->user(), $template->gym_id, $template, ['coach_id' => $coach?->id, 'days' => count($data['days'])]);

            return $template;
        });
    }

    public function assignWorkoutTemplate(WorkoutTemplate $template, array $memberIds, string $startsOn, Request $request): int
    {
        $template->load('days.sets');
        $assigner = $request->user();
        $coachId = $template->coach_id ?: Coach::query()->where('user_id', $assigner->id)->value('id');
        $created = 0;

        DB::transaction(function () use ($template, $memberIds, $startsOn, $request, $coachId, &$created) {
            foreach ($memberIds as $memberId) {
                $member = Member::query()->findOrFail($memberId);
                $program = WorkoutProgram::query()->create([
                    'member_id' => $member->id,
                    'coach_id' => $coachId,
                    'created_by' => $request->user()->id,
                    'title' => $template->name,
                    'starts_on' => $startsOn,
                    'goal' => $template->goal,
                    'status' => 'draft',
                    'notes' => $template->notes,
                ]);

                $dayNumber = 0;
                foreach ($template->days as $tplDay) {
                    $dayNumber++;
                    $dayTitle = collect([$tplDay->phase, $tplDay->title ?: 'روز '.$tplDay->day_number])->filter()->implode(' — ');
                    $programDay = $program->days()->create([
                        'day_number' => $dayNumber,
                        'title' => $dayTitle ?: null,
                        'notes' => $tplDay->notes,
                    ]);
                    foreach ($tplDay->sets as $set) {
                        $notes = $set->notes;
                        if ($set->duration_seconds) {
                            $notes = trim(($notes ? $notes.' · ' : '').'مدت هوازی: '.$set->duration_seconds.' ثانیه');
                        }
                        $programDay->sets()->create([
                            'exercise_name' => $set->exercise_name,
                            'set_number' => $set->set_number,
                            'weight_kg' => $set->weight_kg,
                            'repetitions' => $set->repetitions,
                            'notes' => $notes ?: null,
                        ]);
                    }
                }

                if ($member->user_id) {
                    $this->notify($member->gym_id, $member->user_id, 'workout_program.created', 'برنامه تمرینی جدید', 'برنامه «'.$template->name.'» برای شما نوشته شد.', route('tenant.wellness.index', $member));
                }
                $created++;
            }
        });

        return $created;
    }

    public function requestCoach(Member $member, ?Coach $coach, string $domain, string $type, ?string $message, Request $request): CoachRequest
    {
        if (! in_array($domain, ['training', 'nutrition'], true)) {
            throw ValidationException::withMessages(['domain' => 'دامنه نامعتبر است.']);
        }
        if (! in_array($type, ['assign', 'program'], true)) {
            throw ValidationException::withMessages(['type' => 'نوع درخواست نامعتبر است.']);
        }
        if ($coach && $coach->coach_type !== 'both' && $coach->coach_type !== $domain) {
            throw ValidationException::withMessages(['coach_id' => 'این مربی برای دامنه انتخابی تعریف نشده است.']);
        }

        return DB::transaction(function () use ($member, $coach, $domain, $type, $message, $request) {
            $open = CoachRequest::query()
                ->where('member_id', $member->id)
                ->where('domain', $domain)
                ->where('type', $type)
                ->where('status', 'pending')
                ->exists();
            if ($open) {
                throw ValidationException::withMessages(['request' => 'برای این مورد درخواست بازی دارید.']);
            }

            $coachRequest = CoachRequest::query()->create([
                'member_id' => $member->id,
                'coach_id' => $coach?->id,
                'domain' => $domain,
                'type' => $type,
                'message' => $message,
                'status' => 'pending',
            ]);

            $this->audit->record('coach_request.created', $request, $request->user(), $member->gym_id, $coachRequest, ['member_id' => $member->id, 'coach_id' => $coach?->id, 'domain' => $domain, 'type' => $type]);

            $url = route('tenant.members.show', $member);
            foreach ($this->reviewerUserIds($member->gym_id) as $userId) {
                $this->notify($member->gym_id, $userId, 'coach_request.created', 'درخواست جدید مربی/برنامه', $member->full_name.' درخواست «'.($type === 'assign' ? 'مربی' : 'برنامه').'» ثبت کرد.', $url);
            }
            if ($coach?->user_id) {
                $this->notify($member->gym_id, $coach->user_id, 'coach_request.created', 'درخواست جدید برای شما', $member->full_name.' از شما درخواست کرده است.', route('tenant.wellness.index', $member));
            }

            return $coachRequest;
        });
    }

    public function reviewCoachRequest(CoachRequest $coachRequest, bool $approve, ?string $note, Request $request): CoachRequest
    {
        if ($coachRequest->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'این درخواست قبلاً بررسی شده است.']);
        }
        if (! $approve && ! $note) {
            throw ValidationException::withMessages(['review_note' => 'دلیل رد الزامی است.']);
        }
        $coachRequest->load('member');

        return DB::transaction(function () use ($coachRequest, $approve, $note, $request) {
            $coachRequest->update([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            if ($approve && $coachRequest->type === 'assign') {
                $coach = $coachRequest->coach_id
                    ? Coach::query()->find($coachRequest->coach_id)
                    : $this->defaultCoachFor($coachRequest->member, $coachRequest->domain);

                if (! $coach) {
                    throw ValidationException::withMessages(['coach_id' => 'مربی مقصد مشخص نیست؛ ابتدا مربی پیش‌فرض دامنه را تعیین کنید.']);
                }

                $this->assignCoach($coachRequest->member, $coach, $coachRequest->domain, true, $request);
            }

            $this->audit->record('coach_request.reviewed', $request, $request->user(), $coachRequest->gym_id, $coachRequest, ['status' => $coachRequest->status, 'approve' => $approve]);

            if ($coachRequest->member->user_id) {
                $this->notify($coachRequest->gym_id, $coachRequest->member->user_id, 'coach_request.reviewed', $approve ? 'درخواست شما تأیید شد' : 'درخواست شما رد شد', $approve ? 'درخواست شما تأیید شد.' : ($note ?: 'درخواست شما رد شد.'), route('tenant.wellness.index', $coachRequest->member));
            }

            return $coachRequest;
        });
    }

    public function canReviewCoachRequest(User $user, CoachRequest $coachRequest): bool
    {
        if ($this->roles->isManager($user)) {
            return true;
        }

        $coach = Coach::query()->where('user_id', $user->id)->first();
        if (! $coach) {
            return false;
        }

        if ($coachRequest->coach_id) {
            return $coachRequest->coach_id === $coach->id;
        }

        return CoachMemberAssignment::query()
            ->where('coach_id', $coach->id)
            ->where('member_id', $coachRequest->member_id)
            ->where('domain', $coachRequest->domain)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();
    }

    public function pendingReviewableRequests(Coach $coach): \Illuminate\Database\Eloquent\Collection
    {
        return CoachRequest::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($coach) {
                $query->where('coach_id', $coach->id)
                    ->orWhere(function ($query) use ($coach) {
                        $query->whereNull('coach_id')
                            ->whereExists(function ($sub) use ($coach) {
                                $sub->selectRaw('1')
                                    ->from('coach_member_assignments')
                                    ->whereColumn('coach_member_assignments.member_id', 'coach_requests.member_id')
                                    ->whereColumn('coach_member_assignments.domain', 'coach_requests.domain')
                                    ->where('coach_member_assignments.coach_id', $coach->id)
                                    ->where('coach_member_assignments.is_active', true)
                                    ->where('coach_member_assignments.is_default', true);
                            });
                    });
            })
            ->with(['member', 'coach'])
            ->latest()
            ->get();
    }

    private function defaultCoachFor(Member $member, string $domain): ?Coach
    {
        $assignment = CoachMemberAssignment::query()
            ->where('member_id', $member->id)
            ->where('domain', $domain)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        return $assignment?->coach;
    }

    private function notify(int $gymId, int $userId, string $type, string $title, ?string $message = null, ?string $url = null): void
    {
        Notification::query()->create([
            'gym_id' => $gymId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    private function reviewerUserIds(int $gymId): array
    {
        $gym = Gym::query()->find($gymId);
        $ids = collect([$gym?->owner_id]);
        if ($gym) {
            $ids = $ids->merge(
                $gym->users()->wherePivot('role', 'manager')->wherePivot('status', 'active')->pluck('users.id')
            );
        }

        return $ids->filter()->unique()->values()->map(fn ($value) => (int) $value)->all();
    }

    private function normalizeMobile(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return preg_replace('/[^\d+]/', '', str_replace($fa, $en, trim($value)));
    }
}

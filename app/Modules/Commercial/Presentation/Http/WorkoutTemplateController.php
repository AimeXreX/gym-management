<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Core\Authorization\TenantRoleService;
use App\Core\Tenancy\GymContext;
use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\Exercise;
use App\Models\Member;
use App\Models\WorkoutTemplate;
use App\Modules\Commercial\Application\CommercialWorkflows;
use App\Modules\Commercial\Application\WellnessAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkoutTemplateController extends Controller
{
    public function exercises(Request $request, WellnessAccess $access, TenantRoleService $roles)
    {
        $this->trainingContext($request, $access, $roles);

        return view('commercial.wellness.exercises', [
            'exercises' => Exercise::query()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function storeExercise(Request $request, WellnessAccess $access, TenantRoleService $roles, CommercialWorkflows $flow)
    {
        $this->trainingContext($request, $access, $roles);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['strength', 'cardio', 'stretching'])],
            'muscle_group' => ['nullable', 'string', 'max:80'],
            'equipment' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $flow->createExercise($data, $request);

        return back()->with('status', 'تمرین به کتابخانه اضافه شد.');
    }

    public function destroyExercise(Request $request, Exercise $exercise, WellnessAccess $access, TenantRoleService $roles)
    {
        [$isManager] = $this->trainingContext($request, $access, $roles);
        abort_unless($isManager || $exercise->created_by === $request->user()->id, 403);
        $exercise->delete();

        return back()->with('status', 'تمرین حذف شد.');
    }

    public function index(Request $request, WellnessAccess $access, TenantRoleService $roles)
    {
        [$isManager, $coach] = $this->trainingContext($request, $access, $roles);
        $templates = WorkoutTemplate::query()
            ->with(['coach', 'days.sets.exercise'])
            ->when(! $isManager, fn ($q) => $q->where(fn ($x) => $x->whereNull('coach_id')->orWhere('coach_id', $coach->id)))
            ->latest()
            ->get();

        return view('commercial.wellness.templates', [
            'templates' => $templates,
            'exercises' => Exercise::query()->orderBy('category')->orderBy('name')->get(),
            'members' => $this->assignableMembers($isManager, $coach),
            'isManager' => $isManager,
        ]);
    }

    public function store(Request $request, WellnessAccess $access, TenantRoleService $roles, CommercialWorkflows $flow)
    {
        [$isManager, $coach] = $this->trainingContext($request, $access, $roles);
        $data = $this->templateData($request);
        $flow->createWorkoutTemplate($isManager ? null : $coach, $data, $request);

        return back()->with('status', 'قالب برنامه ذخیره شد.');
    }

    public function assign(Request $request, WorkoutTemplate $template, WellnessAccess $access, TenantRoleService $roles, CommercialWorkflows $flow)
    {
        [$isManager, $coach] = $this->trainingContext($request, $access, $roles);
        $this->authorizeTemplateUse($isManager, $coach, $template);
        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1', 'max:200'],
            'member_ids.*' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
        ]);
        $allowedIds = $this->assignableMembers($isManager, $coach)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $memberIds = array_values(array_intersect(array_map('intval', $data['member_ids']), $allowedIds));
        abort_if(empty($memberIds), 422, 'شاگردی برای اختصاص انتخاب نشده است.');
        $count = $flow->assignWorkoutTemplate($template, $memberIds, $data['starts_on'], $request);

        return back()->with('status', "قالب برای {$count} شاگرد ایجاد شد.");
    }

    public function destroy(Request $request, WorkoutTemplate $template, WellnessAccess $access, TenantRoleService $roles)
    {
        [$isManager, $coach] = $this->trainingContext($request, $access, $roles);
        abort_unless($isManager || ($coach && $template->coach_id === $coach->id), 403);
        $template->delete();

        return back()->with('status', 'قالب حذف شد.');
    }

    /** @return array{0: bool, 1: ?Coach} */
    private function trainingContext(Request $request, WellnessAccess $access, TenantRoleService $roles): array
    {
        $isManager = $roles->isManager($request->user());
        $coach = $access->coachFor($request->user());
        abort_unless($isManager || $coach?->handlesDomain('training'), 403);

        return [$isManager, $coach];
    }

    private function assignableMembers(bool $isManager, ?Coach $coach): Collection
    {
        if ($isManager) {
            return Member::query()->where('status', 'active')->orderBy('first_name')->get();
        }
        $ids = $coach->memberAssignments()->where('is_active', true)->where('domain', 'training')->pluck('member_id');

        return Member::query()->whereIn('id', $ids)->orderBy('first_name')->get();
    }

    private function authorizeTemplateUse(bool $isManager, ?Coach $coach, WorkoutTemplate $template): void
    {
        if ($isManager) {
            return;
        }
        abort_unless($coach && ($template->coach_id === null || $template->coach_id === $coach->id), 403);
    }

    private function templateData(Request $request): array
    {
        $gym = app(GymContext::class)->id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'days' => ['required', 'array', 'min:1', 'max:31'],
            'days.*.phase' => ['nullable', 'string', 'max:80'],
            'days.*.day_number' => ['nullable', 'integer', 'min:1', 'max:366'],
            'days.*.title' => ['nullable', 'string', 'max:100'],
            'days.*.notes' => ['nullable', 'string', 'max:2000'],
            'days.*.sets' => ['nullable', 'array', 'max:60'],
            'days.*.sets.*.exercise_id' => ['nullable', 'integer', Rule::exists('exercises', 'id')->where(fn ($q) => $q->where('gym_id', $gym))],
            'days.*.sets.*.exercise_name' => ['nullable', 'string', 'max:150'],
            'days.*.sets.*.set_number' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days.*.sets.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'days.*.sets.*.repetitions' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'days.*.sets.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'days.*.sets.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $days = [];
        foreach ($data['days'] as $day) {
            $sets = [];
            foreach ($day['sets'] ?? [] as $set) {
                $exerciseId = $set['exercise_id'] ?? null;
                $name = $set['exercise_name'] ?? null;
                if ($exerciseId) {
                    $exercise = Exercise::query()->find($exerciseId);
                    $name = $exercise?->name ?: $name;
                }
                if (! $name) {
                    continue;
                }
                if (empty($set['set_number'])) {
                    throw ValidationException::withMessages(['days' => 'برای هر حرکت شماره ست لازم است.']);
                }
                $sets[] = [
                    'exercise_id' => $exerciseId,
                    'exercise_name' => $name,
                    'set_number' => $set['set_number'],
                    'weight_kg' => $set['weight_kg'] ?? null,
                    'repetitions' => $set['repetitions'] ?? null,
                    'duration_seconds' => $set['duration_seconds'] ?? null,
                    'notes' => $set['notes'] ?? null,
                ];
            }
            $day['sets'] = $sets;
            if (empty($day['phase']) && empty($day['title']) && empty($sets)) {
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
}

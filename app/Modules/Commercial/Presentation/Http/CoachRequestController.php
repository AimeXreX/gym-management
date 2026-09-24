<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\CoachRequest;
use App\Models\Member;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoachRequestController extends Controller
{
    public function store(Request $request, Member $member, CommercialWorkflows $flow)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'domain' => ['required', Rule::in(['training', 'nutrition'])],
            'type' => ['required', Rule::in(['assign', 'program'])],
            'coach_id' => ['nullable', Rule::exists('coaches', 'id')->where(fn ($q) => $q->where('gym_id', $member->gym_id)->where('status', 'active'))],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $coach = isset($data['coach_id']) ? Coach::find($data['coach_id']) : null;
        $flow->requestCoach($member, $coach, $data['domain'], $data['type'], $data['message'] ?? null, $request);

        return back()->with('status', 'درخواست ثبت شد.');
    }

    public function review(Request $request, CoachRequest $coachRequest, CommercialWorkflows $flow)
    {
        abort_unless($flow->canReviewCoachRequest($request->user(), $coachRequest), 403);

        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $flow->reviewCoachRequest($coachRequest, $request->boolean('approve'), $data['review_note'] ?? null, $request);

        return back()->with('status', 'درخواست بررسی شد.');
    }
}

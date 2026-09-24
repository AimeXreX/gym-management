<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoachAssignmentController extends Controller
{
    public function assign(Request $request, Member $member, CommercialWorkflows $workflows)
    {
        $data = $request->validate([
            'coach_id' => ['required', Rule::exists('coaches', 'id')->where(fn ($q) => $q->where('gym_id', $member->gym_id))],
            'domain' => ['required', Rule::in(['training', 'nutrition'])],
        ]);
        $coach = Coach::findOrFail($data['coach_id']);
        $workflows->assignCoach($member, $coach, $data['domain'], $request->boolean('is_default'), $request);

        return back()->with('status', 'مربی به عضو منتسب شد.');
    }

    public function unassign(Request $request, CoachMemberAssignment $assignment, CommercialWorkflows $workflows)
    {
        $workflows->unassignCoach($assignment, $request);

        return back()->with('status', 'انتساب مربی لغو شد.');
    }

    public function setDefault(Request $request, CoachMemberAssignment $assignment, CommercialWorkflows $workflows)
    {
        $workflows->setDefaultCoach($assignment, $request);

        return back()->with('status', 'مربی پیش‌فرض تعیین شد.');
    }
}

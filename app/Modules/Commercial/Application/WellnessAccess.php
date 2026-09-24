<?php

namespace App\Modules\Commercial\Application;

use App\Models\Coach;
use App\Models\CoachMemberAssignment;
use App\Models\Member;
use App\Models\User;

class WellnessAccess
{
    public function memberFor(User $user): ?Member
    {
        return Member::where('user_id', $user->id)->first();
    }

    public function coachFor(User $user): ?Coach
    {
        return Coach::where('user_id', $user->id)->where('status', 'active')->first();
    }

    public function canView(User $user, Member $member): bool
    {
        return $member->user_id === $user->id || $this->canCoach($user, $member);
    }

    public function canCoach(User $user, Member $member): bool
    {
        return $this->canCoachDomain($user, $member, 'training') || $this->canCoachDomain($user, $member, 'nutrition');
    }

    public function canCoachDomain(User $user, Member $member, string $domain): bool
    {
        $coach = $this->coachFor($user);

        return (bool) ($coach && $coach->handlesDomain($domain) && CoachMemberAssignment::where('coach_id', $coach->id)->where('member_id', $member->id)->where('domain', $domain)->where('is_active', true)->exists());
    }
}

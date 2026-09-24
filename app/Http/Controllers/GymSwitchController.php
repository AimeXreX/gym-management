<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\GymAccess;
use App\Core\Tenancy\GymSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GymSwitchController extends Controller
{
    public function __invoke(
        Request $request,
        GymAccess $access,
        GymSelection $selection,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate(['gym_id' => ['required', 'integer']]);
        $destination = $access->findAvailable($request->user(), (int) $validated['gym_id']);

        if ($destination === null) {
            $audit->record('gym.switch_denied', $request, $request->user(), metadata: [
                'requested_gym_id' => (int) $validated['gym_id'],
            ]);
            Log::warning('Unauthorized gym switch denied.', [
                'user_id' => $request->user()->id,
                'requested_gym_id' => (int) $validated['gym_id'],
            ]);

            return back()->withErrors(['gym_id' => __('ui.gym_invalid')]);
        }

        $previousGymId = $selection->id();
        $selection->select($destination->id);
        $request->session()->regenerate();
        $audit->record(
            'gym.switched',
            $request,
            $request->user(),
            $destination->id,
            $destination,
            ['previous_gym_id' => $previousGymId],
        );

        $home = in_array($destination->pivot->role, ['coach', 'nutrition_coach', 'member'], true)
            ? 'tenant.wellness.index'
            : 'tenant.dashboard';

        return redirect()->route($home)->with('status', __('ui.gym_changed'));
    }
}

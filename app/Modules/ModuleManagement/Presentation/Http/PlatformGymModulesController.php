<?php

namespace App\Modules\ModuleManagement\Presentation\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Modules\ModuleRegistry;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Modules\ModuleManagement\Application\UpdateGymEntitlements;
use App\Modules\ModuleManagement\Presentation\Http\Requests\UpdateGymModulesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformGymModulesController extends Controller
{
    public function edit(Gym $gym, ModuleRegistry $registry): View
    {
        return view('platform.gyms.modules', [
            'gym' => $gym,
            'definitions' => $registry->all(),
            'entitlements' => $gym->modules()->get()->keyBy('key'),
        ]);
    }

    public function update(
        UpdateGymModulesRequest $request,
        Gym $gym,
        UpdateGymEntitlements $action,
        AuditLogger $audit,
    ): RedirectResponse {
        $changes = $action->handle($gym, $request->validated('modules'));
        $audit->record('platform.gym.modules_updated', $request, $request->user(), $gym->id, $gym, $changes);

        return back()->with('status', __('ui.modules_updated'));
    }
}

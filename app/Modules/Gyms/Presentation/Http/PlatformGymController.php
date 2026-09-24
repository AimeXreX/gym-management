<?php

namespace App\Modules\Gyms\Presentation\Http;

use App\Core\Modules\ModuleRegistry;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Modules\Gyms\Application\CreateGym;
use App\Modules\Gyms\Application\GymIndexQuery;
use App\Modules\Gyms\Application\UpdateGym;
use App\Modules\Gyms\Presentation\Http\Requests\StoreGymRequest;
use App\Modules\Gyms\Presentation\Http\Requests\UpdateGymRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformGymController extends Controller
{
    public function index(Request $request, GymIndexQuery $query): View
    {
        return view('platform.gyms.index', [
            'gyms' => $query->handle($request->string('search')->trim()->value(), $request->string('status')->value()),
        ]);
    }

    public function create(ModuleRegistry $registry): View
    {
        return view('platform.gyms.create', ['modules' => $registry->all()]);
    }

    public function store(StoreGymRequest $request, CreateGym $action): RedirectResponse
    {
        $gym = $action->handle($request->validated(), $request->user(), $request);

        return redirect()->route('platform.gyms.edit', $gym)
            ->with('status', __('ui.gym_created_securely'));
    }

    public function edit(Gym $gym): View
    {
        return view('platform.gyms.edit', ['gym' => $gym->load('owner:id,name,email')]);
    }

    public function update(UpdateGymRequest $request, Gym $gym, UpdateGym $action): RedirectResponse
    {
        $action->handle($gym, $request->validated(), $request->user(), $request);

        return back()->with('status', __('ui.gym_updated'));
    }
}

<?php

namespace App\Modules\Gyms\Presentation\Http;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'metrics' => [
                'gyms' => Gym::query()->count(),
                'active_gyms' => Gym::query()->where('status', 'active')->count(),
                'inactive_gyms' => Gym::query()->where('status', 'inactive')->count(),
                'users' => User::query()->count(),
                'modules' => Module::query()->count(),
                'active_entitlements' => DB::table('gym_modules')->where('enabled', true)->count(),
            ],
        ]);
    }
}

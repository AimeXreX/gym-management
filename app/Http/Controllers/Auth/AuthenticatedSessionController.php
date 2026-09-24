<?php

namespace App\Http\Controllers\Auth;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\GymSelection;
use App\Core\Tenancy\GymAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit, GymAccess $access, GymSelection $selection): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login', $request, $request->user(), metadata: [
            'remember' => $request->boolean('remember'),
        ]);

        if ($request->user()->is_platform_admin) {
            return redirect()->intended(route('platform.dashboard'));
        }

        $gyms = $access->availableFor($request->user());
        if ($gyms->isEmpty()) {
            return redirect()->intended(route('tenant.dashboard'));
        }
        if ($gyms->count() > 1) {
            return redirect()->route('account.gyms.select');
        }

        $gym = $gyms->first();
        $selection->select($gym->id);
        $role = $gym->pivot->role;
        $home = match ($role) {
            'member' => 'tenant.wellness.index',
            'coach', 'nutrition_coach' => 'tenant.wellness.dashboard',
            default => 'tenant.dashboard',
        };

        return redirect()->intended(route($home));
    }

    public function destroy(Request $request, AuditLogger $audit, GymSelection $selection): RedirectResponse
    {
        $user = $request->user();
        $audit->record('auth.logout', $request, $user, $selection->id());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

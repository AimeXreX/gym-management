<?php

namespace App\Modules\Commercial\Presentation\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\GymContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandingController extends Controller
{
    public function edit()
    {
        return view('commercial.branding.edit', ['gym' => app(GymContext::class)->gym()]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'theme_preset' => ['required', Rule::in(['midnight', 'aurora', 'graphite'])],
            'brand_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'brand_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_tagline' => ['nullable', 'string', 'max:120'], 'ui_radius' => ['required', Rule::in(['compact', 'soft', 'round'])],
            'ui_density' => ['required', Rule::in(['compact', 'comfortable'])],
        ]);
        $gym = app(GymContext::class)->gym();
        $gym->update($data);
        $audit->record('gym.branding_updated', $request, $request->user(), $gym->id, $gym, ['fields' => array_keys($data)]);

        return back()->with('status', 'هویت بصری باشگاه ذخیره شد.');
    }
}

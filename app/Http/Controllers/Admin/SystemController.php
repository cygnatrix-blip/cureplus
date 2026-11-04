<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function __construct(private readonly PlatformSettings $settings, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.system.index', ['settings' => $this->settings, 'isDown' => app()->isDownForMaintenance()]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'require_approval' => ['sometimes', 'boolean'],
            'two_factor_all_staff' => ['sometimes', 'boolean'],
            'legal_privacy' => ['nullable', 'string', 'max:50000'],
            'legal_terms' => ['nullable', 'string', 'max:50000'],
        ]);

        $this->settings->save([
            'organizations.require_approval' => ($data['require_approval'] ?? false) ? '1' : '0',
            'security.two_factor_all_staff' => ($data['two_factor_all_staff'] ?? false) ? '1' : null,
            'legal.privacy' => $data['legal_privacy'] ?? null,
            'legal.terms' => $data['legal_terms'] ?? null,
        ]);
        $this->audit->record('platform_settings.system_saved');

        return back()->with('success', 'Settings saved.');
    }

    /**
     * The secret is the admin's way back in, so it is shown once and is the whole bypass URL.
     */
    public function maintenance(Request $request): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:down,up']]);

        if ($data['action'] === 'down') {
            $secret = Str::random(40);
            Artisan::call('down', ['--secret' => $secret, '--retry' => 60]);
            $this->audit->record('system.maintenance_on');

            return back()->with('status', 'Maintenance mode is on. Keep this link to get back in: '.url('/'.$secret));
        }

        Artisan::call('up');
        $this->audit->record('system.maintenance_off');

        return back()->with('success', 'The site is back up.');
    }
}

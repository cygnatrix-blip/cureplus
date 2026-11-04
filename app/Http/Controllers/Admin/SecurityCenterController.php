<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\BlockIps;
use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecurityCenterController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $found = $search === '' ? null : User::query()->where(fn ($query) => $query->where('email', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))->limit(10)->get();

        $liveSince = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        return view('admin.security.index', [
            'events' => SecurityEvent::query()->latest('id')->paginate(50),
            'activeUserSessions' => DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', $liveSince)->count(),
            'activeAdminSessions' => DB::table('sessions')->whereNotNull('platform_admin_id')->where('last_activity', '>=', $liveSince)->count(),
            'search' => $search,
            'found' => $found,
            'foundSessions' => $found ? DB::table('sessions')->whereIn('user_id', $found->pluck('id'))->latest('last_activity')->limit(50)->get(['user_id', 'ip_address', 'user_agent', 'last_activity'])->groupBy('user_id') : collect(),
            'blockedIps' => BlockedIp::query()->latest('id')->limit(100)->get(),
            'twoFactorMissing' => User::query()->whereNull('two_factor_confirmed_at')->whereHas('memberships', fn ($query) => $query->where('status', 'active')->whereHas('roles', fn ($query) => $query->whereIn('key', config('cureplus.security.two_factor_roles'))))->count(),
            'lockedAccounts' => User::query()->where('locked_until', '>', now())->limit(100)->get(['id', 'name', 'email', 'mobile', 'locked_until']),
        ]);
    }

    public function blockIp(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Locking yourself out of the portal that could undo it helps nobody.
        if ($data['ip_address'] === $request->ip()) {
            return back()->with('error', 'That is your own IP address.');
        }

        BlockedIp::query()->firstOrCreate(['ip_address' => $data['ip_address']], ['reason' => $data['reason'] ?? null, 'blocked_by' => $request->user('platform')->id]);
        Cache::forget(BlockIps::CACHE_KEY);
        $audit->record('security.ip_blocked', null, ['ip' => $data['ip_address']]);

        return back()->with('success', 'IP address blocked.');
    }

    public function unblockIp(BlockedIp $blockedIp, AuditLogger $audit): RedirectResponse
    {
        $audit->record('security.ip_unblocked', null, ['ip' => $blockedIp->ip_address]);
        $blockedIp->delete();
        Cache::forget(BlockIps::CACHE_KEY);

        return back()->with('success', 'IP address unblocked.');
    }

    /**
     * For someone who lost their phone and recovery codes. Every session ends too, so
     * whoever may hold the phone is not left signed in. The reason is the audit trail.
     */
    public function resetTwoFactor(Request $request, User $user, UserSessions $sessions, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $user->forceFill(['two_factor_method' => null, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->saveQuietly();
        $sessions->revokeAll($user);
        $audit->record('security.two_factor_reset', $user, ['reason' => $data['reason']]);

        return back()->with('success', '2FA was reset. They will set it up again at next sign-in.');
    }

    public function forceLogout(User $user, UserSessions $sessions, AuditLogger $audit): RedirectResponse
    {
        $count = $sessions->revokeAll($user);
        $audit->record('security.force_logout', $user, ['sessions' => $count]);
        SecurityEvent::record('forced_logout', 'web', $user->email ?? $user->mobile, $user->id);

        return back()->with('success', "Signed out {$count} session(s).");
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceSecurity;
use App\Services\PinSecurity;
use Closure;
use Illuminate\Http\Request;

class SharedAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user && $request->session()->get('bootstrap_admin_setup') === true) {
            $isSetupRoute = ($request->isMethod('GET') && $request->is('security/setup'))
                || ($request->isMethod('POST') && $request->is('api/security/setup'));
            $isLogout = $request->isMethod('POST') && $request->is('logout');

            if ($isLogout || (WorkspaceSecurity::bootstrapSetupAvailable() && $isSetupRoute)) {
                return $next($request);
            }

            if (WorkspaceSecurity::bootstrapSetupAvailable()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'ابتدا راه‌اندازی امنیت را تکمیل کنید.'], 403)
                    : redirect('/security/setup');
            }

            $request->session()->forget('bootstrap_admin_setup');

            return $request->expectsJson()
                ? response()->json(['message' => 'ابتدا وارد فضای کار شوید.'], 401)
                : redirect('/login');
        }

        if ($user && ! $user->active) {
            auth()->logout();

            return $request->expectsJson() ? response()->json(['message' => 'پروفایل فعال نیست.'], 403) : redirect('/login');
        }

        if ($user?->role === 'admin') {
            $hash = $user->pin_hash;
            $fingerprint = $request->session()->get('admin_pin_fingerprint');
        } elseif ($user?->role === 'member' || ! $user) {
            $hash = WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash');
            $fingerprint = $request->session()->get('member_pin_fingerprint');
        } else {
            auth()->logout();
            $hash = null;
            $fingerprint = null;
        }

        if (! $hash || ! hash_equals(PinSecurity::fingerprint($hash), (string) $fingerprint)) {
            auth()->logout();
            $request->session()->forget(['admin_pin_fingerprint', 'member_pin_fingerprint']);

            return $request->expectsJson() ? response()->json(['message' => 'ابتدا وارد فضای کار شوید.'], 401) : redirect('/login');
        }

        return $next($request);
    }
}

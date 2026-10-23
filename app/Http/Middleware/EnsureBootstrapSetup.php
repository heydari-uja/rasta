<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceSecurity;
use Closure;
use Illuminate\Http\Request;

class EnsureBootstrapSetup
{
    public function handle(Request $request, Closure $next)
    {
        $isSetupRoute = ($request->isMethod('GET') && $request->is('security/setup'))
            || ($request->isMethod('POST') && $request->is('api/security/setup'));
        $isBootstrapSession = ! $request->user()
            && $request->session()->get('bootstrap_admin_setup') === true
            && WorkspaceSecurity::bootstrapSetupAvailable();

        if ($isBootstrapSession && $isSetupRoute) {
            return $next($request);
        }

        if ($request->user()?->role === 'admin' && WorkspaceSecurity::adminPinConfigured()) {
            return redirect('/tasks');
        }

        $request->session()->forget('bootstrap_admin_setup');

        return $request->expectsJson()
            ? response()->json(['message' => 'ابتدا راه‌اندازی امنیت را تکمیل کنید.'], 403)
            : redirect('/login');
    }
}

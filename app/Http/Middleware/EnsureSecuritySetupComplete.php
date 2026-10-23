<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceSecurity;
use Closure;
use Illuminate\Http\Request;

class EnsureSecuritySetupComplete
{
    public function handle(Request $request, Closure $next)
    {
        if (WorkspaceSecurity::adminPinConfigured()) {
            return $next($request);
        }

        auth()->logout();
        $request->session()->forget(['admin_pin_fingerprint', 'member_pin_fingerprint', 'bootstrap_admin_setup']);

        return $request->expectsJson()
            ? response()->json(['message' => 'ابتدا راه‌اندازی امنیت مدیر را تکمیل کنید.'], 403)
            : redirect('/login');
    }
}

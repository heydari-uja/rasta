<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireWorkspaceUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'ابتدا پروفایل خود را انتخاب کنید.'], 401)
                : redirect('/profiles');
        }

        return $next($request);
    }
}

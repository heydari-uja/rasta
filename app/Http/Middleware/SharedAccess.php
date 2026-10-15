<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SharedAccess {
 public function handle(Request $request, Closure $next) {
  $hash = config('uja.password_hash');
  if (!$hash || !hash_equals(hash('sha256', $hash), (string)$request->session()->get('shared_access'))) {
   auth()->logout();
   return $request->expectsJson() ? response()->json(['message'=>'ابتدا وارد فضای کار شوید.'],401) : redirect('/login');
  }
  return $next($request);
 }
}

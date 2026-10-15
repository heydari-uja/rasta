<?php
namespace App\Http\Controllers;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function login(Request $r) {
  $v=$r->validate(['password'=>'required|string|max:1024']);
  $hash=config('uja.password_hash');
  if (!$hash || !Hash::check($v['password'],$hash)) throw ValidationException::withMessages(['password'=>'رمز فضای کار درست نیست.']);
  $r->session()->regenerate();
  $r->session()->put('shared_access',hash('sha256',$hash));
  return redirect(Auth::check()?'/dashboard':'/profiles');
 }
 public function profiles() { return view('profiles',['members'=>Member::where('active',true)->orderBy('name')->get()]); }
 public function select(Request $r) {
  if ($r->filled('member_id')) { $v=$r->validate(['member_id'=>'required|integer|exists:members,id']); $member=Member::where('active',true)->findOrFail($v['member_id']); }
  else { $v=$r->validate(['name'=>'required|string|max:100|unique:members,name','job_title'=>'required|string|max:100']); $member=Member::create($v); }
  Auth::login($member,true); $r->session()->regenerate(); return redirect('/dashboard');
 }
 public function switch(Request $r) { Auth::logout(); $r->session()->regenerate(); return redirect('/profiles'); }
 public function logout(Request $r) { Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login'); }
}

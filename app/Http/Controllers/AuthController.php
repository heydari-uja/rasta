<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\WorkspaceSecurity;
use App\Services\PinSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        Auth::logout();
        $r->session()->forget(['admin_pin_fingerprint', 'member_pin_fingerprint', 'bootstrap_admin_setup']);

        $input = $r->only(['mode', 'pin']);
        $input['pin'] = PinSecurity::normalize($input['pin'] ?? null);
        $v = validator($input, [
            'mode' => ['required', Rule::in(['member', 'admin'])],
            'pin' => ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'],
        ], [
            'mode.required' => 'نوع ورود را انتخاب کنید.',
            'mode.in' => 'نوع ورود معتبر نیست.',
            'pin.required' => 'پین را وارد کنید.',
            'pin.string' => 'پین باید بین ۴ تا ۱۲ رقم باشد.',
            'pin.regex' => 'پین باید بین ۴ تا ۱۲ رقم باشد.',
        ])->validate();

        if ($v['mode'] === 'admin') {
            if (WorkspaceSecurity::bootstrapSetupAvailable()) {
                $bootstrapPin = PinSecurity::bootstrapPin();
                if ($bootstrapPin === null) {
                    throw ValidationException::withMessages(['pin' => 'ورود مدیر در حال حاضر در دسترس نیست. پیکربندی راه‌اندازی را بررسی کنید.']);
                }
                if (! hash_equals($bootstrapPin, $v['pin'])) {
                    throw ValidationException::withMessages(['pin' => 'پین مدیر درست نیست.']);
                }

                $r->session()->regenerate();
                $r->session()->put('bootstrap_admin_setup', true);

                return redirect('/security/setup');
            }

            $admins = Member::where('role', 'admin')->get();
            $admin = $admins->count() === 1 ? $admins->first() : null;
            if (! $admin?->pin_hash || ! Hash::check($v['pin'], $admin->pin_hash)) {
                throw ValidationException::withMessages(['pin' => 'پین مدیر درست نیست.']);
            }
            if (! $admin->active) {
                throw ValidationException::withMessages(['pin' => 'پین مدیر درست نیست.']);
            }

            Auth::login($admin);
            $r->session()->regenerate();
            $r->session()->put('admin_pin_fingerprint', PinSecurity::fingerprint($admin->pin_hash));

            return redirect('/tasks');
        }

        $memberPinHash = WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash');
        if (! WorkspaceSecurity::adminPinConfigured() || ! $memberPinHash) {
            throw ValidationException::withMessages(['pin' => 'ورود اعضا هنوز فعال نشده است. مدیر باید ابتدا رمز مشترک اعضا را از بخش امنیت تنظیم کند.']);
        }
        if (! Hash::check($v['pin'], $memberPinHash)) {
            throw ValidationException::withMessages(['pin' => 'پین اعضا درست نیست.']);
        }

        Auth::logout();
        $r->session()->regenerate();
        $r->session()->forget('admin_pin_fingerprint');
        $r->session()->put('member_pin_fingerprint', PinSecurity::fingerprint($memberPinHash));

        return redirect('/profiles');
    }

    public function profiles()
    {
        if (Auth::user()?->role === 'admin') {
            return redirect('/tasks');
        }

        return view('profiles', ['members' => Member::where('active', true)->where('role', 'member')->orderBy('name')->get()]);
    }

    public function select(Request $r)
    {
        if ($r->filled('member_id')) {
            $v = $r->validate(['member_id' => ['required', 'integer', Rule::exists('members', 'id')->where(fn ($query) => $query->where('active', true)->where('role', 'member'))]]);
            $member = Member::where('active', true)->where('role', 'member')->findOrFail($v['member_id']);
        } else {
            $v = $r->validate(['name' => 'required|string|max:100|unique:members,name', 'job_title' => 'required|string|max:100']);
            $member = (new Member)->fill($v)->forceFill(['role' => 'member']);
            $member->save();
        }
        Auth::login($member, true);
        $r->session()->regenerate();

        return redirect('/tasks');
    }

    public function switch(Request $r)
    {
        Auth::logout();
        $r->session()->regenerate();

        return redirect('/profiles');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}

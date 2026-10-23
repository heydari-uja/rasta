<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\WorkspaceSecurity;
use App\Services\PinSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function updateAdminPin(Request $request)
    {
        $values = $this->normalizedInput($request, ['current_pin', 'pin', 'pin_confirmation']);
        $pinRules = ['required', 'string', 'regex:/\A[0-9]{4,12}\z/', ...PinSecurity::bootstrapPinRules()];
        $validated = validator($values, [
            'current_pin' => ['required', 'string', 'max:12'],
            'pin' => $pinRules,
            'pin_confirmation' => ['required', 'string', 'same:pin'],
        ], $this->messages())->validate();

        $admin = $request->user();
        if (! $admin->pin_hash || ! Hash::check($validated['current_pin'], $admin->pin_hash)) {
            throw ValidationException::withMessages(['current_pin' => 'پین فعلی مدیر درست نیست.']);
        }

        $adminId = $admin->id;
        DB::transaction(function () use ($adminId, $validated) {
            WorkspaceSecurity::singleton();
            $security = WorkspaceSecurity::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $admin = Member::query()->whereKey($adminId)->lockForUpdate()->firstOrFail();

            if (! $admin->pin_hash || ! Hash::check($validated['current_pin'], $admin->pin_hash)) {
                throw ValidationException::withMessages(['current_pin' => 'پین فعلی مدیر درست نیست.']);
            }
            if ($security->member_pin_hash && Hash::check($validated['pin'], $security->member_pin_hash)) {
                throw ValidationException::withMessages(['pin' => 'پین مدیر و پین مشترک اعضا باید متفاوت باشند.']);
            }

            $admin->forceFill(['pin_hash' => Hash::make($validated['pin'])])->save();
        });

        $admin->refresh();
        $request->session()->regenerate();
        $request->session()->put('admin_pin_fingerprint', PinSecurity::fingerprint($admin->pin_hash));

        return response()->json(['ok' => true]);
    }

    public function completeInitialSetup(Request $request)
    {
        $values = $this->normalizedInput($request, [
            'admin_pin', 'admin_pin_confirmation', 'member_pin', 'member_pin_confirmation',
        ]);
        $values += $request->only(['name', 'job_title']);
        $adminPinRules = ['required', 'string', 'regex:/\A[0-9]{4,12}\z/', ...PinSecurity::bootstrapPinRules()];
        $validator = validator($values, [
            'name' => ['required', 'string', 'max:100', Rule::unique('members', 'name')],
            'job_title' => ['required', 'string', 'max:100'],
            'admin_pin' => $adminPinRules,
            'admin_pin_confirmation' => ['required', 'string', 'same:admin_pin'],
            'member_pin' => ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'],
            'member_pin_confirmation' => ['required', 'string', 'same:member_pin'],
        ], [
            'name.required' => 'نام و نام خانوادگی مدیر را وارد کنید.',
            'name.string' => 'نام مدیر معتبر نیست.',
            'name.max' => 'نام مدیر نباید بیش از ۱۰۰ نویسه باشد.',
            'name.unique' => 'این نام پیش‌تر برای یک پروفایل استفاده شده است.',
            'job_title.required' => 'عنوان شغلی را وارد کنید.',
            'job_title.string' => 'عنوان شغلی معتبر نیست.',
            'job_title.max' => 'عنوان شغلی نباید بیش از ۱۰۰ نویسه باشد.',
            'admin_pin.required' => 'پین مدیر را وارد کنید.',
            'admin_pin.string' => 'پین مدیر باید بین ۴ تا ۱۲ رقم باشد.',
            'admin_pin.regex' => 'پین مدیر باید بین ۴ تا ۱۲ رقم باشد.',
            'admin_pin.not_in' => 'پین مدیر باید با پین موقت راه‌اندازی متفاوت باشد.',
            'admin_pin_confirmation.required' => 'تکرار پین مدیر را وارد کنید.',
            'admin_pin_confirmation.same' => 'تکرار پین مدیر یکسان نیست.',
            'member_pin.required' => 'پین مشترک اعضا را وارد کنید.',
            'member_pin.string' => 'پین اعضا باید بین ۴ تا ۱۲ رقم باشد.',
            'member_pin.regex' => 'پین اعضا باید بین ۴ تا ۱۲ رقم باشد.',
            'member_pin_confirmation.required' => 'تکرار پین اعضا را وارد کنید.',
            'member_pin_confirmation.same' => 'تکرار پین اعضا یکسان نیست.',
        ]);
        $validator->after(function ($validator) use ($values) {
            if (PinSecurity::isValid($values['admin_pin'] ?? null)
                && PinSecurity::isValid($values['member_pin'] ?? null)
                && hash_equals($values['admin_pin'], $values['member_pin'])) {
                $validator->errors()->add('member_pin', 'پین مدیر و پین مشترک اعضا باید متفاوت باشند.');
            }
        });

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'اطلاعات راه‌اندازی معتبر نیست.', 'errors' => $validator->errors()], 422);
            }

            return redirect('/security/setup')->withErrors($validator);
        }
        $validated = $validator->validated();

        $admin = null;
        DB::transaction(function () use (&$admin, $validated) {
            WorkspaceSecurity::singleton();
            $security = WorkspaceSecurity::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            abort_unless(WorkspaceSecurity::bootstrapSetupAvailable() && WorkspaceSecurity::adminCount() === 0, 403);

            $admin = (new Member)->forceFill([
                'name' => $validated['name'],
                'job_title' => $validated['job_title'],
                'role' => 'admin',
                'active' => true,
                'pin_hash' => Hash::make($validated['admin_pin']),
            ]);
            $admin->save();

            $security->forceFill([
                'member_pin_hash' => Hash::make($validated['member_pin']),
                'admin_pin_configured_at' => now(),
            ])->save();
        });

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget(['bootstrap_admin_setup', 'member_pin_fingerprint']);
        $request->session()->put('admin_pin_fingerprint', PinSecurity::fingerprint($admin->pin_hash));

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'redirect' => '/tasks'])
            : redirect('/tasks');
    }

    public function updateMemberPin(Request $request)
    {
        $values = $this->normalizedInput($request, ['current_pin', 'pin', 'pin_confirmation']);
        $validated = validator($values, [
            'current_pin' => ['required', 'string', 'max:12'],
            'pin' => ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'],
            'pin_confirmation' => ['required', 'string', 'same:pin'],
        ], $this->messages())->validate();

        $admin = $request->user();
        if (! $admin->pin_hash || ! Hash::check($validated['current_pin'], $admin->pin_hash)) {
            throw ValidationException::withMessages(['current_pin' => 'پین فعلی مدیر درست نیست.']);
        }

        $adminId = $admin->id;
        DB::transaction(function () use ($adminId, $validated) {
            WorkspaceSecurity::singleton();
            $security = WorkspaceSecurity::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $admin = Member::query()->whereKey($adminId)->lockForUpdate()->firstOrFail();

            if (! $admin->pin_hash || ! Hash::check($validated['current_pin'], $admin->pin_hash)) {
                throw ValidationException::withMessages(['current_pin' => 'پین فعلی مدیر درست نیست.']);
            }
            if (Hash::check($validated['pin'], $admin->pin_hash)) {
                throw ValidationException::withMessages(['pin' => 'پین مدیر و پین مشترک اعضا باید متفاوت باشند.']);
            }

            $security->forceFill(['member_pin_hash' => Hash::make($validated['pin'])])->save();
        });

        return response()->json(['ok' => true]);
    }

    private function normalizedInput(Request $request, array $keys): array
    {
        $values = $request->only($keys);
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = PinSecurity::normalize($values[$key]);
            }
        }

        return $values;
    }

    private function messages(): array
    {
        return [
            'current_pin.required' => 'پین فعلی مدیر را وارد کنید.',
            'current_pin.string' => 'پین فعلی مدیر معتبر نیست.',
            'current_pin.max' => 'پین فعلی مدیر درست نیست.',
            'pin.required' => 'پین جدید را وارد کنید.',
            'pin.string' => 'پین باید بین ۴ تا ۱۲ رقم باشد.',
            'pin.regex' => 'پین باید بین ۴ تا ۱۲ رقم باشد.',
            'pin.not_in' => 'پین مدیر باید با پین موقت راه‌اندازی متفاوت باشد.',
            'pin_confirmation.required' => 'تکرار پین جدید را وارد کنید.',
            'pin_confirmation.string' => 'تکرار پین جدید معتبر نیست.',
            'pin_confirmation.same' => 'تکرار پین جدید یکسان نیست.',
        ];
    }
}

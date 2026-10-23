<?php

use App\Models\Member;
use App\Models\WorkspaceSecurity;
use App\Services\PinSecurity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('uja:setup', function () {
    $admins = Member::query()->where('role', 'admin')->orderBy('id')->get();
    if ($admins->count() > 1) {
        $this->error('بیش از یک مدیر ثبت شده است. ابتدا وضعیت مدیران را بررسی کنید.');

        return 1;
    }

    $identity = null;
    if ($admins->isEmpty()) {
        $name = trim((string) $this->ask('نام و نام خانوادگی مدیر'));
        if ($name === '' || mb_strlen($name) > 100 || Member::where('name', $name)->exists()) {
            $this->error('نام مدیر خالی، تکراری یا بیش از حد طولانی است.');

            return 1;
        }

        $jobTitle = trim((string) $this->ask('عنوان شغلی'));
        if ($jobTitle === '' || mb_strlen($jobTitle) > 100) {
            $this->error('عنوان شغلی خالی یا بیش از حد طولانی است.');

            return 1;
        }
        $identity = ['name' => $name, 'job_title' => $jobTitle];
    }

    $pin = PinSecurity::normalize($this->secret('پین مدیر (۴ تا ۱۲ رقم)'));
    $bootstrapPin = PinSecurity::bootstrapPin();
    if (! PinSecurity::isValid($pin) || ($bootstrapPin !== null && hash_equals($bootstrapPin, $pin))) {
        unset($pin);
        $this->error('پین باید بین ۴ تا ۱۲ رقم باشد و با پین موقت راه‌اندازی متفاوت باشد.');

        return 1;
    }

    $confirmation = PinSecurity::normalize($this->secret('تکرار پین مدیر'));
    if (! is_string($confirmation) || ! hash_equals($pin, $confirmation)) {
        unset($pin, $confirmation);
        $this->error('تکرار پین یکسان نیست.');

        return 1;
    }

    $pinHash = Hash::make($pin);
    $failure = DB::transaction(function () use ($identity, $pin, $pinHash) {
        WorkspaceSecurity::singleton();
        $security = WorkspaceSecurity::query()->whereKey(1)->lockForUpdate()->firstOrFail();
        $admins = Member::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->get();

        if ($admins->count() > 1) {
            return 'بیش از یک مدیر ثبت شده است. هیچ تغییری انجام نشد.';
        }
        if ($security->member_pin_hash && Hash::check($pin, $security->member_pin_hash)) {
            return 'پین مدیر و پین مشترک اعضا باید متفاوت باشند.';
        }

        if ($admins->isEmpty()) {
            if (! $identity) {
                return 'اطلاعات مدیر برای ایجاد حساب موجود نیست.';
            }
            $admin = (new Member)->forceFill([
                ...$identity,
                'role' => 'admin',
                'active' => true,
                'pin_hash' => $pinHash,
            ]);
            $admin->save();
        } else {
            $admins->first()->forceFill(['pin_hash' => $pinHash])->save();
        }

        $security->forceFill(['admin_pin_configured_at' => now()])->save();

        return null;
    });

    unset($pin, $confirmation, $pinHash);
    if ($failure !== null) {
        $this->error($failure);

        return 1;
    }

    $this->info('پین مدیر ذخیره شد.');
})->purpose('ایجاد مدیر یا بازیابی پین مدیر');

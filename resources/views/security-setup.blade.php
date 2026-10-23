<!DOCTYPE html>
<html lang="fa" dir="rtl" class="dark" data-theme="dark" data-theme-preference="dark">
<head><link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content=""><title>راه‌اندازی امنیت راستا</title>
    
    @vite(['resources/css/app.css','resources/js/app.js'])
<script>const themeMeta=document.querySelector("meta[name=theme-color]");if(themeMeta)themeMeta.content=getComputedStyle(document.documentElement).getPropertyValue("--surface-page").trim()</script></head>
<body>
<main class="gate-page page-enter" x-data="{saving:false}">
    <section class="gate-card security-setup-card">
        <a class="brand" href="/security/setup">
            <span class="brand-symbol"><span></span><span></span><span></span></span>
            <strong>راستا<small>هر قدم، نزدیک‌تر به هدف</small></strong>
        </a>
        <h1>راه‌اندازی امنیت راستا</h1>
        <p class="muted">برای ادامه، حساب مدیر و رمز ورود اعضا را مشخص کنید.</p>
        <form method="POST" action="/api/security/setup" @submit="saving=true">
            @csrf
            <h2>حساب مدیر</h2>
            <label>نام و نام خانوادگی مدیر<input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"></label>
            @error('name')<small class="field-error">{{ $message }}</small>@enderror
            <label>عنوان شغلی<input name="job_title" value="{{ old('job_title') }}" required maxlength="100" autocomplete="organization-title"></label>
            @error('job_title')<small class="field-error">{{ $message }}</small>@enderror
            <h2>پین مدیر</h2>
            <label>پین جدید مدیر<input type="password" name="admin_pin" required inputmode="numeric" maxlength="12" autocomplete="new-password"></label>
            @error('admin_pin')<small class="field-error">{{ $message }}</small>@enderror
            <label>تکرار پین مدیر<input type="password" name="admin_pin_confirmation" required inputmode="numeric" maxlength="12" autocomplete="new-password"></label>
            @error('admin_pin_confirmation')<small class="field-error">{{ $message }}</small>@enderror
            <h2>پین مشترک اعضا</h2>
            <label>پین مشترک اعضا<input type="password" name="member_pin" required inputmode="numeric" maxlength="12" autocomplete="new-password"></label>
            @error('member_pin')<small class="field-error">{{ $message }}</small>@enderror
            <label>تکرار پین مشترک اعضا<input type="password" name="member_pin_confirmation" required inputmode="numeric" maxlength="12" autocomplete="new-password"></label>
            @error('member_pin_confirmation')<small class="field-error">{{ $message }}</small>@enderror
            <button class="button primary" type="submit" :disabled="saving" x-text="saving?'در حال ذخیره...':'تکمیل راه‌اندازی'"></button>
        </form>
    </section>
</main>
</body>
</html>

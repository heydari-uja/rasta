# راستا (PHP خام)

نسخهٔ PHP خام راستا روی ساختار PHP_BASE — بدون Laravel، با رندر صفحات PHP و چند endpoint سادهٔ JSON برای Alpine.

## نیازمندی

- PHP 8.1+ (PDO MySQL، GD، OpenSSL)
- MySQL 8 / MariaDB
- ServBay یا Apache با `mod_rewrite`

## راه‌اندازی

1. در phpMyAdmin فایل `rasta.sql` را از ریشهٔ پروژه Import کنید (دیتابیس `rasta` ساخته می‌شود).
2. در `config/config.php` مقادیر `DB_HOST`، `DB_USERNAME`، `DB_PASSWORD`، `DB_NAME` را تنظیم کنید.
3. Document Root سایت را روی **همین پوشه** (جایی که `index.php` و `.htaccess` هستند) بگذارید — نه فقط `public/`.
4. پوشهٔ `storage/private` باید برای PHP قابل نوشتن باشد.
5. PIN موقت راه‌اندازی مدیر: `4868` (ثابت `RASTA_BOOTSTRAP_PIN` در `config/config.php`).

## اولین ورود

1. `/login` → حالت **مدیر** → PIN `4868`
2. تکمیل `/security/setup` (پین مدیر + پین مشترک اعضا)
3. ورود اعضا با پین مشترک و انتخاب پروفایل در `/profiles`

## ساختار

```
index.php          فرانت‌کنترلر
.htaccess          روتینگ Apache
config/            تنظیمات
database/          کلاس Database
helpers/           uri، csrf، flash، asset
lib/               Validator، AuthGate، PinSecurity، JsonPresenter
activities/        Auth، Security، Workspace
routes/web.php     مسیرها
template/          قالب‌های PHP
public/app/        CSS/JS بیلدشده (Alpine + Tailwind)
storage/private/   پیوست و آواتار
rasta.sql          اسکیمای MySQL
```

ظاهر و فلو همان نسخهٔ قبلی است؛ تعامل‌های کانبان/مودال همچنان با `fetch` به `/api/*` کار می‌کنند.

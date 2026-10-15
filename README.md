# راستا — هر قدم، نزدیک‌تر به هدف

فضای کار تیمی فارسی و RTL با Laravel 12، Blade، Alpine.js و Tailwind CSS/Vite. فونت Vazirmatn با وزن‌های ۴۰۰ تا ۷۰۰ محلی است. نصب خالی است؛ هیچ Seeder یا حساب، پروژه و برچسب نمونه‌ای وجود ندارد.

## اجرا

نیازمندی‌های نسخه قفل‌شده: PHP 8.4+ و افزونه‌های ctype، curl، dom، fileinfo، intl، mbstring، PDO، pdo_sqlite (یا pdo_mysql)، tokenizer، xml، xmlwriter؛ Composer 2؛ Node.js 22.12+ و npm. SQLite برای توسعه و MySQL 8 برای سرور قابل استفاده است.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan uja:setup
npm ci
npm run build
php artisan serve
```

سپس نشانی‌ای را که `artisan serve` چاپ می‌کند در مرورگر باز کنید. در دستور `uja:setup` رمز مشترک را دو بار با ورودی مخفی وارد کنید. فقط هش آن در `.env` ذخیره می‌شود؛ رمز پیش‌فرض وجود ندارد. رمز را در فرمان، README، Git یا پیام ننویسید. پس از ورود، «شما کی هستید؟» نمایش داده می‌شود و می‌توانید پروفایل خود را انتخاب کنید یا نام و عنوان شغلی خود را اضافه کنید. انتخاب پروفایل روی دستگاه به خاطر سپرده می‌شود. منوی پایین سایدبار «تغییر پروفایل» و «خروج» دارد. تغییر رمز مشترک، دسترسی نشست‌های قبلی را باطل می‌کند.

برای توسعه CSS/JS، `npm run dev` را در ترمینال دیگری اجرا کنید. برای MySQL، پیش از migrate مقادیر DB_CONNECTION=mysql، DB_HOST، DB_PORT، DB_DATABASE، DB_USERNAME و DB_PASSWORD را فقط در `.env` تنظیم کنید؛ دیتابیس باید موجود باشد. آزمون مستقل MySQL در این تحویل انجام نشده است.

## آزمون‌ها

```bash
php artisan test
./vendor/bin/pint --test
npm run build
npm run test:browser
```

آزمون مرورگر به Chromium نیاز دارد؛ مسیر پیش‌فرض `/usr/bin/chromium` است و می‌توان `CHROMIUM_PATH` را تنظیم کرد. runner یک دیتابیس SQLite موقت و رمز تصادفی فقط در حافظه ایجاد می‌کند، سرور آزمون را روی پورت 8010 اجرا می‌کند و در پایان دیتابیس موقت را پاک می‌کند. داده آزمون وارد دیتابیس اصلی نمی‌شود. اسکرین‌شات‌های تولیدشده در `storage/browser-results` قرار می‌گیرند و در Git یا ZIP تحویلی نیستند. ثبت trace و video خاموش است تا ورودی رمز ذخیره نشود.

## امنیت و نگهداری

همه اعضای فعال دسترسی مشترک به پروژه‌ها و تسک‌ها دارند. نقش owner/viewer، ثبت‌نام ایمیلی و رمز شخصی وجود ندارد. برچسب شخصی فقط برای سازنده دیده می‌شود. نظر و پیوست فقط توسط نویسنده ویرایش/حذف می‌شود. فایل‌ها در `storage/app/private/attachments` نگهداری می‌شوند و فقط از مسیر احراز هویت‌شده دانلود می‌شوند؛ `storage:link` لازم نیست. محدودیت هر فایل ۵ مگابایت؛ فرمت مجاز PDF، PNG، JPEG، WebP و TXT است.

CSRF، محدودسازی ورود، نشست Laravel، اعتبارسنجی و policy سمت سرور فعال‌اند. اطلاعات در DB با تاریخ ISO ذخیره و در رابط با تقویم شمسی و ارقام فارسی نمایش داده می‌شود. فقط پوشه `public` را به عنوان web root منتشر کنید؛ روی سرور HTTPS، APP_ENV=production، APP_DEBUG=false و SESSION_SECURE_COOKIE=true تنظیم شود. هیچ pipeline یا GitHub Actions ایجاد نشده است.

پیش از commit:

```bash
python3 scripts/scan-secrets.py
```

Git فایل `.env`، وابستگی‌ها، دیتابیس SQLite و محتوای storage را نادیده می‌گیرد. `.env.example` فقط نام تنظیمات و جای خالی هش دارد. فایل‌های خصوصی و دیتابیس را جداگانه و امن پشتیبان بگیرید. پروژه به داده نمایشی، CDN یا سرویس بیرونی برای اجرا وابسته نیست.

[طرح ساخت](docs/PLAN.md) · [فرض‌ها](ASSUMPTIONS.md) · [گزارش تحویل](docs/DELIVERY.md)

## نمونه خروجی

[گالری تصاویر واقعی برنامه](docs/PREVIEW.md) شامل خانه، پروژه، مودال تسک و نمایش موبایل است.

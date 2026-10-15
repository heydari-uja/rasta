<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
Artisan::command('uja:setup', function () {
 if (!is_file(base_path('.env'))) { $this->error('ابتدا فایل .env را از .env.example بسازید.'); return 1; }
 $password=$this->secret('رمز مشترک فضای کار (حداقل ۱۰ نویسه)');
 if (!is_string($password) || mb_strlen($password)<10) { $this->error('رمز باید حداقل ۱۰ نویسه باشد.'); return 1; }
 if ($password!==$this->secret('تکرار رمز مشترک')) { $this->error('تکرار رمز یکسان نیست.'); return 1; }
 $line="UJA_PASSWORD_HASH='".Hash::make($password)."'"; unset($password);
 $path=base_path('.env'); $contents=file_get_contents($path);
 $contents=preg_match('/^UJA_PASSWORD_HASH=.*$/m',$contents) ? preg_replace('/^UJA_PASSWORD_HASH=.*$/m', str_replace('$','\\$', $line),$contents) : rtrim($contents)."\n".$line."\n";
 file_put_contents($path,$contents,LOCK_EX); chmod($path,0600);
 $this->callSilent('config:clear');
 $this->info('هش رمز ذخیره شد. اکنون وارد شوید و پروفایل خود را انتخاب کنید.');
})->purpose('تنظیم رمز مشترک؛ فقط هش در .env ذخیره می‌شود');

<?php /* converted from profiles.blade.php */ ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="dark" data-theme="dark" data-theme-preference="dark">

<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="">
    <title>راستا — هر قدم، نزدیک‌تر به هدف</title><?= assetHead() ?><script>
        const themeMeta = document.querySelector("meta[name=theme-color]");
        if (themeMeta) themeMeta.content = getComputedStyle(document.documentElement).getPropertyValue("--surface-page").trim()
    </script>
</head>

<body>
    <main class="gate-page page-enter" x-data="{saving:false}">
        <section class="gate-card"><a class="brand" href="/login"><span class="brand-symbol"><span></span><span></span><span></span></span><strong>راستا<small>هر قدم، نزدیک‌تر به هدف</small></strong></a>
            <h1>شما کی هستید؟</h1>
            <p class="muted">پروفایل خودتان را انتخاب کنید یا به تیم بپیوندید.</p>
            <div class="profile-list"><?php foreach ($members as $member): ?><form method="POST" action="/profiles"><?= csrf_field() ?><input type="hidden" name="member_id" value="<?= e($member['id']) ?>"><button class="profile-choice"><span class="avatar"><?php if (!empty($member['avatar_path'])): ?><img src="<?= e(url('avatars/' . $member['id'])) ?>" alt="<?= e($member['name']) ?>"><?php else: ?><span class="avatar-initials" style="--avatar-marker:var(--project-marker-4)"><?= e(memberInitials($member['name'])) ?></span><?php endif; ?></span><span><?= e($member['name']) ?><small><?= e($member['job_title']) ?></small></span></button></form><?php endforeach; ?></div>
            <form method="POST" action="/profiles" @submit="saving=true"><?= csrf_field() ?><h2>افزودن خودم</h2><label>نام و نام خانوادگی<input name="name" value="<?= e(old('name')) ?>" required maxlength="100"><?= fieldError('name') ?></label><label>عنوان شغلی<input name="job_title" value="<?= e(old('job_title')) ?>" required maxlength="100"><?= fieldError('job_title') ?></label><button class="button button-primary" :disabled="saving" :aria-busy="saving?'true':'false'"><span class="button-spinner" x-show="saving" x-cloak x-html="$icon('LoaderCircle')"></span><span>افزودن خودم</span></button></form>
        </section>
    </main>
</body>

</html>
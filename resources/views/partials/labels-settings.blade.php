<section x-show="page==='labels'&&false" class="page-enter">
<div x-show="!labelFilterId"><div class="page-heading"><div><h1>برچسب‌ها</h1><p>برچسب‌های ساده برای پیدا کردن سریع‌تر کارها.</p></div><button class="button primary" @click="openLabel()"><span x-html="$icon('Plus')"></span>افزودن برچسب</button></div>
<div class="labels-list"><template x-for="l in labels" :key="l.id"><div class="label-list-row"><button class="label-row-main" @click="labelFilterId=l.id"><span class="label-hash" :style="`--label-color:var(--${markerToken(l.color)})`">#</span><strong x-text="l.name"></strong><span class="muted" x-text="fa(tasks.filter(t=>!t.completed_at&&t.labels.some(x=>x.id===l.id)).length)+' تسک'"></span></button><div x-data="{open:false}" class="label-row-menu" @click.outside="open=false"><button class="icon-btn" @click="open=!open" x-html="$icon('MoreHorizontal')" :aria-label="'گزینه‌های '+l.name"></button><div class="dropdown-menu" x-show="open" x-transition.opacity.scale.95.duration.150ms><button @click="openLabel(l);open=false">ویرایش</button><button class="danger-text" @click="deleteLabel(l);open=false">حذف</button></div></div></div></template><div class="empty-state" x-show="!labels.length"><h2>هنوز برچسبی ندارید</h2><p>برای دسته‌بندی کارها اولین برچسب را بسازید.</p></div></div></div>
<div x-show="labelFilterId"><div class="page-heading"><div><button class="text-button back-link" @click="labelFilterId=null"><span x-html="$icon('ArrowLeft')"></span>همه برچسب‌ها</button><h1><span class="label-hash" :style="`--label-color:var(--${markerToken(selectedLabel?.color)})`">#</span> <span x-text="selectedLabel?.name"></span></h1><p x-text="fa(selectedLabelTasks.length)+' تسک با این برچسب'"></p></div></div><div class="label-task-list"><template x-for="t in selectedLabelTasks" :key="t.id"><button class="label-task-row" @click="openTask(t)"><span class="completion" :class="{'checked':t.completed_at}"></span><span><strong x-text="t.title"></strong><small x-text="projects.find(p=>p.id===t.project_id)?.name"></small></span><span class="task-date" x-text="t.due_date?date(t.due_date):'بدون تاریخ'"></span></button></template><p class="empty-state" x-show="!selectedLabelTasks.length">تسکی با این برچسب نیست.</p></div></div>
</section>
<section x-show="page==='settings'" class="page-enter settings-page">
    <header class="page-heading settings-page-heading">
        <div>
            <h1>تنظیمات</h1>
        </div>
    </header>

    <div class="settings-layout {{ auth()->user()?->role === 'admin' ? '' : 'settings-layout-account-only' }}">
        @if(auth()->user()?->role === 'admin')
        <nav class="settings-nav" aria-label="بخش‌های تنظیمات">
            <button type="button" :class="{'selected':settingsSection==='account'}" :aria-current="settingsSection==='account'?'page':null" @click="settingsSection='account'">
                <span aria-hidden="true" x-html="$icon('UserRound')"></span><span>حساب کاربری</span>
            </button>
            @if(auth()->user()?->role === 'admin')
            <button type="button" :class="{'selected':settingsSection==='security'}" :aria-current="settingsSection==='security'?'page':null" @click="settingsSection='security'">
                <span aria-hidden="true" x-html="$icon('ShieldCheck')"></span><span>امنیت</span>
            </button>
            @endif
        </nav>
        @endif

        <div class="settings-main">
            <section x-show="settingsSection==='account'" class="settings-section" aria-labelledby="settings-account-title">
                <header class="settings-section-heading">
                    <h2 id="settings-account-title">حساب کاربری</h2>
                </header>

                <div class="settings-panel panel">
                    <div class="settings-profile-row">
                        <input x-ref="settingsAvatarInput" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" :aria-label="user.avatar_url?'تغییر پروفایل':'افزودن تصویر'" :disabled="avatarUploading" @change="uploadAvatar($event.target.files[0]);$event.target.value=''">
                        <button type="button" class="settings-avatar-button" :class="{'is-loading':avatarUploading}" :disabled="avatarUploading" @click="$refs.settingsAvatarInput.click()" :aria-label="user.avatar_url?'تغییر پروفایل':'افزودن تصویر'">
                            <span class="avatar settings-avatar">
                                <template x-if="user.avatar_url"><img :src="user.avatar_url" :alt="user.name"></template>
                                <template x-if="!user.avatar_url"><span class="avatar-upload-initials" :style="`--avatar-marker:${color(user.id)}`" x-text="initials(user.name)"></span></template>
                            </span>
                        </button>
                        <div class="settings-profile-copy">
                            <h3 x-text="user.name"></h3>
                            <button type="button" class="text-button settings-avatar-change" :disabled="avatarUploading" @click="$refs.settingsAvatarInput.click()" x-text="avatarUploading?'در حال بارگذاری...':(user.avatar_url?'تغییر پروفایل':'افزودن تصویر')"></button>
                        </div>
                    </div>

                    <form id="settings-account-form" class="settings-account-form" @submit.prevent="saveSettings()">
                        <label for="settings-account-name">نام و نام خانوادگی
                            <input id="settings-account-name" x-model="account.name" required maxlength="100" autocomplete="name" :disabled="busy" :aria-invalid="err('name')?'true':'false'" aria-describedby="settings-name-error" @input="delete errors.name">
                            <small id="settings-name-error" class="field-error" x-text="err('name')"></small>
                        </label>
                        <label for="settings-account-job-title">عنوان شغلی
                            <input id="settings-account-job-title" x-model="account.job_title" required maxlength="100" autocomplete="organization-title" :disabled="busy" :aria-invalid="err('job_title')?'true':'false'" aria-describedby="settings-job-error" @input="delete errors.job_title">
                            <small id="settings-job-error" class="field-error" x-text="err('job_title')"></small>
                        </label>
                        <div class="settings-account-actions">
                            <button type="button" class="button subtle" x-show="account.name!==user.name || account.job_title!==user.job_title" :disabled="busy" @click="account={name:user.name,job_title:user.job_title};errors={}">لغو تغییرات</button>
                            <button class="button primary" :disabled="busy || (account.name===user.name && account.job_title===user.job_title)" type="submit" x-text="busy?'در حال ذخیره...':'ذخیره تغییرات'"></button>
                        </div>
                    </form>
                </div>

                <section class="settings-logout" aria-labelledby="settings-logout-title">
                    <h3 id="settings-logout-title">خروج از حساب</h3>
                    <form method="POST" action="/logout">
                        @csrf
                        <button class="button danger settings-logout-button" type="submit"><span aria-hidden="true" x-html="$icon('LogOut')"></span>خروج</button>
                    </form>
                </section>
            </section>


            @if(auth()->user()?->role === 'admin')
            <section x-show="settingsSection==='security'&&user.role==='admin'" class="settings-section" aria-labelledby="settings-security-title">
                <header class="settings-section-heading">
                    <h2 id="settings-security-title">امنیت و حریم خصوصی</h2>
                </header>

                <div class="settings-security-list">
                    <article class="settings-security-row">
                        <div class="settings-row-copy">
                            <h3>رمز مدیر</h3>
                        </div>
                        <button type="button" class="button settings-row-action" @click="openSecurityDialog('admin')">تغییر رمز</button>
                    </article>
                    <article class="settings-security-row">
                        <div class="settings-row-copy">
                            <div class="settings-security-title-row">
                                <h3>رمز مشترک اعضا</h3>
                                <span class="settings-security-status" x-show="security?.member_pin_configured"><i aria-hidden="true"></i>تنظیم شده</span>
                                <span class="settings-security-status is-unconfigured" x-show="!security?.member_pin_configured"><i aria-hidden="true"></i>تنظیم نشده</span>
                            </div>
                            <small class="settings-security-note" x-show="!security?.member_pin_configured">ورود اعضا تا زمان تنظیم این رمز غیرفعال است.</small>
                        </div>
                        <button type="button" class="button settings-row-action" @click="openSecurityDialog('member')" x-text="security?.member_pin_configured?'تغییر رمز':'تنظیم رمز'"></button>
                    </article>
                </div>
            </section>
            @endif
        </div>
    </div>

    @if(auth()->user()?->role === 'admin')
    <div class="settings-security-backdrop" x-show="securityDialog!==''" x-cloak x-transition.opacity @click.self="closeSecurityDialog()" @keydown.escape.stop="closeSecurityDialog()">
        <section class="settings-security-dialog" x-ref="securityDialogPanel" @click.stop @keydown.tab="trapSecurityDialog($event)" role="dialog" aria-modal="true" aria-labelledby="settings-security-dialog-title">
            <header class="settings-dialog-heading">
                <div>
                    <h2 id="settings-security-dialog-title" x-text="securityDialog==='admin'?'تغییر رمز مدیر':(security?.member_pin_configured?'تغییر رمز مشترک اعضا':'تنظیم رمز مشترک اعضا')"></h2>
                </div>
                <button type="button" class="icon-btn settings-dialog-close" aria-label="بستن پنجره" :disabled="securityBusy!==''" @click="closeSecurityDialog()" x-html="$icon('X')"></button>
            </header>

            <form x-show="securityDialog==='admin'" x-ref="adminPinForm" class="settings-security-form" @submit.prevent="saveAdminPin($event.currentTarget)">
                <div class="settings-dialog-body">
                    <label for="admin-pin-current">رمز فعلی مدیر
                        <input id="admin-pin-current" x-ref="adminPinCurrent" name="current_pin" type="password" required inputmode="numeric" maxlength="12" autocomplete="current-password" :disabled="securityBusy!==''" :aria-invalid="err('current_pin')?'true':'false'">
                        <small class="field-error" x-text="err('current_pin')"></small>
                    </label>
                    <label for="admin-pin-new">رمز جدید
                        <input id="admin-pin-new" name="pin" type="password" required inputmode="numeric" maxlength="12" autocomplete="new-password" :disabled="securityBusy!==''" :aria-invalid="err('pin')?'true':'false'">
                        <small class="field-error" x-text="err('pin')"></small>
                    </label>
                    <label for="admin-pin-confirmation">تکرار رمز جدید
                        <input id="admin-pin-confirmation" name="pin_confirmation" type="password" required inputmode="numeric" maxlength="12" autocomplete="new-password" :disabled="securityBusy!==''" :aria-invalid="err('pin_confirmation')?'true':'false'">
                        <small class="field-error" x-text="err('pin_confirmation')"></small>
                    </label>
                </div>
                <footer class="settings-dialog-actions">
                    <button type="button" class="button subtle" :disabled="securityBusy!==''" @click="closeSecurityDialog()">انصراف</button>
                    <button type="submit" class="button primary" :disabled="securityBusy!==''" x-text="securityBusy==='admin'?'در حال ذخیره...':'تغییر رمز'"></button>
                </footer>
            </form>

            <form x-show="securityDialog==='member'" x-ref="memberPinForm" class="settings-security-form" @submit.prevent="saveMemberPin($event.currentTarget)">
                <div class="settings-dialog-body">
                    <label for="member-pin-current">رمز فعلی مدیر
                        <input id="member-pin-current" x-ref="memberPinCurrent" name="current_pin" type="password" required inputmode="numeric" maxlength="12" autocomplete="current-password" :disabled="securityBusy!==''" :aria-invalid="err('current_pin')?'true':'false'">
                        <small class="field-error" x-text="err('current_pin')"></small>
                    </label>
                    <label for="member-pin-new">رمز مشترک جدید
                        <input id="member-pin-new" name="pin" type="password" required inputmode="numeric" maxlength="12" autocomplete="new-password" :disabled="securityBusy!==''" :aria-invalid="err('pin')?'true':'false'">
                        <small class="field-error" x-text="err('pin')"></small>
                    </label>
                    <label for="member-pin-confirmation">تکرار رمز جدید
                        <input id="member-pin-confirmation" name="pin_confirmation" type="password" required inputmode="numeric" maxlength="12" autocomplete="new-password" :disabled="securityBusy!==''" :aria-invalid="err('pin_confirmation')?'true':'false'">
                        <small class="field-error" x-text="err('pin_confirmation')"></small>
                    </label>
                </div>
                <footer class="settings-dialog-actions">
                    <button type="button" class="button subtle" :disabled="securityBusy!==''" @click="closeSecurityDialog()">انصراف</button>
                    <button type="submit" class="button primary" :disabled="securityBusy!==''" x-text="securityBusy==='member'?'در حال ذخیره...':'ذخیره رمز'"></button>
                </footer>
            </form>
        </section>
    </div>
    @endif
</section>

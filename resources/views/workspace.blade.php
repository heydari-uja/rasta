<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>راستا — هر قدم، نزدیک‌تر به هدف</title><script>(()=>{const t=localStorage.getItem('rasta-theme')||'system';const dark=t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',dark);document.documentElement.dataset.theme=dark?'dark':'light';document.documentElement.dataset.themePreference=t})()</script>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body><div x-data="workspace" class="app-shell" x-cloak>
<div class="sidebar-backdrop" x-show="sidebar" x-transition.opacity.duration.150ms @click="sidebar=false"></div>
<aside class="sidebar" :class="{'is-open':sidebar}" aria-label="ناوبری راستا">
 <a href="/tasks" @click.prevent="go('tasks')" class="brand"><span class="brand-symbol" aria-hidden="true"><span></span><span></span><span></span></span><strong>راستا<span>فضای کار تیمی</span></strong></a>
 <nav aria-label="ناوبری اصلی">
  <a href="/tasks" @click.prevent="go('tasks')" :class="{'active':page==='tasks'}" :aria-current="page==='tasks'?'page':null"><span aria-hidden="true" x-html="$icon('CheckCheck')"></span><span>تسک‌های من</span><span class="nav-count" x-text="fa(myTasks.filter(t=>!t.completed_at).length)"></span></a>
  <a href="/labels" x-show="false" @click.prevent="go('labels')" :class="{'active':page==='labels'}" :aria-current="page==='labels'?'page':null"><span aria-hidden="true" x-html="$icon('Tags')"></span><span>برچسب‌ها</span></a>
 </nav>
 <section class="sidebar-projects" aria-labelledby="sidebar-projects-title">
  <div class="eyebrow sidebar-section-heading"><span class="sidebar-projects-title-wrap"><span id="sidebar-projects-title">پروژه‌های شما</span></span><button type="button" class="icon-btn" @click="openProject()" aria-label="ساخت پروژه جدید" title="ساخت پروژه جدید" x-html="$icon('Plus')"></button></div>
  <template x-for="p in projects" :key="p.id"><div class="project-shortcut-wrap"><button type="button" class="project-shortcut" @click="go('projects',p.id)" :class="{'active':page==='projects'&&projectId===p.id}" :aria-current="page==='projects'&&projectId===p.id?'page':null"><i aria-hidden="true" :style="`background:${p.color}`"></i><span x-text="p.name"></span></button><button type="button" class="project-edit-btn" @click.stop="openProjectEdit(p)" :aria-label="'ویرایش پروژه '+p.name" :title="'ویرایش '+p.name" x-html="$icon('Pencil')"></button></div></template>
  <div class="sidebar-empty-projects" x-show="!loading&&!projects.length"><span>هنوز پروژه‌ای ندارید.</span><button type="button" class="text-button" @click="openProject()">ساخت اولین پروژه</button></div>
 </section>
 <div class="sidebar-bottom">
  <div class="sidebar-bottom-row">
   <button type="button" class="user-account" @click="go('settings')" :class="{'active':page==='settings'}" :aria-current="page==='settings'?'page':null" aria-label="تنظیمات حساب"><span class="avatar"><img x-show="user.avatar_url" :src="user.avatar_url" :alt="user.name"><span x-show="!user.avatar_url" :style="`background:${color(user.id)}`" x-text="initials(user.name)"></span></span><span><strong x-text="user.name"></strong></span><span class="user-account-settings" aria-hidden="true" x-html="$icon('ChevronLeft')"></span></button>
   <div class="notification-wrap sidebar-notification" @click.outside="notificationsOpen=false">
    <button type="button" class="sidebar-notification-button profile-notification-button" :class="{'is-open':notificationsOpen}" x-ref="sidebarNotificationButton" @click="toggleSidebarNotifications()" aria-label="اعلان‌ها" aria-controls="sidebar-notification-panel" :aria-expanded="notificationsOpen.toString()"><span aria-hidden="true" x-html="$icon('Bell')"></span><span class="sr-only">اعلان‌ها</span><span class="notification-count" x-show="unread" x-text="fa(unread)"></span></button>
    <div id="sidebar-notification-panel" x-ref="sidebarNotificationPanel" class="notification-panel sidebar-notification-panel" role="region" aria-label="فهرست اعلان‌ها" x-show="notificationsOpen" x-cloak x-transition.opacity.scale.95.duration.150ms>
     <div class="notification-head">
      <div class="notification-head-copy"><strong>اعلان‌ها</strong><small x-text="unread?fa(unread)+' اعلان خوانده‌نشده':'همه‌چیز به‌روز است'"></small></div>
      <div class="notification-head-actions"><button type="button" class="notification-read-all" x-show="unread" @click="readNotifications()">خواندن همه</button><button type="button" class="notification-close" @click="notificationsOpen=false" aria-label="بستن اعلان‌ها" x-html="$icon('X')"></button></div>
     </div>
     <div class="notification-list">
      <template x-for="n in notifications" :key="n.id"><button type="button" class="notification-item" :class="{'unread':!n.read_at}" @click="openNotification(n)"><span class="avatar small"><img x-show="n.author?.avatar_url" :src="n.author?.avatar_url" :alt="n.author?.name"><span x-show="!n.author?.avatar_url" :style="`background:${color(n.author_id)}`" x-text="initials(n.author?.name)"></span></span><span class="notification-copy"><strong><span x-text="n.author?.name"></span> <span>روی تسک </span><b x-text="n.task?.title"></b><span> نظر داد</span></strong><span class="notification-excerpt" x-text="n.excerpt"></span><small x-text="relativeTime(n.created_at)"></small></span><i class="notification-unread-dot" x-show="!n.read_at"></i></button></template>
      <div class="notification-empty" x-show="!notifications.length"><span class="notification-empty-icon" aria-hidden="true" x-html="$icon('Bell')"></span><strong>فعلاً اعلانی نیست</strong><small>وقتی روی تسک‌ها فعالیتی باشد، اینجا می‌بینی.</small></div>
     </div>
    </div>
   </div>
  </div>
 </div>
</aside>
<div class="main-shell"><header class="topbar"><button class="icon-btn mobile-menu" @click="sidebar=!sidebar" aria-label="باز کردن منو" x-html="$icon('Menu')"></button></header>
<main class="content"><div x-show="loading" class="skeleton-wrap"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div><div x-show="loadError" class="error-banner"><span x-text="loadError"></span><button class="button" @click="refresh()">تلاش دوباره</button></div><div x-show="!loading&&!loadError">
@include('partials.projects')
@include('partials.tasks')
@include('partials.labels-settings')
</div></main></div>
@include('partials.modals')
<div class="confirm-backdrop" x-show="confirmBox" x-cloak x-transition.opacity.duration.150ms @keydown.escape.stop="confirmBox=null"><section class="confirm-dialog" x-show="confirmBox" x-transition.opacity.scale.95.duration.150ms role="alertdialog" aria-modal="true" aria-labelledby="confirm-title"><h2 id="confirm-title" x-text="confirmBox?.title"></h2><p x-text="confirmBox?.body"></p><div class="confirm-transfer" x-show="confirmBox?.transferOptions?.length"><span>انتقال تسک‌ها به</span><x-ui-select model="confirmBox.transferId" options="(confirmBox?.transferOptions||[]).map(o=>({value:o.id,label:o.title}))" placeholder="ستون مقصد" /></div><div class="button-row confirm-actions"><button class="button subtle" @click="confirmBox=null" autofocus>انصراف</button><button class="button danger" @click="confirm()" x-text="confirmBox?.label"></button></div></section></div>
<div class="toast snackbar" x-show="toast" x-transition :class="{'toast-error':toastError,'has-action':toastActionTaskId}" role="status"><span class="snackbar-status" x-html="$icon(toastError?'X':'CheckCircle2')"></span><span class="snackbar-message" x-text="toast"></span><button type="button" class="snackbar-action" x-show="toastActionTaskId" x-text="toastActionLabel" @click="undoCompletedTask()"></button><button class="icon-btn snackbar-close" @click="clearToast()" aria-label="بستن پیام" x-html="$icon('X')"></button><span class="snackbar-timeout" x-show="toastActionTaskId"></span></div>
</div></body></html>

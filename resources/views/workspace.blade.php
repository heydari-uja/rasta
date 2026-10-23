<!DOCTYPE html><html lang="fa" dir="rtl" class="dark" data-theme="dark" data-theme-preference="dark"><head><link rel="icon" type="image/svg+xml" href="/favicon.svg"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content=""><title>راستا — هر قدم، نزدیک‌تر به هدف</title>@vite(['resources/css/app.css','resources/js/app.js'])<script>const themeMeta=document.querySelector("meta[name=theme-color]");if(themeMeta)themeMeta.content=getComputedStyle(document.documentElement).getPropertyValue("--surface-page").trim()</script></head>
<body><div x-data="workspace" class="app-shell" x-cloak>
<aside class="sidebar" :class="{'is-collapsed':sidebarCollapsed}" aria-label="ناوبری راستا">
 <div class="sidebar-header">
  <a href="/tasks" @click.prevent="go('tasks')" class="sidebar-brand" x-show="!sidebarCollapsed"><span class="sidebar-brand-mark" aria-hidden="true"><span class="brand-symbol"><span></span><span></span><span></span></span></span><strong>راستا</strong></a>
  <button type="button" class="sidebar-logo-toggle" x-show="sidebarCollapsed" x-ref="sidebarBrandToggle" @mouseenter="showSidebarTooltip($refs.sidebarBrandToggle)" @mouseleave="hideSidebarTooltip()" @focus="if($event.target.matches(':focus-visible'))showSidebarTooltip($refs.sidebarBrandToggle)" @blur="hideSidebarTooltip()" @click="hideSidebarTooltip();toggleSidebarCollapsed()" aria-label="باز کردن منو" aria-describedby="sidebar-toggle-tooltip"><span class="sidebar-brand-mark" aria-hidden="true"><span class="brand-symbol"><span></span><span></span><span></span></span></span><span class="sidebar-open-glyph" aria-hidden="true" x-html="$icon('PanelRight')"></span></button>
  <button type="button" class="sidebar-collapse-toggle" x-show="!sidebarCollapsed" x-ref="sidebarCollapseToggle" @mouseenter="showSidebarTooltip($refs.sidebarCollapseToggle)" @mouseleave="hideSidebarTooltip()" @focus="if($event.target.matches(':focus-visible'))showSidebarTooltip($refs.sidebarCollapseToggle)" @blur="hideSidebarTooltip()" @click="hideSidebarTooltip();toggleSidebarCollapsed()" aria-label="جمع کردن منو" aria-describedby="sidebar-toggle-tooltip"><span aria-hidden="true" x-html="$icon('PanelRight')"></span></button>
 </div>
 <nav class="sidebar-nav" aria-label="ناوبری اصلی">
  <button type="button" class="sidebar-item" :class="{'is-active':page==='tasks'}" :aria-current="page==='tasks'?'page':null" @click="go('tasks')"><span class="sidebar-item-icon" aria-hidden="true" x-html="$icon('CheckCheck')"></span><span class="sidebar-label">تسک‌های من</span><span class="sidebar-count" x-text="fa(myTasks.filter(t=>!t.completed_at).length)"></span></button>
  <div class="sidebar-divider" aria-hidden="true"></div>
  <section class="sidebar-projects" aria-labelledby="sidebar-projects-title">
   <div class="sidebar-section-heading">
    <button type="button" class="sidebar-fold" @click="projectsExpanded=!projectsExpanded" :aria-expanded="projectsExpanded.toString()" aria-controls="sidebar-project-list"><span class="sidebar-fold-icon" aria-hidden="true" x-html="$icon('ChevronDown')"></span><span class="sidebar-label" id="sidebar-projects-title">پروژه‌های شما</span></button>
    <button type="button" class="sidebar-add" @click="openProject()" aria-label="پروژه جدید" title="پروژه جدید" x-html="$icon('Plus')"></button>
   </div>
   <div id="sidebar-project-list" class="sidebar-project-list" :class="{'is-closed':!projectsExpanded}" x-show="projectsExpanded">
<template x-for="p in projects" :key="p.id"><div class="sidebar-project-row"><button type="button" class="sidebar-item sidebar-project-item" @click="go('projects',p.id)" :class="{'is-active':page==='projects'&&Number(projectId)===Number(p.id)}" :aria-current="page==='projects'&&Number(projectId)===Number(p.id)?'page':null" :aria-label="p.name" :title="p.name" :style="`--project-marker:var(--${markerToken(p.color)})`"><span class="sidebar-project-dot" aria-hidden="true"></span><span class="sidebar-label" x-text="p.name"></span><span class="sidebar-count" x-show="Number(p.tasks_count)>0" x-text="fa(p.tasks_count)"></span></button><button type="button" class="sidebar-project-edit" :disabled="page!=='projects'||Number(projectId)!==Number(p.id)" @click.stop="openProjectEdit(p)" :aria-label="'ویرایش پروژه '+p.name" :title="'ویرایش '+p.name"><span aria-hidden="true" x-html="$icon('Pencil')"></span></button></div></template>
    <div class="sidebar-empty-projects" x-show="!loading&&!projects.length"><span>هنوز پروژه‌ای ندارید.</span><button type="button" class="text-button" @click="openProject()">ساخت اولین پروژه</button></div>
   </div>
  </section>
 </nav>
 <footer class="sidebar-footer">
  <button type="button" class="sidebar-profile-button" @click="settingsSection='account';go('settings')" :aria-label="'تنظیمات حساب '+user.name">
   <span class="sidebar-avatar"><img x-show="user.avatar_url" :src="user.avatar_url" :alt="user.name"><span class="sidebar-avatar-fallback" x-show="!user.avatar_url" x-text="initials(user.name)"></span><i class="sidebar-online-dot" aria-label="آنلاین"></i></span>
   <span class="sidebar-profile-copy"><strong x-text="user.name"></strong><small x-show="user.job_title" x-text="user.job_title"></small></span>
  </button>
 </footer>
</aside>
<div id="sidebar-toggle-tooltip" class="sidebar-toggle-tooltip" role="tooltip" x-show="sidebarTooltipVisible" :style="sidebarTooltipStyle" @click="hideSidebarTooltip()" @pointerleave="hideSidebarTooltip()"><span x-text="sidebarCollapsed?'باز کردن منو':'جمع کردن منو'"></span><kbd dir="ltr">Ctrl+Shift+S</kbd></div>
<div class="main-shell"><header class="topbar"><button class="icon-btn mobile-menu" @click="toggleSidebarCollapsed()" aria-label="باز کردن منو" x-html="$icon('Menu')"></button></header>
<main class="content"><div x-show="loading" class="skeleton-wrap"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div><div x-show="loadError" class="error-banner"><span x-text="loadError"></span><button class="button" @click="refresh()">تلاش دوباره</button></div><div x-show="!loading&&!loadError">
@include('partials.projects')
@include('partials.tasks')
@include('partials.labels-settings')
</div></main></div>
@include('partials.modals')
<div class="confirm-backdrop" x-show="confirmBox" x-cloak x-transition.opacity.duration.150ms @keydown.escape.stop="confirmBox=null"><section class="confirm-dialog" x-show="confirmBox" x-transition.opacity.scale.95.duration.150ms role="alertdialog" aria-modal="true" aria-labelledby="confirm-title"><h2 id="confirm-title" x-text="confirmBox?.title"></h2><p x-text="confirmBox?.body"></p><div class="confirm-transfer" x-show="confirmBox?.transferOptions?.length"><span>انتقال تسک‌ها به</span><x-ui-select model="confirmBox.transferId" options="(confirmBox?.transferOptions||[]).map(o=>({value:o.id,label:o.title}))" placeholder="ستون مقصد" /></div><div class="button-row confirm-actions"><button class="button subtle" @click="confirmBox=null" autofocus>انصراف</button><button class="button danger" @click="confirm()" x-text="confirmBox?.label"></button></div></section></div>
<div class="toast snackbar" x-show="toast" x-transition :class="{'toast-error':toastError,'has-action':toastActionTaskId}" role="status"><span class="snackbar-status" x-html="$icon(toastError?'X':'CheckCircle2')"></span><span class="snackbar-message" x-text="toast"></span><button type="button" class="snackbar-action" x-show="toastActionTaskId" x-text="toastActionLabel" @click="undoCompletedTask()"></button><button class="icon-btn snackbar-close" @click="clearToast()" aria-label="بستن پیام" x-html="$icon('X')"></button><span class="snackbar-timeout" x-show="toastActionTaskId"></span></div>
</div></body></html>

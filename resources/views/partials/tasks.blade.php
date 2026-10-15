<section x-show="page==='tasks'" class="page-enter">
<div class="page-heading"><div><div class="eyebrow">تمرکز روی قدم بعدی</div><h1>تسک‌های من</h1><p>کارهایی که خودتان ساخته‌اید و در مسیر شخصی شما قرار دارند.</p></div><button class="button primary" @click="openTask()"><span x-html="$icon('Plus')"></span>افزودن تسک</button></div>
<div class="tasks-toolbar"><div class="segmented"><button :class="{'selected':taskFilter==='active'}" @click="taskFilter='active'">تسک‌های باز <span x-text="fa(myTasks.filter(t=>!t.completed_at).length)"></span></button><button :class="{'selected':taskFilter==='done'}" @click="taskFilter='done'">تکمیل‌شده‌ها</button><button :class="{'selected':taskFilter==='all'}" @click="taskFilter='all'">همه</button></div><div class="button-row"><x-ui-select model="selectedProject" options="[{value:'all',label:'همه پروژه‌ها'},...projects.map(p=>({value:p.id,label:p.name}))]" placeholder="همه پروژه‌ها" /><div class="view-switch"><button class="icon-btn" :class="{'selected':view==='board'}" @click="view='board'" aria-label="نمای برد" x-html="$icon('LayoutGrid')"></button><button class="icon-btn" :class="{'selected':view==='list'}" @click="view='list'" aria-label="نمای فهرست" x-html="$icon('List')"></button></div></div></div>
<template x-for="p in boardProjects" :key="p.id"><section class="task-project-section">
    <div class="section-heading"><h2><i class="project-dot" :style="`background:${p.color}`"></i><span x-text="p.name"></span></h2><button class="text-button" @click="go('projects',p.id)">مشاهده پروژه <span x-html="$icon('ArrowUpLeft')"></span></button></div>
    <div class="board" :class="{'list-view':view==='list'}">
        <template x-for="s in myStages(p)" :key="s.id"><section class="board-column">
            <div class="stage-column-head">
                <div class="stage-title-wrap">
                    <form class="inline-column-rename" x-show="columnRename?.id===s.id&&columnRename?.kind==='member'" @submit.prevent="saveColumnRename(s,'member',p)" @keydown.escape.prevent="cancelColumnRename()"><input data-column-rename-input x-model="columnRenameTitle" maxlength="120"></form>
                    <template x-if="!(columnRename?.id===s.id&&columnRename?.kind==='member')"><h3 x-text="s.title"></h3></template>
                    <span class="column-count" x-text="fa(stageTasks(s.id,true).length)"></span>
                </div>
                <div x-data="{open:false}" class="stage-menu" @click.outside="open=false"><button class="icon-btn" @click="open=!open" :aria-label="'گزینه‌های '+s.title" x-html="$icon('MoreHorizontal')"></button><div class="dropdown-menu" x-show="open" x-transition.opacity.scale.95.duration.150ms><button @click="beginColumnRename(s,'member',p,user);open=false">تغییر نام</button><button @click="openStage(s,user,p);open=false">تنظیم تاریخ</button><button class="danger-text" @click="deleteStage(s,'member',user);open=false">حذف ستون</button></div></div>
            </div>
            <div class="column-tasks"><template x-for="t in stageTasks(s.id,true)" :key="t.id">@include('partials.task-card')</template></div>
            <button class="add-column-task" x-show="canWrite(p)" @click="openTask(null,s,p)"><span class="add-task-plus"><span x-html="$icon('Plus')"></span></span><span>افزودن تسک</span></button>
        </section></template>
        <section class="board-column add-column-card" :class="{'is-editing':inlineColumn?.kind==='member'&&inlineColumn?.project_id===p.id}" x-show="canWrite(p)">
            <button class="add-column-trigger" x-show="inlineColumn?.kind!=='member'||inlineColumn?.project_id!==p.id" @click="startInlineColumn('member',p)"><span x-html="$icon('Plus')"></span><span>افزودن ستون</span></button>
            <form class="column-create-form" x-show="inlineColumn?.kind==='member'&&inlineColumn?.project_id===p.id" @submit.prevent="saveInlineColumn('member',p)" @keydown.escape.prevent="cancelInlineColumn()">
                <input data-inline-column-input x-model="inlineColumnForm.title" placeholder="نام این ستون" maxlength="120">
                <textarea x-model="inlineColumnForm.description" placeholder="توضیحات (اختیاری)" rows="3" maxlength="2000"></textarea>
                <x-ui-select model="inlineColumnForm.product_stage_id" options="p.stages.map(ps=>({value:ps.id,label:ps.title}))" placeholder="مرحله محصول مادر" />
                <div class="column-create-actions"><button class="button primary small-button" type="submit" :disabled="!inlineColumnForm.title.trim()||!inlineColumnForm.product_stage_id">افزودن ستون</button><button class="text-button" type="button" @click="cancelInlineColumn()">انصراف</button></div>
            </form>
        </section>
    </div>
</section></template>
<div class="empty-state" x-show="!boardProjects.length"><span x-html="$icon('CheckCircle2')"></span><h2>همه چیز مرتب است</h2><p>تسک بازی ندارید. وقت یک شروع تازه است!</p><button class="button primary" @click="openTask()">افزودن تسک</button></div>
</section>

<template x-if="page==='projects'&&currentProject"><section class="page-enter">

<div class="goal-card">
    <div class="goal-main">
        <div class="goal-kicker"><span class="goal-project-dot" aria-hidden="true"></span><span class="goal-project-name" x-text="currentProject.name"></span></div>
        <h2 class="goal-title"><span x-html="$icon('Target')"></span><span x-text="'هدف: '+(currentProject.goal?.title||'—')"></span></h2>
        <p x-show="currentProject.goal?.description" x-text="currentProject.goal?.description"></p>
        <div class="goal-dates">
            <span><span x-html="$icon('CalendarDays')"></span><span><small>شروع</small><strong x-text="date(currentProject.goal?.start_date)"></strong></span></span>
            <span><span x-html="$icon('CalendarDays')"></span><span><small>پایان</small><strong x-text="date(currentProject.goal?.target_date)"></strong></span></span>
        </div>
    </div>
    <div class="goal-progress">
        <div class="goal-progress-ring" role="progressbar" aria-label="پیشرفت پروژه" :aria-valuenow="progress(currentProject)" aria-valuemin="0" aria-valuemax="100">
            <svg viewBox="0 0 108 108" aria-hidden="true"><circle class="goal-progress-track" cx="54" cy="54" r="46"></circle><circle class="goal-progress-value" cx="54" cy="54" r="46" :stroke-dasharray="`${Math.max(0,Math.min(100,progress(currentProject)))*2.89027} 289.027`"></circle></svg>
            <strong x-text="fa(progress(currentProject))+'٪'"></strong>
        </div>
    </div>
</div>
<div class="section-heading stage-heading"><div><h2>مراحل محصول <span class="count-badge" x-text="fa(currentProject.stages.length)"></span></h2></div></div>
<div class="board product-board">
<template x-for="s in currentProject.stages" :key="s.id"><section class="board-column" @dragover.prevent @drop.prevent="taskDrag?dropTaskOnProductStage(s):reorderStage(s)">
    <div class="stage-column-head" :draggable="canWrite(currentProject)&&!taskDrag" @dragstart="dragStage=s.id;$event.dataTransfer.effectAllowed='move'" @dragend="dragStage=null">
        <div class="stage-title-wrap">
            <form class="inline-column-rename" x-show="columnRename?.id===s.id&&columnRename?.kind==='product'" @submit.prevent="saveColumnRename(s,'product',currentProject)" @keydown.escape.prevent="cancelColumnRename()"><input data-column-rename-input x-model="columnRenameTitle" maxlength="120"></form>
            <template x-if="!(columnRename?.id===s.id&&columnRename?.kind==='product')"><button type="button" class="stage-title-edit" @mousedown.stop @click.stop="openStage(s,null,currentProject)" :aria-label="'ویرایش ستون '+s.title" :title="'ویرایش '+s.title"><h3 x-text="s.title"></h3><span aria-hidden="true" x-html="$icon('Pencil')"></span></button></template>
        </div>
    </div>
    <div class="column-tasks"><template x-for="t in stageTasks(s.id)" :key="t.id">@include('partials.task-card')</template></div>
</section></template>
<section class="board-column add-column-card" :class="{'is-editing':inlineColumn?.kind==='product'&&inlineColumn?.project_id===currentProject.id}" x-show="canWrite(currentProject)">
    <button class="add-column-trigger" x-show="inlineColumn?.kind!=='product'||inlineColumn?.project_id!==currentProject.id" @click="startInlineColumn('product',currentProject)"><span x-html="$icon('Plus')"></span><span>افزودن ستون</span></button>
    <form class="column-create-form" x-show="inlineColumn?.kind==='product'&&inlineColumn?.project_id===currentProject.id" @submit.prevent="saveInlineColumn('product',currentProject)" @keydown.escape.prevent="cancelInlineColumn()">
        <input data-inline-column-input x-model="inlineColumnForm.title" placeholder="نام این ستون" maxlength="120">
        <div class="column-create-actions"><button class="button primary small-button" type="submit" :disabled="!inlineColumnForm.title.trim()">افزودن ستون</button><button class="text-button" type="button" @click="cancelInlineColumn()">انصراف</button></div>
    </form>
</section>
</div>
{{-- Temporarily hidden; set x-show to true to restore the team stages section. --}}
<div class="project-team-stages" x-show="false">
<div class="project-section-divider" aria-hidden="true"></div>
<div class="section-heading team-heading"><div><h2>مراحل اعضای تیم</h2></div></div><div class="team-grid"><template x-for="m in currentProject.members" :key="m.id"><article class="team-card"><div class="team-card-head"><span class="avatar"><img x-show="m.avatar_url" :src="m.avatar_url" :alt="m.name"><span x-show="!m.avatar_url" :style="`background:${color(m.id)}`" x-text="initials(m.name)"></span></span><div><strong x-text="m.name"></strong><small x-text="m.job_title||'عضو تیم'"></small></div></div><div class="team-stages"><template x-for="(s,i) in currentProject.member_stages.filter(s=>s.member_id===m.id)" :key="s.id"><div class="team-stage" :draggable="canWrite(currentProject)" @dragstart.stop="teamDrag=s.id" @dragover.prevent @drop.stop.prevent="reorderStage(s,'member',m)"><span class="step-number" x-text="fa(i+1)"></span><button @click="openStage(s,m,currentProject)" x-text="s.title"></button><button class="icon-btn" x-show="canWrite(currentProject)" @click="moveStage(s,-1,'member',m)" aria-label="جابجایی به قبل" x-html="$icon('GripVertical')"></button></div></template><p class="muted" x-show="!currentProject.member_stages.some(s=>s.member_id===m.id)">مسیر این عضو هنوز تعریف نشده است.</p></div></article></template></div>
</div>
</section></template>
<div class="empty-state page-enter" x-show="page==='projects'&&projectId&&!currentProject&&!loading"><h2>پروژه در دسترس نیست</h2><button class="button" @click="go('tasks')">بازگشت به تسک‌های من</button></div>

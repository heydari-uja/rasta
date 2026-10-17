<div class="modal-backdrop" x-show="modal" x-cloak x-transition:enter="modal-backdrop-enter" x-transition:enter-start="modal-backdrop-enter-start" x-transition:enter-end="modal-backdrop-enter-end" x-transition:leave="modal-backdrop-leave" x-transition:leave-start="modal-backdrop-leave-start" x-transition:leave-end="modal-backdrop-leave-end" @click.self="closeModal()"><section class="modal-panel" :class="{'task-modal-panel': (modal || closingModal)==='task'}" x-ref="modalPanel" @keydown.tab="trap($event)" role="dialog" aria-modal="true" aria-labelledby="modal-title">
<header class="modal-heading"><div><span class="modal-kicker" x-show="modal!=='stage'&&modal!=='project'&&modal!=='projectEdit'" x-text="modal==='task'?'یک قدم در مسیر هدف':'فضای کار راستا'"></span><h2 id="modal-title" x-text="modal==='task'?(taskForm.id?(taskReadOnly?'مشاهده تسک':'ویرایش تسک'):'تسک جدید'):({project:'یک پروژه تازه',projectEdit:'ویرایش پروژه',stage:stageId?'ویرایش مرحله':'مرحله جدید',label:labelForm.id?'ویرایش برچسب':'برچسب جدید',goal:'هدف پروژه',member:'افزودن هم‌تیمی'})[modal]"></h2></div><button class="icon-btn" @click="closeModal()" aria-label="بستن فرم" x-html="$icon('X')"></button></header>
<template x-if="modal==='task' || closingModal==='task'"><form class="task-composer todoist-task-modal" novalidate @submit.prevent="saveTask()">
<div class="task-modal-topbar">
    <div class="task-breadcrumb" aria-label="مسیر تسک">
        <span aria-hidden="true">#</span><strong x-text="formProject?.name||'بدون پروژه'"></strong>
        <template x-if="selectedMemberStage?.title"><span class="task-breadcrumb-separator" aria-hidden="true">/</span></template>
        <span x-show="selectedMemberStage?.title" x-text="selectedMemberStage?.title"></span>
        <template x-if="selectedProductStage?.title"><span class="task-breadcrumb-separator" aria-hidden="true">/</span></template>
        <span x-show="selectedProductStage?.title" x-text="selectedProductStage?.title"></span>
        <template x-if="!selectedMemberStage?.title&&!selectedProductStage?.title"><span class="task-breadcrumb-separator" aria-hidden="true">/</span></template>
        <span x-show="!selectedMemberStage?.title&&!selectedProductStage?.title">بدون مرحله</span>
    </div>
    <div class="task-top-actions"><button type="button" class="icon-btn" @click="closeTaskComposer()" aria-label="بستن" x-html="$icon('X')"></button></div>
</div>
<div class="task-modal-grid">
<main class="task-modal-main">
    <div class="task-editor-row"><button type="button" class="completion task-main-completion" x-show="!taskReadOnly" :class="{'checked':taskForm.completed_at||taskForm.completed}" @click="toggleTaskForm()"><span x-show="taskForm.completed_at||taskForm.completed" x-html="$icon('Check')"></span></button>
        <div class="task-editor-box" :class="{'is-editing':taskDetailsEditing&&!taskReadOnly,'is-readonly':taskReadOnly,'has-error':err('title')}" @click="if(!taskReadOnly&&!taskDetailsEditing)startTaskDetailsEdit()" @focusout="if(!taskReadOnly&&taskForm.id&&!$el.contains($event.relatedTarget)){autosaveTaskNow(true);taskDetailsEditing=false}">
            <template x-if="taskDetailsEditing&&!taskReadOnly"><div><input class="task-title-editor" x-model="taskForm.title" :aria-invalid="err('title')?'true':'false'" aria-describedby="task-title-error" @input="if(err('title'))delete errors.title;if(taskForm.id)scheduleTaskAutosave()" placeholder="نام تسک" required maxlength="200"><small id="task-title-error" class="field-error" x-text="err('title')"></small><div class="task-description-editor"><span class="task-description-icon" x-html="$icon('List')"></span><textarea x-model="taskForm.description" @input="autoGrow($event);if(taskForm.id) scheduleTaskAutosave()" placeholder="توضیحات" rows="1" maxlength="10000"></textarea></div></div></template>
            <template x-if="!taskDetailsEditing||taskReadOnly"><div class="task-editor-preview"><h2 x-text="taskForm.title||'نام تسک'"></h2><p x-show="taskForm.description"><span class="task-description-icon" x-html="$icon('List')"></span><span x-text="taskForm.description"></span></p></div></template>
        </div>
    </div>
    <div class="task-editor-actions" x-show="taskDetailsEditing && !taskForm.id"><button type="button" class="button subtle" @click="closeTaskComposer()">انصراف</button><button type="submit" class="button primary" :disabled="taskSaving||busy" x-text="busy?'در حال ثبت...':'افزودن تسک'"></button></div>
    <section class="task-subtasks-section" x-data="{open:true}">
        <button type="button" class="task-subtasks-head" x-show="taskForm.subtasks.length" @click="open=!open" :aria-expanded="open.toString()">
            <span class="task-subtasks-head-main"><span class="task-subtasks-chevron" :class="{'is-open':open}" x-html="$icon('ChevronLeft')"></span><strong>زیرتسک‌ها</strong><span class="task-subtasks-count" x-text="fa((taskForm.subtasks||[]).filter(s=>s.completed||s.completed_at).length)+'/'+fa((taskForm.subtasks||[]).length)"></span></span>
        </button>
        <div class="task-subtasks" x-show="!taskForm.subtasks.length||open" x-transition.opacity.duration.120ms>
            <template x-for="(s,i) in taskForm.subtasks" :key="s.id||i"><div class="task-subtask-row" :class="{'is-readonly':taskReadOnly,'is-complete':s.completed}" :draggable="!taskReadOnly" @dragstart="if(!taskReadOnly)dragIndex=i" @dragover.prevent @drop.prevent="if(!taskReadOnly){reorderSubtask(i);autosaveTaskNow()}">
                <button type="button" class="subtask-completion" x-show="!taskReadOnly" :class="{'checked':s.completed}" @click="s.completed=!s.completed;autosaveTaskNow()" :aria-label="s.completed?'باز کردن زیرتسک':'تکمیل زیرتسک'"><span x-show="s.completed" x-html="$icon('Check')"></span></button>
                <input x-show="!taskReadOnly" x-model="s.title" @change="autosaveTaskNow()" placeholder="عنوان زیرتسک" maxlength="200">
                <div class="task-subtask-present" x-show="taskReadOnly"><span class="task-subtask-state" :class="{'is-complete':s.completed}"><span x-show="s.completed" x-html="$icon('Check')"></span><span x-show="!s.completed">•</span></span><span x-text="s.title"></span></div>
                <button type="button" class="icon-btn remove-subtask" x-show="!taskReadOnly" @click="taskForm.subtasks.splice(i,1);autosaveTaskNow()" aria-label="حذف زیرتسک" x-html="$icon('X')"></button>
            </div></template>
            <button type="button" class="add-subtask-link" x-show="!taskReadOnly" @click="addSubtask();$nextTick(()=>{const els=$el.parentElement.querySelectorAll('.task-subtask-row input');els[els.length-1]?.focus()})"><span x-html="$icon('Plus')"></span><span>افزودن زیرتسک</span></button>
            <div class="task-content-divider" x-show="(!taskForm.subtasks.length||open)&&(!taskForm.id||!taskHistory.length)"></div>
        </div>
    </section>
    <details class="task-history-collapsed" x-show="taskForm.id&&taskHistory.length"><summary><span class="history-summary-main"><span x-html="$icon('History')"></span><span>تاریخچه</span></span><span class="history-summary-meta"><span x-text="fa(taskHistory.length)"></span><span class="history-chevron" x-html="$icon('ChevronLeft')"></span></span></summary><div class="task-history-list"><template x-for="a in taskHistory" :key="a.id"><div class="activity-row"><span class="activity-dot"></span><div><p x-text="activityText(a)"></p><small x-text="relativeTime(a.created_at)"></small></div></div></template></div></details>
    <div class="task-comment-composer" x-data="{expanded:false}">
        <span class="avatar comment-avatar"><img x-show="user.avatar_url" :src="user.avatar_url" :alt="user.name"><span x-show="!user.avatar_url" :style="`background:${color(user.id)}`" x-text="initials(user.name)"></span></span>
        <div class="comment-composer-shell" :class="{'is-expanded':expanded}" @click="if(!expanded){expanded=true;$nextTick(()=>$refs.commentInput?.focus())}">
            <textarea x-ref="commentInput" x-model="commentText" @focus="expanded=true" @input="autoGrow($event)" placeholder="نظر بنویسید..." :rows="expanded?4:1" maxlength="5000"></textarea>
            <div class="comment-file-queue" x-show="commentFiles.length"><template x-for="(f,i) in commentFiles" :key="i"><span><span x-html="$icon('Paperclip')"></span><span x-text="f.name"></span><button type="button" aria-label="حذف فایل" @click.stop="commentFiles.splice(i,1)" x-html="$icon('X')"></button></span></template></div>
            <div class="comment-composer-footer" x-show="expanded" x-transition.opacity.duration.120ms @click.stop>
                <label class="comment-attach" aria-label="پیوست به نظر"><input type="file" multiple class="sr-only" accept=".pdf,.png,.jpg,.jpeg,.webp,.txt" @change="commentFiles=[...commentFiles,...$event.target.files];$event.target.value=''"/><span x-html="$icon('Paperclip')"></span></label>
                <div class="comment-composer-actions"><button type="button" class="button subtle" @click="commentText='';commentFiles=[];expanded=false">انصراف</button><button type="button" class="button primary comment-submit" :aria-disabled="busy||!commentText.trim()" @click="if(!busy && commentText.trim() && taskForm.id) addComment().then(()=>{if(!commentText.trim()) expanded=false})">ثبت نظر</button></div>
            </div>
        </div>
    </div>
    <div class="task-comments" x-show="taskForm.id"><template x-for="c in taskForm.comments" :key="c.id"><div class="comment" :data-comment-id="c.id"><span class="avatar small"><img x-show="c.user?.avatar_url" :src="c.user?.avatar_url" :alt="c.user?.name"><span x-show="!c.user?.avatar_url" :style="`background:${color(c.member_id)}`" x-text="initials(c.user?.name)"></span></span><div class="comment-content"><div><strong x-text="c.user?.name"></strong><small x-text="commentTime(c.created_at)"></small></div><p x-show="!c.editing" x-text="c.body"></p><div class="comment-files"><template x-for="a in (c.attachments||[])" :key="a.id"><a :href="'/attachments/'+a.id"><span x-html="$icon('Paperclip')"></span><span x-text="a.original_name"></span></a></template></div><div x-show="c.editing" class="comment-composer-shell is-expanded comment-edit-composer"><textarea x-model="c.editBody" @input="autoGrow($event)" rows="4" maxlength="5000"></textarea><div class="comment-file-queue" x-show="(c.editFiles||[]).length"><template x-for="(f,i) in (c.editFiles||[])" :key="i"><span><span x-html="$icon('Paperclip')"></span><span x-text="f.name"></span><button type="button" aria-label="حذف فایل" @click.stop="c.editFiles.splice(i,1)" x-html="$icon('X')"></button></span></template></div><div class="comment-composer-footer"><label class="comment-attach" aria-label="پیوست به نظر"><input type="file" multiple class="sr-only" accept=".pdf,.png,.jpg,.jpeg,.webp,.txt" @change="c.editFiles=[...(c.editFiles||[]),...$event.target.files];$event.target.value=''"/><span x-html="$icon('Paperclip')"></span></label><div class="comment-composer-actions"><button type="button" class="button subtle" @click="c.editing=false;c.editBody=c.body;c.editFiles=[]">انصراف</button><button type="button" class="button primary comment-submit" :aria-disabled="!c.editBody?.trim()" @click="if(c.editBody?.trim()) editComment(c)">ویرایش نظر</button></div></div></div><div class="comment-actions" x-show="c.member_id===user.id&&!c.editing"><button type="button" @click="c.editing=true;c.editBody=c.body;c.editFiles=[]">ویرایش</button><button type="button" class="danger-text" @click="deleteComment(c)">حذف</button></div></div></div></template></div>
</main>
<aside class="task-details-panel" :class="{'is-readonly':taskReadOnly}">
    <div class="task-readonly-details" x-show="taskReadOnly" x-cloak>
        <div class="task-readonly-row"><span>وضعیت</span><strong x-text="taskForm.completed_at||taskForm.completed?'انجام‌شده':'باز'"></strong></div>
        <div class="task-readonly-row"><span>تاریخ</span><strong x-text="taskDateLabel()"></strong></div>
        <div class="task-readonly-row task-readonly-labels"><span>برچسب‌ها</span><div><template x-for="l in labels.filter(l=>(taskForm.labels||[]).includes(l.id))" :key="l.id"><span class="task-readonly-label"><i :style="'background:'+l.color"></i><span x-text="l.name"></span></span></template><small x-show="!(taskForm.labels||[]).length">بدون برچسب</small></div></div>
        <div class="task-readonly-row task-readonly-attachments"><span>پیوست‌ها</span><div><template x-for="a in taskForm.attachments||[]" :key="a.id"><a :href="'/attachments/'+a.id" target="_blank"><span x-html="$icon('Paperclip')"></span><span x-text="a.original_name"></span></a></template><small x-show="!(taskForm.attachments||[]).length">بدون پیوست</small></div></div>
        <div class="task-readonly-row"><span>پروژه</span><strong x-text="projects.find(p=>String(p.id)===String(taskForm.project_id))?.name||'بدون پروژه'"></strong></div>
    </div>
    <div x-show="!taskReadOnly" x-cloak>
    <div class="task-detail-row"><span class="task-detail-label">تاریخ</span><button type="button" class="task-detail-value task-date-value" @click="openTaskDate($event.currentTarget)"><span x-html="$icon('CalendarDays')"></span><span x-text="taskDateLabel()"></span><span x-html="$icon('Plus')"></span></button></div>
    <template x-teleport="body"><div class="task-date-popover calendar-surface" x-show="taskDateOpen" x-cloak x-transition.opacity.scale.95.duration.150ms :style="taskDateStyle" @click.outside="taskDateOpen=false" @keydown.escape.stop="taskDateOpen=false" role="dialog" aria-label="انتخاب تاریخ تسک">
        <div class="task-date-popover-head"><div><span class="task-date-popover-icon" x-html="$icon('CalendarDays')"></span><strong>تاریخ</strong></div><button type="button" class="calendar-nav" @click="taskDateOpen=false" aria-label="بستن تقویم" x-html="$icon('X')"></button></div>
        <div class="task-date-tabs" role="tablist"><button type="button" role="tab" :aria-selected="taskDateMode==='single'" :class="{'selected':taskDateMode==='single'}" @click="taskDateMode='single';taskDatePickingEnd=false">یک روز</button><button type="button" role="tab" :aria-selected="taskDateMode==='range'" :class="{'selected':taskDateMode==='range'}" @click="taskDateMode='range';taskDatePickingEnd=false">بازه زمانی</button></div>
        <div class="calendar-head"><button type="button" class="calendar-nav" @click="moveTaskDate(-1)" aria-label="ماه قبل" x-html="$icon('ChevronRight')"></button><strong x-text="taskDateHeading()"></strong><button type="button" class="calendar-nav" @click="moveTaskDate(1)" aria-label="ماه بعد" x-html="$icon('ChevronLeft')"></button></div>
        <div class="calendar-weekdays" aria-hidden="true"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div>
        <div class="calendar-grid calendar-days task-range-calendar"><template x-for="day in taskDateDays" :key="day.iso"><button type="button" :class="taskDateClass(day)" @click="pickTaskDate(day.iso)" :aria-label="day.fullLabel" x-text="day.label"></button></template></div>
    </div></template>
    <div class="task-detail-row task-label-row" x-data="{open:false}" @click.outside="open=false" @keydown.escape.stop="open=false">
        <span class="task-detail-label">برچسب‌ها</span>
        <button type="button" class="task-detail-value task-label-trigger" @click="open=!open;$nextTick(()=>open&&$refs.labelName?.focus())" :aria-expanded="open"><span x-text="taskForm.labels.length?fa(taskForm.labels.length)+' برچسب':'افزودن برچسب'"></span><span class="task-detail-plus" x-html="$icon('Plus')"></span></button>
        <div class="task-inline-dropdown task-label-dropdown" x-show="open" x-cloak x-transition.opacity.duration.100ms>
            <div class="task-popover-head"><div><strong>برچسب‌ها</strong><small x-text="taskForm.labels.length?fa(taskForm.labels.length)+' انتخاب‌شده':'انتخاب یا ساخت برچسب'"></small></div></div>
            <div class="task-label-create">
                <span class="task-label-create-icon" x-html="$icon('Tags')"></span>
                <input x-ref="labelName" x-model="labelQuery" @keydown.enter.prevent="createOrSelectLabel()" placeholder="نام برچسب جدید" maxlength="60">
                <button type="button" x-show="labelQuery.trim()" @click="createOrSelectLabel()" aria-label="ساخت یا انتخاب برچسب" x-html="$icon('Plus')"></button>
            </div>
            <div class="task-label-list">
                <template x-for="l in formLabels" :key="l.id"><label class="task-label-option" :class="{'is-selected':taskForm.labels.includes(l.id)}"><input class="sr-only" type="checkbox" :value="l.id" x-model.number="taskForm.labels" @change="autosaveTaskNow()"><span class="label-color-dot" :style="'background:'+l.color"></span><span class="task-label-name" x-text="l.name"></span><span class="task-label-check" x-show="taskForm.labels.includes(l.id)" x-html="$icon('Check')"></span></label></template>
                <p class="task-popover-empty" x-show="!formLabels.length&&!labelQuery.trim()">هنوز برچسبی ندارید</p>
                <button type="button" class="task-create-label-row" x-show="labelQuery.trim()&&!formLabels.some(l=>l.name.trim().toLowerCase()===labelQuery.trim().toLowerCase())" @click="quickLabel()"><span class="task-create-label-color"></span><span>ساخت «<b x-text="labelQuery.trim()"></b>»</span><span class="task-create-label-hint">Enter</span></button>
            </div>
        </div>
    </div>
    <div class="task-detail-row task-attachment-row" x-data="{open:false}" @click.outside="open=false" @keydown.escape.stop="open=false">
        <span class="task-detail-label">پیوست‌ها</span>
        <button type="button" class="task-detail-value task-attachment-trigger" @click="open=!open" :aria-expanded="open"><span x-text="fa((taskForm.attachments||[]).length+pendingFiles.length)+' فایل'"></span><span class="task-detail-plus" x-html="$icon('Plus')"></span></button>
        <div class="task-inline-dropdown task-attachment-dropdown" x-show="open" x-cloak x-transition.opacity.duration.100ms>
            <div class="task-popover-head"><div><strong>پیوست‌ها</strong><small x-text="fa((taskForm.attachments||[]).length+pendingFiles.length)+' فایل'"></small></div></div>
            <div class="attachment-popover-list">
                <template x-for="a in taskForm.attachments||[]" :key="a.id"><div class="side-attachment"><span class="side-attachment-icon" x-html="$icon('Paperclip')"></span><a :href="'/attachments/'+a.id" target="_blank"><strong x-text="a.original_name"></strong><small x-text="size(a.size)"></small></a><button type="button" class="icon-btn side-attachment-remove" x-show="a.member_id===user.id" @click="deleteAttachment(a)" aria-label="حذف فایل" x-html="$icon('X')"></button></div></template>
                <template x-for="(f,i) in pendingFiles" :key="'pending-'+i"><div class="side-attachment is-pending"><span class="side-attachment-icon" x-html="$icon('Upload')"></span><span><strong x-text="f.name"></strong><small><span x-text="size(f.size)"></span> · آماده بارگذاری</small></span><button type="button" class="icon-btn side-attachment-remove" @click="pendingFiles.splice(i,1)" aria-label="حذف فایل" x-html="$icon('X')"></button></div></template>
                <div class="attachment-empty" x-show="!(taskForm.attachments||[]).length&&!pendingFiles.length"><span x-html="$icon('Paperclip')"></span><strong>فایلی پیوست نشده</strong><small>PDF، تصویر یا فایل متنی تا ۵ مگابایت</small></div>
            </div>
            <label class="attachment-add"><input type="file" class="sr-only" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.txt" @change="filesAdded($event.target.files);$event.target.value=''"/><span x-html="$icon('Upload')"></span><span>افزودن فایل</span><small>حداکثر ۵ مگابایت</small></label>
        </div>
    </div>
    <div class="task-detail-row task-project-row" style="border-bottom:0" x-show="formProject" x-data="{open:false}" @click.outside="open=false" @keydown.escape.stop="open=false">
        <span class="task-detail-label">مرحله پروژه</span>
        <button type="button" class="task-detail-value task-project-trigger" @click="open=!open" :aria-expanded="open" :aria-invalid="err('product_stage_id')?'true':'false'" aria-describedby="task-stage-error"><span x-text="selectedProductStage?.title||('انتخاب مرحله از پروژه '+(formProject?.name||''))"></span><span class="task-detail-chevron" x-html="$icon('ChevronDown')"></span></button>
        <div class="ui-select-menu task-project-menu task-project-dropdown" x-show="open" x-cloak x-transition.opacity.duration.100ms>
            <template x-for="s in (formProject?.stages||[])" :key="s.id"><button type="button" class="ui-select-option" :class="{'selected':String(s.id)===String(taskForm.product_stage_id)}" @click="taskForm.product_stage_id=s.id;delete errors.product_stage_id;productStageChanged();open=false"><span x-text="s.title"></span><span class="ui-select-check" x-show="String(s.id)===String(taskForm.product_stage_id)" x-html="$icon('Check')"></span></button></template>
        </div>
        <small id="task-stage-error" class="field-error" x-text="err('product_stage_id')"></small>
    </div>
    </div>
</aside>
</div>
</form></template>
<template x-if="modal==='project'">
<form class="project-wizard-form" @submit.prevent="projectStep<2?nextStep():saveProject()">
    <nav class="wizard-steps" aria-label="مراحل ساخت پروژه">
        <template x-for="(title,i) in ['اطلاعات پروژه','هدف پروژه']" :key="i">
            <button type="button" class="wizard-step" :class="{'current':projectStep===i+1,'done':projectStep>i+1}" :aria-current="projectStep===i+1?'step':'false'" @click="goProjectStep(i+1)">
                <span x-text="projectStep>i+1?'✓':fa(i+1)"></span>
                <strong x-text="title"></strong>
            </button>
        </template>
    </nav>
    <div class="modal-body">
        <div class="project-info-step" x-show="projectStep===1">
            <label>نام پروژه<input x-model="projectForm.name" placeholder="نام پروژه شما" maxlength="150"><small class="field-error" x-text="err('name')"></small></label>
            <label>توضیح کوتاه<textarea x-model="projectForm.description" placeholder="این پروژه درباره چیست؟" rows="3"></textarea></label>
            <fieldset class="project-color-palette" x-data="{open:false}" @click.outside="open=false" @keydown.escape.window="open=false">
                <legend>رنگ پروژه</legend>
                <button type="button" class="project-color-trigger" aria-controls="project-color-options" :aria-expanded="open.toString()" @click="open=!open">
                    <span class="project-color-current-swatch" :style="`--choice:${projectForm.color}`"></span>
                    <span x-text="projectForm.color===colorToken('data-project-default')?'پیش‌فرض':'رنگ انتخاب‌شده'"></span>
                    <span class="project-color-trigger-chevron" :class="{'is-open':open}" x-html="$icon('ChevronDown')"></span>
                </button>
                <div id="project-color-options" class="project-color-options" role="group" aria-label="رنگ پروژه" x-show="open" x-cloak>
                    <template x-for="(c,i) in projectColorPalette()" :key="c">
                        <button type="button" class="project-color-choice" :style="`--choice:${c}`" :class="{'chosen':projectForm.color===c}" :aria-label="i===0?'رنگ پیش‌فرض':'انتخاب رنگ '+fa(i+1)" :aria-pressed="(projectForm.color===c).toString()" @click="projectForm.color=c;open=false"><span></span><i x-show="projectForm.color===c" x-html="$icon('Check')"></i></button>
                    </template>
                </div>
            </fieldset>
        </div>
        <div x-show="projectStep===2">
            <label>عنوان هدف<input x-model="projectForm.goal.title" placeholder="می‌خواهیم به چه نتیجه‌ای برسیم؟"><small class="field-error" x-text="err('goal.title')"></small></label>
            <label>توضیح هدف<textarea x-model="projectForm.goal.description" rows="2" placeholder="هدف را کمی بیشتر توضیح دهید..."></textarea></label>
            <label>معیار موفقیت<textarea x-model="projectForm.goal.success_criterion" rows="2" placeholder="از کجا بفهمیم به هدف رسیده‌ایم؟"></textarea><small class="field-error" x-text="err('goal.success_criterion')"></small></label>
            <div class="form-grid"><label>تاریخ شروع<x-date-picker model="projectForm.goal.start_date" /><small class="field-error" x-text="err('goal.start_date')"></small></label><label>تاریخ هدف<x-date-picker model="projectForm.goal.target_date" /><small class="field-error" x-text="err('goal.target_date')"></small></label></div>
        </div>
    </div>
    <footer class="modal-footer"><span class="muted" x-text="'مرحله '+fa(projectStep)+' از ۲'"></span><div class="button-row"><button type="button" class="button" @click="projectStep>1?goProjectStep(projectStep-1):closeModal()" x-text="projectStep>1?'قبلی':'انصراف'"></button><button class="button primary" type="submit" :disabled="busy" x-text="busy?'در حال ساخت...':projectStep===2?'ساخت پروژه':'ادامه'"></button></div></footer>
</form>
</template>
<template x-if="modal==='projectEdit'">
<form class="project-edit-form" @submit.prevent="saveProjectEdit()">
    <div class="modal-body">
        <fieldset class="project-color-palette" x-data="{open:false}" @click.outside="open=false" @keydown.escape.window="open=false">
            <legend>رنگ پروژه</legend>
            <button type="button" class="project-color-trigger" aria-controls="project-edit-color-options" :aria-expanded="open.toString()" @click="open=!open">
                <span class="project-color-current-swatch" :style="`--choice:${projectEditForm.color}`"></span>
                <span x-text="projectEditForm.color===colorToken('data-project-default')?'پیش‌فرض':'رنگ انتخاب‌شده'"></span>
                <span class="project-color-trigger-chevron" :class="{'is-open':open}" x-html="$icon('ChevronDown')"></span>
            </button>
            <div id="project-edit-color-options" class="project-color-options" role="group" aria-label="رنگ پروژه" x-show="open" x-cloak>
                <template x-for="(c,i) in projectColorPalette()" :key="c">
                    <button type="button" class="project-color-choice" :style="`--choice:${c}`" :class="{'chosen':projectEditForm.color===c}" :aria-label="i===0?'رنگ پیش‌فرض':'انتخاب رنگ '+fa(i+1)" :aria-pressed="(projectEditForm.color===c).toString()" @click="projectEditForm.color=c;open=false"><span></span><i x-show="projectEditForm.color===c" x-html="$icon('Check')"></i></button>
                </template>
            </div>
        </fieldset>
        <label>نام پروژه<input x-model="projectEditForm.name" required maxlength="150" placeholder="نام پروژه"><small class="field-error" x-text="err('name')"></small></label>
        <label>توضیح کوتاه<textarea x-model="projectEditForm.description" rows="3" maxlength="2000" placeholder="توضیح پروژه"></textarea><small class="field-error" x-text="err('description')"></small></label>
    </div>
    <footer class="modal-footer project-edit-footer"><button type="button" class="text-button danger-text" @click="deleteProject()">حذف پروژه</button><div class="button-row"><button type="button" class="button" @click="closeModal()">انصراف</button><button type="submit" class="button primary" :disabled="busy||!projectEditForm.name?.trim()" x-text="busy?'در حال ذخیره...':'ذخیره تغییرات'"></button></div></footer>
</form>
</template>
<template x-if="modal==='stage'"><form @submit.prevent="saveStage()"><div class="modal-body"><label>نام مرحله<input x-model="stageForm.title" required maxlength="120"><small class="field-error" x-text="err('title')"></small></label><label>توضیحات<textarea x-model="stageForm.description" rows="3"></textarea></label><div class="form-grid"><label>شروع<x-date-picker model="stageForm.start_date" /><small class="field-error" x-text="err('start_date')"></small></label><label>پایان<x-date-picker model="stageForm.end_date" /><small class="field-error" x-text="err('end_date')"></small></label></div></div><footer class="modal-footer"><button type="button" class="text-button danger-text" x-show="stageId&&canWrite(stageProject)" @click="deleteStage({...stageForm,id:stageId,project_id:stageProjectId},stageKind,stageUser)">حذف مرحله</button><div class="button-row"><button type="button" class="button" @click="closeModal()">انصراف</button><button type="submit" class="button primary" :disabled="busy||(stageKind!=='personal'&&!canWrite(stageProject))">ذخیره مرحله</button></div></footer></form></template>
<template x-if="modal==='goal'"><form @submit.prevent="saveGoal()"><div class="modal-body"><label>عنوان هدف<input x-model="goalForm.title" required><small class="field-error" x-text="err('title')"></small></label><label>توضیح هدف<textarea x-model="goalForm.description" rows="3"></textarea></label><label>معیار موفقیت<textarea x-model="goalForm.success_criterion" required rows="3"></textarea><small class="field-error" x-text="err('success_criterion')"></small></label><div class="form-grid"><label>شروع<x-date-picker model="goalForm.start_date" /></label><label>پایان<x-date-picker model="goalForm.target_date" /><small class="field-error" x-text="err('end_date')"></small></label></div></div><footer class="modal-footer"><span></span><div class="button-row"><button type="button" class="button" @click="closeModal()">انصراف</button><button type="submit" class="button primary" :disabled="busy">ذخیره هدف</button></div></footer></form></template>
<template x-if="modal==='label'"><form class="label-modal-form" @submit.prevent="saveLabel()"><div class="modal-body"><label>نام برچسب<input x-model="labelForm.name" required maxlength="60" placeholder="نام برچسب"><small class="field-error" x-text="err('name')"></small></label><fieldset class="label-palette"><legend>رنگ <small x-show="labelForm.color===colorToken('data-label-palette-1')">پیش‌فرض</small></legend><template x-for="c in labelColorPalette()" :key="c"><button type="button" class="label-color-choice" :style="`--choice:${c}`" :class="{'chosen':labelForm.color===c}" @click="labelForm.color=c" :aria-label="'انتخاب رنگ '+c" :aria-pressed="(labelForm.color===c).toString()"><span></span><i x-show="labelForm.color===c" x-html="$icon('Check')"></i></button></template></fieldset></div><footer class="modal-footer"><button type="button" class="button subtle" @click="closeModal()">انصراف</button><button type="submit" class="button primary" :disabled="busy||!labelForm.name?.trim()">ذخیره برچسب</button></footer></form></template>
</section></div>

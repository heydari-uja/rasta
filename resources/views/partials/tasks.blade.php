<section x-show="page==='tasks'" class="page-enter tasks-page">
<div class="page-heading"><div><h1>تسک‌های من</h1><p>کارهایی که خودتان ساخته‌اید و در مسیر شخصی شما قرار دارند.</p></div></div>
<section class="task-project-section">
    <div class="board my-stages-board" data-my-stages-board :class="{'list-view':view==='list'}">
        <template x-for="s in myStageColumns" :key="s.id"><section class="board-column task-stage-column" :data-stage-id="s.id">
            <div class="stage-column-head task-stage-drag-handle">
                <div class="stage-title-wrap">
                    <form class="inline-column-rename" x-show="columnRename?.id===s.id&&columnRename?.kind==='member'" @submit.prevent="saveColumnRename(s,'member',projectForStage(s))" @keydown.escape.prevent="cancelColumnRename()"><input data-column-rename-input x-model="columnRenameTitle" maxlength="120"></form>
                    <template x-if="!(columnRename?.id===s.id&&columnRename?.kind==='member')"><div class="task-stage-title"><button type="button" class="stage-title-edit" @mousedown.stop @click.stop="openStage(s,user,projectForStage(s))" :aria-label="'ویرایش ستون '+s.title" :title="'ویرایش '+s.title"><h3 x-text="s.title"></h3><span aria-hidden="true" x-html="$icon('Pencil')"></span></button></div></template>
                </div>
            </div>
            <div class="column-tasks" :data-stage-id="s.id"><template x-for="t in stageTasks(s.id,true)" :key="t.id">@include('partials.task-card')</template></div>
            <button class="add-column-task" x-show="!s.project_id||canWrite(projectForStage(s))" @click="openTask(null,s,projectForStage(s))"><span class="add-task-plus"><span x-html="$icon('Plus')"></span></span><span>افزودن تسک</span></button>
        </section></template>
        <section class="board-column add-column-card task-global-add-column" :class="{'is-editing':inlineColumn?.kind==='member'&&inlineColumn?.source==='tasks'}">
            <button class="add-column-trigger" x-show="inlineColumn?.kind!=='member'||inlineColumn?.source!=='tasks'" @click="startTaskColumn()"><span x-html="$icon('Plus')"></span><span>افزودن ستون</span></button>
            <form class="column-create-form" x-show="inlineColumn?.kind==='member'&&inlineColumn?.source==='tasks'" @submit.prevent="saveTaskColumn()" @keydown.escape.prevent="cancelInlineColumn()">
                <x-ui-select model="inlineColumnForm.project_id" options="[{value:'none',label:'بدون پروژه'},...projects.map(p=>({value:p.id,label:p.name}))]" placeholder="انتخاب پروژه یا حالت شخصی" />
                <input data-inline-column-input x-model="inlineColumnForm.title" placeholder="نام این ستون" maxlength="120">
                <div class="column-create-actions"><button class="button primary small-button" type="submit" :disabled="!inlineColumnForm.title.trim()||inlineColumnForm.project_id===''">افزودن ستون</button><button class="text-button" type="button" @click="cancelInlineColumn()">انصراف</button></div>
            </form>
        </section>
    </div>
</section>
<div class="empty-state" x-show="!myStageColumns.length&&!inlineColumn"><span x-html="$icon('CheckCircle2')"></span><h2>هنوز مرحله‌ای ندارید</h2><p>می‌توانید یک مرحله شخصی بدون پروژه یا مرحله‌ای وابسته به یک پروژه بسازید.</p></div>
</section>

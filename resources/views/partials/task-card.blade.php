<article class="task-card" :draggable="page==='projects'&&canWrite(projects.find(p=>p.id===t.project_id))" @dragstart.stop="if(page==='projects')beginTaskDrag(t)" @dragend="taskDrag=null" :class="{'is-complete':t.completed_at}">
    <div class="task-card-main">
        <button class="completion" :class="{'checked':t.completed_at}" :disabled="!canWrite(projects.find(p=>p.id===t.project_id))" @click.stop="toggleTask(t)" :aria-label="(t.completed_at?'باز کردن ':'تکمیل ')+t.title"><span x-show="t.completed_at" x-html="$icon('Check')"></span></button>
        <div class="task-card-copy">
            <button @click="openTask(t)" class="task-title" x-text="t.title"></button>
            <div class="task-meta-line">
                <span class="task-date" :class="{'overdue':overdue(t)}" x-show="t.due_date"><span x-text="date(t.due_date)"></span></span>
                <button type="button" class="subtask-toggle" x-show="t.subtasks.length" @click.stop="toggleSubtasks(t)"><span x-text="subProgress(t)"></span><span :class="{'is-open':subtasksExpanded(t)}">⌄</span></button>
                <span class="task-label-text" x-show="t.labels.length"><span x-html="$icon('Tags')"></span><span x-text="t.labels.map(l=>l.name).join('، ')"></span></span>
                <span class="avatar card-avatar" x-show="t.owner_id" :title="member(t.owner_id).name"><img x-show="member(t.owner_id).avatar_url" :src="member(t.owner_id).avatar_url" :alt="member(t.owner_id).name"><span x-show="!member(t.owner_id).avatar_url" :style="`background:${color(t.owner_id)}`" x-text="initials(member(t.owner_id).name)"></span></span>
            </div>
        </div>
    </div>
    <div class="card-subtasks" x-show="t.subtasks.length&&subtasksExpanded(t)" x-transition.opacity.duration.150ms>
        <template x-for="s in t.subtasks" :key="s.id">
            <div class="card-subtask" :class="{'is-complete':s.completed_at}">
                <button type="button" class="subtask-completion" :class="{'checked':s.completed_at}" @click.stop="toggleBoardSubtask(t,s)"><span x-show="s.completed_at" x-html="$icon('Check')"></span></button>
                <span x-text="s.title"></span>
            </div>
        </template>
    </div>
</article>

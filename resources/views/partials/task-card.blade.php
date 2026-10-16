<article class="task-card" :data-task-id="t.id" :draggable="page==='projects'&&canEditTask(t)" @dragstart.stop="if(page==='projects'&&canEditTask(t))beginTaskDrag(t)" @dragend="taskDrag=null" :class="{'is-complete':t.completed_at}">
    <div class="task-card-main">
        <button class="completion" x-show="canEditTask(t)" :class="{'checked':t.completed_at}" @click.stop="toggleTask(t)" :aria-label="(t.completed_at?'باز کردن ':'تکمیل ')+t.title"><span x-show="t.completed_at" x-html="$icon('Check')"></span></button>
        <div class="task-card-copy">
            <button @click="if(Date.now()>=taskDragBlockedUntil)openTask(t)" class="task-title" x-text="t.title"></button>
            <div class="task-meta-line">
                <span class="task-date task-date-compact" :class="{'overdue':overdue(t)}" x-show="page==='tasks'&&(t.start_date||t.due_date)"><span x-html="$icon('CalendarDays')"></span><span x-text="shortDate(t.start_date&&t.due_date&&t.start_date!==t.due_date?t.start_date:(t.due_date||t.start_date))"></span></span>
                <span class="task-date" :class="{'overdue':overdue(t)}" x-show="page!=='tasks'&&t.due_date"><span x-text="date(t.due_date)"></span></span>
                <span class="task-label-text task-label-compact" x-show="page==='tasks'&&t.labels.length"><span x-html="$icon('Tags')"></span><span class="task-label-name" x-text="t.labels[0]?.name"></span><span class="task-label-more" x-show="t.labels.length>1" x-text="'+'+fa(t.labels.length-1)"></span></span>
                <span class="task-label-text" x-show="page!=='tasks'&&t.labels.length"><span x-html="$icon('Tags')"></span><span x-text="t.labels.map(l=>l.name).join('، ')"></span></span>
                <button type="button" class="subtask-toggle" x-show="page!=='tasks'&&t.subtasks.length" @click.stop="toggleSubtasks(t)"><span x-text="subProgress(t)"></span><span :class="{'is-open':subtasksExpanded(t)}">⌄</span></button>
            </div>
        </div>
    </div>
    <div class="card-subtasks" x-show="page!=='tasks'&&t.subtasks.length&&subtasksExpanded(t)" x-transition.opacity.duration.150ms>
        <template x-for="s in t.subtasks" :key="s.id">
            <div class="card-subtask" :class="{'is-complete':s.completed_at}">
                <button type="button" class="subtask-completion" x-show="canEditTask(t)" :class="{'checked':s.completed_at}" @click.stop="toggleBoardSubtask(t,s)"><span x-show="s.completed_at" x-html="$icon('Check')"></span></button>
                <span class="card-subtask-state" x-show="!canEditTask(t)" :class="{'is-complete':s.completed_at}"><span x-show="s.completed_at" x-html="$icon('Check')"></span><span x-show="!s.completed_at">•</span></span>
                <span x-text="s.title"></span>
            </div>
        </template>
    </div>
</article>

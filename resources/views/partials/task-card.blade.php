<article class="task-card" :data-task-id="t.id" :draggable="page==='projects'&&canEditTask(t)" @dragstart.stop="if(page==='projects'&&canEditTask(t))beginTaskDrag(t)" @dragend="taskDrag=null" :class="{'is-complete':t.completed_at}">
    <div class="task-card-main">
        <button class="completion" x-show="page==='tasks'&&canEditTask(t)" :class="{'checked':t.completed_at}" @click.stop="toggleTask(t)" :aria-label="(t.completed_at?'باز کردن ':'تکمیل ')+t.title"><span x-show="t.completed_at" x-html="$icon('Check')"></span></button>
        <div class="task-card-copy">
            <button @click="if(Date.now()>=taskDragBlockedUntil)openTask(t)" class="task-title" x-text="t.title"></button>
            <div class="task-meta-line" x-show="page==='tasks'&&(t.start_date||t.due_date||t.labels.length)">
                <span class="task-date task-date-compact" :class="{'overdue':overdue(t)}" x-show="page==='tasks'&&(t.start_date||t.due_date)"><span x-html="$icon('CalendarDays')"></span><span x-text="shortDate(t.start_date&&t.due_date&&t.start_date!==t.due_date?t.start_date:(t.due_date||t.start_date))"></span></span>
                <span class="task-label-text task-label-compact" x-show="page==='tasks'&&t.labels.length"><span x-html="$icon('Tags')"></span><span class="task-label-name" x-text="t.labels[0]?.name"></span><span class="task-label-more" x-show="t.labels.length>1" x-text="'+'+fa(t.labels.length-1)"></span></span>
            </div>
        </div>
    </div>
    <div class="task-card-footer" x-show="page==='projects'">
        <span class="task-creator" x-show="t.creator" :aria-label="'سازنده تسک: '+(t.creator?.name||'')"><span class="avatar task-creator-avatar"><img x-show="t.creator?.avatar_url" :src="t.creator?.avatar_url" :alt="t.creator?.name"><span x-show="!t.creator?.avatar_url" :style="`background:${color(t.creator?.id)}`" x-text="initials(t.creator?.name)"></span></span><span class="task-creator-name" x-text="t.creator?.name"></span></span>
        <span class="task-project-date" :class="{'overdue':overdue(t)}" x-show="t.due_date||t.start_date"><span aria-hidden="true" x-html="$icon('CalendarDays')"></span><span x-text="t.due_date?'سررسید: '+shortDate(t.due_date):'شروع: '+shortDate(t.start_date)"></span></span>
    </div>
</article>


@props(['model','label'=>'انتخاب تاریخ'])
<div class="date-picker" x-data="datePicker" x-modelable="value" x-model="{{ $model }}" @keydown.escape.stop="open=false">
    <button type="button" class="date-trigger" x-ref="trigger" @click="open=!open;render();if(open)$nextTick(()=>position())" :aria-expanded="open" aria-haspopup="dialog" aria-label="{{ $label }}">
        <span x-text="label()"></span><span x-html="$icon('CalendarDays')"></span>
    </button>
    <template x-teleport="body">
        <div class="calendar-pop calendar-fixed calendar-surface" x-ref="calendar" x-show="open" x-cloak x-transition.opacity.scale.95.duration.150ms :style="calendarStyle" @click.outside="open=false" @keydown.escape.stop="open=false;$refs.trigger.focus()" @resize.window="if(open)position()" @scroll.window.capture="if(open)position()" role="dialog" aria-label="{{ $label }}">
            <div class="calendar-head">
                <button type="button" class="calendar-nav" @click="move(-1)" aria-label="ماه قبل" x-html="$icon('ChevronRight')"></button>
                <strong x-text="heading"></strong>
                <button type="button" class="calendar-nav" @click="move(1)" aria-label="ماه بعد" x-html="$icon('ChevronLeft')"></button>
            </div>
            <div class="calendar-weekdays" aria-hidden="true"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div>
            <div class="calendar-grid calendar-days">
                <template x-for="day in days" :key="day.iso"><button type="button" @click="value=day.iso;open=false" :class="{'muted':day.muted,'selected':day.iso===value,'is-today':day.today}" :aria-label="day.fullLabel" x-text="day.label"></button></template>
            </div>
            <div class="calendar-footer">
                <button type="button" class="calendar-today" @click="selectToday()"><span x-html="$icon('CalendarDays')"></span><span>امروز</span></button>
                <button type="button" class="calendar-clear" x-show="value" @click="value='';open=false">پاک کردن</button>
            </div>
        </div>
    </template>
</div>

@props(['model', 'options', 'placeholder' => 'انتخاب کنید', 'change' => ''])
<div class="ui-select" x-data="{open:false,menuStyle:'',position(){const r=this.$refs.trigger.getBoundingClientRect();const w=r.width;const left=Math.max(8,Math.min(innerWidth-w-8,r.left));const below=r.bottom+6;const top=below+260<innerHeight?below:Math.max(12,r.top-260);this.menuStyle=`position:fixed;top:${top}px;left:${left}px;width:${w}px`}}" @keydown.escape.stop="open=false">
    <button type="button" class="ui-select-trigger" x-ref="trigger" @click="open=!open;if(open)$nextTick(()=>position())" @keydown.arrow-down.prevent="open=true;$nextTick(()=>{position();setTimeout(()=>$refs.menu?.querySelector('.ui-select-option')?.focus(),0)})" :aria-expanded="open">
        <span x-text="({{ $options }}).find(o=>String(o.value)===String({{ $model }}))?.label || @js($placeholder)"></span>
        <span class="ui-select-chevron" x-html="$icon('ChevronDown')"></span>
    </button>
    <template x-teleport="body">
        <div class="ui-select-menu" x-ref="menu" x-show="open" x-cloak x-transition.opacity.scale.95.duration.150ms :style="menuStyle" @click.outside="open=false">
            <template x-for="o in {{ $options }}" :key="String(o.value)">
                <button type="button" class="ui-select-option" :class="{'selected':String(o.value)===String({{ $model }})}" @keydown.escape.stop="open=false;$refs.trigger.focus()" @click="{{ $model }}=o.value;{{ $change }};open=false">
                    <span x-text="o.label"></span><span class="ui-select-check" x-show="String(o.value)===String({{ $model }})" x-html="$icon('Check')"></span>
                </button>
            </template>
        </div>
    </template>
</div>

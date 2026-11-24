<script setup>
// Редактор схемы: перетаскивание (Pointer Events — мышь и палец), привязка к сетке 20 px
// (Shift — по 1 px), ограничение полотном, угловая ручка размера. Состояние хранит страница:
// редактор только сообщает изменения через patch(key, поля).
import { computed, ref } from 'vue';
import { useI18n } from '@/i18n';
import {
    ELEMENT_CLASS,
    GRID,
    MIN_ELEMENT,
    MIN_TABLE,
    box,
    clamp,
    snap,
    useFitScale,
} from './hall';

const props = defineProps({
    hall: { type: Object, required: true },
    tables: { type: Array, default: () => [] },
    elements: { type: Array, default: () => [] },
    selected: { type: String, default: null },
    conflicts: { type: Object, default: () => new Set() }, // ключи пересекающихся столов
});
const emit = defineEmits(['select', 'patch']);
const { t, loc } = useI18n();

const wrap = ref(null);
const { scale } = useFitScale(wrap, () => props.hall.width);

let drag = null;

function start(e, item, mode, isTable) {
    if (e.button !== undefined && e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    emit('select', item.key);
    drag = {
        key: item.key,
        mode,
        isTable,
        sx: e.clientX,
        sy: e.clientY,
        orig: { ...item },
        box: box(item),
    };
    e.currentTarget.setPointerCapture?.(e.pointerId);
}

function move(e) {
    if (!drag) return;
    const step = e.shiftKey ? 1 : GRID;
    const dx = (e.clientX - drag.sx) / scale.value;
    const dy = (e.clientY - drag.sy) / scale.value;
    const { orig, box: b } = drag;

    if (drag.mode === 'move') {
        emit('patch', drag.key, {
            pos_x: clamp(
                snap(orig.pos_x + dx, step),
                0,
                props.hall.width - b.w,
            ),
            pos_y: clamp(
                snap(orig.pos_y + dy, step),
                0,
                props.hall.height - b.h,
            ),
        });
        return;
    }

    const min = drag.isTable ? MIN_TABLE : MIN_ELEMENT;
    const w = clamp(snap(b.w + dx, step), min, props.hall.width - orig.pos_x);
    const h = clamp(snap(b.h + dy, step), min, props.hall.height - orig.pos_y);
    // Размер хранится без поворота: у повёрнутого стола ширина на экране — это его высота.
    const rotated = Number(orig.rotation) === 90;
    emit(
        'patch',
        drag.key,
        rotated ? { width: h, height: w } : { width: w, height: h },
    );
}

const end = () => (drag = null);

const outer = computed(() => ({
    width: `${props.hall.width * scale.value}px`,
    height: `${props.hall.height * scale.value}px`,
}));
const style = (o) => {
    const b = box(o);
    return {
        left: `${b.x}px`,
        top: `${b.y}px`,
        width: `${b.w}px`,
        height: `${b.h}px`,
    };
};
const elementText = (e) =>
    loc(e, 'label') ||
    (['bar', 'entrance', 'window', 'wall'].includes(e.type)
        ? t(`hall.el_${e.type}`)
        : '');
</script>

<template>
    <div ref="wrap" class="w-full overflow-auto">
        <div :style="outer" class="relative touch-none select-none">
            <div
                class="editor-grid absolute top-0 left-0 origin-top-left rounded-lg border border-line"
                :style="{
                    width: `${hall.width}px`,
                    height: `${hall.height}px`,
                    transform: `scale(${scale})`,
                }"
                @pointermove="move"
                @pointerup="end"
                @pointercancel="end"
                @pointerdown.self="emit('select', null)"
            >
                <div
                    v-for="e in elements"
                    :key="e.key"
                    class="absolute flex cursor-move items-center justify-center overflow-hidden font-mono text-[11px] tracking-[0.06em] uppercase"
                    :class="[
                        ELEMENT_CLASS[e.type],
                        selected === e.key
                            ? 'outline-2 outline-offset-2 outline-ink'
                            : '',
                    ]"
                    :style="style(e)"
                    role="button"
                    :aria-label="elementText(e) || t(`hall.el_${e.type}`)"
                    @pointerdown="start($event, e, 'move', false)"
                >
                    {{ elementText(e) }}
                    <span
                        v-if="selected === e.key"
                        class="absolute -right-1.5 -bottom-1.5 size-4 cursor-nwse-resize rounded-sm border-2 border-surface bg-ink"
                        @pointerdown="start($event, e, 'resize', false)"
                    />
                </div>

                <div
                    v-for="tb in tables"
                    :key="tb.key"
                    class="absolute flex cursor-move flex-col items-center justify-center border-2 bg-surface leading-tight"
                    :class="[
                        tb.shape === 'round' ? 'rounded-full' : 'rounded-md',
                        conflicts.has(tb.key)
                            ? 'border-danger bg-danger-soft'
                            : tb.busy
                              ? 'border-brand'
                              : 'border-ink-3',
                        selected === tb.key
                            ? 'outline-2 outline-offset-4 outline-ink'
                            : '',
                    ]"
                    :style="style(tb)"
                    role="button"
                    :aria-label="`${t('tables.table')} ${tb.number}`"
                    @pointerdown="start($event, tb, 'move', true)"
                >
                    <span class="font-mono text-lg leading-5 font-semibold">{{
                        tb.number || '?'
                    }}</span>
                    <span class="font-mono text-[11px] text-ink-3"
                        >{{ tb.seats }} {{ t('tables.seats_short') }}</span
                    >
                    <span
                        v-if="selected === tb.key"
                        class="absolute -right-1.5 -bottom-1.5 size-4 cursor-nwse-resize rounded-sm border-2 border-surface bg-ink"
                        @pointerdown="start($event, tb, 'resize', true)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.editor-grid {
    background-color: var(--color-surface);
    background-image: radial-gradient(
        circle,
        var(--color-ink-3) 1px,
        transparent 1.2px
    );
    background-size: 20px 20px;
}
</style>

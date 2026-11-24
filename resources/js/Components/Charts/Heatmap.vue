<script setup>
// Тепловая карта загрузки зала: дни недели × часы. Один оттенок, светлый → тёмный.
import { computed, ref } from 'vue';

const props = defineProps({
    matrix: { type: Array, required: true }, // 7 × 24
    max: { type: Number, default: 0 },
    days: { type: Array, required: true }, // короткие названия дней
    format: { type: Function, required: true },
    title: { type: String, required: true },
});

// Последовательная шкала зелёного: пустая ячейка — цвет поверхности 2, полная — тёмный зелёный.
const LOW = [235, 227, 213]; // --color-surface-2
const HIGH = [26, 92, 63];
const color = (v) => {
    if (!v || !props.max) return 'var(--color-surface-2)';
    const k = 0.15 + 0.85 * Math.min(1, v / props.max);
    const c = LOW.map((lo, i) => Math.round(lo + (HIGH[i] - lo) * k));
    return `rgb(${c.join(' ')})`;
};

const hover = ref(null); // [day, hour]
const legend = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((k) => k * props.max),
);
</script>

<template>
    <figure class="flex flex-col gap-3">
        <div class="overflow-x-auto">
            <div
                class="grid min-w-[640px] grid-cols-[40px_repeat(24,minmax(0,1fr))] gap-[2px]"
                role="img"
                :aria-label="title"
                @mouseleave="hover = null"
            >
                <span />
                <span
                    v-for="hr in 24"
                    :key="`h${hr}`"
                    class="text-center font-mono text-[10px] text-ink-3"
                    >{{
                        (hr - 1) % 3 === 0
                            ? String(hr - 1).padStart(2, '0')
                            : ''
                    }}</span
                >
                <template v-for="(row, d) in matrix" :key="`d${d}`">
                    <span
                        class="flex items-center font-mono text-[11px] text-ink-2"
                        >{{ days[d] }}</span
                    >
                    <span
                        v-for="(v, hr) in row"
                        :key="`c${d}-${hr}`"
                        class="relative h-7 rounded-[3px]"
                        :class="
                            hover && hover[0] === d && hover[1] === hr
                                ? 'outline-2 outline-offset-1 outline-ink'
                                : ''
                        "
                        :style="{ background: color(v) }"
                        @mouseenter="hover = [d, hr]"
                        @touchstart.passive="hover = [d, hr]"
                    />
                </template>
            </div>
        </div>
        <div
            class="flex flex-wrap items-center justify-between gap-3 text-xs text-ink-2"
        >
            <span v-if="hover" class="font-mono"
                >{{ days[hover[0]] }} ·
                {{ String(hover[1]).padStart(2, '0') }}:00–{{
                    String(hover[1] + 1).padStart(2, '0')
                }}:00 ·
                <b class="text-ink">{{
                    format(matrix[hover[0]][hover[1]])
                }}</b></span
            >
            <span v-else />
            <span class="flex items-center gap-1.5">
                <span
                    v-for="(v, i) in legend"
                    :key="i"
                    class="size-3.5 rounded-[3px]"
                    :style="{ background: color(v) }"
                />
                <span class="font-mono">0 – {{ format(max) }}</span>
            </span>
        </div>
    </figure>
</template>

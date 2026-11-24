<script setup>
// Горизонтальные столбцы для рейтингов (топ блюд, выручка по официантам). Одна серия.
import { computed, ref } from 'vue';
import { SERIES } from './chart';

const props = defineProps({
    rows: { type: Array, default: () => [] }, // [{ label, value, hint? }]
    format: { type: Function, required: true },
    title: { type: String, required: true },
});

const max = computed(() => Math.max(1, ...props.rows.map((r) => r.value)));
const hover = ref(null);
</script>

<template>
    <figure
        class="flex flex-col gap-1.5"
        role="img"
        :aria-label="title"
        @mouseleave="hover = null"
    >
        <div
            v-for="(r, i) in rows"
            :key="`${r.label}${i}`"
            class="grid grid-cols-[minmax(0,160px)_1fr_auto] items-center gap-3 rounded-sm px-1 py-0.5 text-[13px]"
            :class="hover === i ? 'bg-surface-2' : ''"
            :title="r.hint"
            @mouseenter="hover = i"
        >
            <span class="truncate">{{ r.label }}</span>
            <span class="h-3.5">
                <span
                    class="block h-full rounded-r-[4px]"
                    :style="{
                        width: `${Math.max(r.value ? 1.5 : 0, (r.value / max) * 100)}%`,
                        background: SERIES.primary,
                        opacity: hover === null || hover === i ? 1 : 0.5,
                    }"
            /></span>
            <span class="text-right font-mono text-ink-2 num">{{
                format(r.value)
            }}</span>
        </div>
    </figure>
</template>

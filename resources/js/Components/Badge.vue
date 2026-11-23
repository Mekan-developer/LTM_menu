<script setup>
import { computed } from 'vue';

// Единая семантика статусов на всех экранах.
const props = defineProps({
    tone: { type: String, default: 'neutral' }, // neutral | brand | ok | ochre | accent | danger | muted | outline
});

const tones = {
    neutral: 'bg-surface-2 text-ink-2',
    brand: 'bg-brand text-brand-ink',
    ok: 'bg-ok-soft text-ok',
    ochre: 'bg-ochre-soft text-ochre-ink',
    accent: 'bg-accent text-accent-ink',
    danger: 'bg-danger-soft text-danger',
    muted: 'bg-surface-2 text-ink-3',
    outline: 'bg-transparent text-ink border border-ink',
};

const cls = computed(() => tones[props.tone] ?? tones.neutral);
</script>

<template>
    <span
        class="inline-flex h-[22px] items-center whitespace-nowrap rounded-sm px-2 font-mono text-[11px] font-medium"
        :class="cls"
    >
        <slot />
    </span>
</template>

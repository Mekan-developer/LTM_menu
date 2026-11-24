<script setup>
// Неинтерактивный объект схемы: бар, вход, окно, стена, подпись.
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { ELEMENT_CLASS } from './hall';

const props = defineProps({ element: { type: Object, required: true } });
const { t, loc } = useI18n();

const cls = computed(() => ELEMENT_CLASS[props.element.type] ?? '');
const text = computed(
    () =>
        loc(props.element, 'label') ||
        (['bar', 'entrance'].includes(props.element.type)
            ? t(`hall.el_${props.element.type}`)
            : ''),
);
</script>

<template>
    <div
        class="pointer-events-none absolute flex items-center justify-center overflow-hidden font-mono text-[11px] tracking-[0.06em] uppercase"
        :class="cls"
        :style="{
            left: `${element.pos_x}px`,
            top: `${element.pos_y}px`,
            width: `${element.width}px`,
            height: `${element.height}px`,
        }"
    >
        {{ text }}
    </div>
</template>

<script setup>
import Icon from '@/Components/Icon.vue';
import { useFieldControl } from '@/composables/useFieldControl';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number, null], default: '' });

const props = defineProps({
    id: { type: String, default: null },
    type: { type: String, default: 'text' },
    mono: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm 32 · md 36 · lg 44
    prefix: { type: String, default: null },
    icon: { type: String, default: null }, // значок слева, например search
});

const { invalid: isInvalid, aria } = useFieldControl(props);
const heights = { sm: 'h-8', md: 'h-9', lg: 'h-11' };
</script>

<template>
    <div
        class="flex overflow-hidden rounded-md border transition-colors duration-[120ms] focus-within:ring-1"
        :class="[
            isInvalid
                ? 'border-danger bg-danger-soft focus-within:ring-danger'
                : 'border-line bg-surface hover:border-ink-3 focus-within:border-brand focus-within:ring-brand',
            heights[size] ?? heights.md,
        ]"
    >
        <span
            v-if="prefix"
            class="flex items-center bg-surface-2 px-2.5 font-mono text-sm text-ink-2"
            >{{ prefix }}</span
        >
        <span v-if="icon" class="flex items-center pl-3 text-ink-3"
            ><Icon :name="icon" :size="16"
        /></span>
        <input
            :id="id"
            v-model="model"
            :type="type"
            class="w-full min-w-0 bg-transparent px-3 text-sm text-ink outline-none placeholder:text-ink-3"
            :class="[
                mono ? 'font-mono num' : '',
                size === 'lg' ? 'text-base' : '',
                icon ? 'pl-2' : '',
            ]"
            v-bind="{ ...aria, ...$attrs }"
        />
    </div>
</template>

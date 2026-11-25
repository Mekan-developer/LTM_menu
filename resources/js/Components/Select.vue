<script setup>
// Выпадающий список в стиле TextInput: та же высота, фон, наведение, фокус и ошибка.
import { computed } from 'vue';
import { useFieldControl } from '@/composables/useFieldControl';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number, null], default: null });

const props = defineProps({
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm 32 · md 36 · lg 44
    // Текст невыбираемого первого пункта, пока значение null.
    placeholder: { type: String, default: null },
});

const { invalid: isInvalid, aria } = useFieldControl(props);
const heights = { sm: 'h-8', md: 'h-9', lg: 'h-11' };
const empty = computed(() => props.placeholder && model.value === null);
</script>

<template>
    <select
        :id="id"
        v-model="model"
        class="w-full min-w-0 cursor-pointer rounded-md border px-2 text-sm transition-colors duration-[120ms] outline-none focus:ring-1 disabled:cursor-not-allowed disabled:opacity-60 [&>option]:text-ink"
        :class="[
            isInvalid
                ? 'border-danger bg-danger-soft focus:ring-danger'
                : 'border-line bg-surface hover:border-ink-3 focus:border-brand focus:ring-brand',
            heights[size] ?? heights.md,
            empty ? 'text-ink-3' : 'text-ink',
        ]"
        v-bind="{ ...aria, ...$attrs }"
    >
        <option v-if="placeholder" :value="null" disabled>
            {{ placeholder }}
        </option>
        <slot />
    </select>
</template>

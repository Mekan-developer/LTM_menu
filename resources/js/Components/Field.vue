<script setup>
import { provide } from 'vue';
import { FIELD } from '@/composables/useFieldControl';

const props = defineProps({
    label: { type: String, default: '' },
    for: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});

provide(FIELD, props);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            v-if="label"
            :for="$props.for"
            class="text-[13px] font-medium text-ink"
            >{{ label }}</label
        >
        <slot />
        <p
            v-if="error"
            :id="$props.for ? `${$props.for}-error` : undefined"
            class="text-xs text-danger"
            role="alert"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="$props.for ? `${$props.for}-hint` : undefined"
            class="text-xs text-ink-3"
        >
            {{ hint }}
        </p>
    </div>
</template>

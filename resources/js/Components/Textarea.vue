<script setup>
// Многострочное поле в стиле TextInput.
import { useFieldControl } from '@/composables/useFieldControl';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, null], default: '' });

const props = defineProps({
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

const { invalid: isInvalid, aria } = useFieldControl(props);
</script>

<template>
    <textarea
        :id="id"
        v-model="model"
        rows="3"
        class="min-h-20 w-full resize-y rounded-md border px-3 py-2 text-sm text-ink transition-colors duration-[120ms] outline-none placeholder:text-ink-3 focus:ring-1"
        :class="
            isInvalid
                ? 'border-danger bg-danger-soft focus:ring-danger'
                : 'border-line bg-surface hover:border-ink-3 focus:border-brand focus:ring-brand'
        "
        v-bind="{ ...aria, ...$attrs }"
    />
</template>

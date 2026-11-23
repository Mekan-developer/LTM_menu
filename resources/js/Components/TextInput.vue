<script setup>
defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number, null], default: '' });

defineProps({
    id: { type: String, default: null },
    type: { type: String, default: 'text' },
    mono: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // md 36 · lg 44
    prefix: { type: String, default: null },
});
</script>

<template>
    <div
        class="flex overflow-hidden rounded-md border bg-surface focus-within:border-2 focus-within:border-brand"
        :class="[
            invalid ? 'border-danger bg-danger-soft' : 'border-line',
            size === 'lg' ? 'h-11' : 'h-9',
        ]"
    >
        <span
            v-if="prefix"
            class="flex items-center bg-surface-2 px-2.5 font-mono text-sm text-ink-2"
            >{{ prefix }}</span
        >
        <input
            :id="id"
            v-model="model"
            :type="type"
            class="w-full min-w-0 bg-transparent px-3 text-sm text-ink outline-none placeholder:text-ink-3"
            :class="[
                mono ? 'font-mono num' : '',
                size === 'lg' ? 'text-base' : '',
            ]"
            v-bind="$attrs"
        />
    </div>
</template>

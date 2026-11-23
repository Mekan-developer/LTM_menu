<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toasts = ref([]);
let id = 0;

function push(kind, text) {
    if (!text) return;
    const toast = { id: ++id, kind, text };
    toasts.value.push(toast);
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== toast.id);
    }, 6000);
}

watch(
    () => page.props.flash,
    (flash) => {
        push('success', flash?.success);
        push('error', flash?.error);
    },
    { deep: true, immediate: true },
);

defineExpose({ push });
</script>

<template>
    <div
        class="no-print pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[min(380px,calc(100vw-2rem))] flex-col gap-2"
        aria-live="polite"
    >
        <TransitionGroup
            enter-active-class="transition duration-[240ms] ease-out"
            enter-from-class="translate-y-3 opacity-0"
            leave-active-class="transition duration-[160ms] ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-start gap-3 rounded-lg border border-line bg-surface px-4 py-3 text-ink shadow-[0_1px_2px_rgba(0,0,0,.08),0_8px_24px_-8px_rgba(29,36,32,.25)]"
            >
                <span
                    class="mt-1.5 size-2.5 shrink-0 rounded-xs"
                    :class="toast.kind === 'error' ? 'bg-danger' : 'bg-ok'"
                />
                <span class="text-sm">{{ toast.text }}</span>
            </div>
        </TransitionGroup>
    </div>
</template>

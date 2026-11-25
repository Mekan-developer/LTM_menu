<script setup>
import { onMounted, onUnmounted, watch } from 'vue';

/** Боковая панель справа поверх экрана. На телефоне — во всю ширину. */
const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: 'sm:max-w-xl' },
});

const emit = defineEmits(['close']);

const onKey = (e) => {
    if (e.key === 'Escape' && props.show) emit('close');
};

onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

watch(
    () => props.show,
    (v) => {
        document.body.style.overflow = v ? 'hidden' : '';
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-150"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 bg-ink/40"
                @click.self="emit('close')"
            >
                <aside
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    class="drawer-panel absolute inset-y-0 right-0 flex w-full flex-col border-l border-line bg-surface text-ink shadow-[0_24px_48px_-12px_rgba(29,36,32,.32)]"
                    :class="width"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-line px-5 py-4"
                    >
                        <h2 class="text-xl font-semibold">{{ title }}</h2>
                        <button
                            type="button"
                            class="flex size-11 items-center justify-center rounded-md text-ink-2 hover:bg-surface-2"
                            aria-label="Закрыть"
                            @click="emit('close')"
                        >
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 18 18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M4 4l10 10M14 4 4 14" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        <slot />
                    </div>
                </aside>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.drawer-panel {
    animation: drawer-in 0.22s ease-out;
}
@keyframes drawer-in {
    from {
        transform: translateX(100%);
    }
}
@media (prefers-reduced-motion: reduce) {
    .drawer-panel {
        animation: none;
    }
}
</style>

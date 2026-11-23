<script setup>
import { onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);

const onKey = (e) => {
    if (e.key === 'Escape' && props.show) emit('close');
};

onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));

watch(
    () => props.show,
    (v) => {
        document.body.style.overflow = v ? 'hidden' : '';
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 sm:items-center sm:p-6"
            @click.self="emit('close')"
        >
            <div
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-xl border border-line bg-surface shadow-[0_2px_4px_rgba(29,36,32,.1),0_24px_48px_-12px_rgba(29,36,32,.32)] sm:rounded-lg"
                :class="width"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-line px-5 py-4"
                >
                    <h2 class="text-lg font-semibold">{{ title }}</h2>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-md text-ink-2 hover:bg-surface-2"
                        aria-label="Закрыть"
                        @click="emit('close')"
                    >
                        <svg
                            width="18"
                            height="18"
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
                <div class="overflow-y-auto px-5 py-4">
                    <slot />
                </div>
                <div
                    v-if="$slots.footer"
                    class="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-3"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const model = defineModel({ type: String, default: '' });
const props = defineProps({
    error: { type: String, default: null },
    length: { type: Number, default: 4 },
});
const emit = defineEmits(['complete']);

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'back', '0', 'ok'];
const full = computed(() => model.value.length >= props.length);

function press(key) {
    if (key === 'back') {
        model.value = model.value.slice(0, -1);
    } else if (key === 'ok') {
        if (full.value) emit('complete', model.value);
    } else if (!full.value) {
        // model.value обновится только после ре-рендера родителя — длину проверяем по локальному значению.
        const next = model.value + key;
        model.value = next;
        if (next.length === props.length) emit('complete', next);
    }
}
</script>

<template>
    <div class="flex w-full max-w-[280px] flex-col items-center gap-5">
        <div
            class="flex gap-4"
            :class="error ? 'animate-[shake_240ms]' : ''"
            aria-hidden="true"
        >
            <span
                v-for="i in length"
                :key="i"
                class="size-4 rounded-full"
                :class="i <= model.length ? 'bg-ink' : 'border-2 border-ink-3'"
            />
        </div>
        <p
            v-if="error"
            class="min-h-5 text-center text-sm text-danger"
            role="alert"
        >
            {{ error }}
        </p>
        <div class="grid w-full grid-cols-3 gap-2.5">
            <button
                v-for="key in keys"
                :key="key"
                type="button"
                class="flex h-16 items-center justify-center rounded-md border border-line font-mono text-2xl font-medium active:translate-y-px"
                :class="
                    key === 'ok'
                        ? 'bg-brand-soft'
                        : 'bg-surface hover:bg-surface-2'
                "
                :aria-label="
                    key === 'back' ? 'Стереть' : key === 'ok' ? 'Войти' : key
                "
                @click="press(key)"
            >
                <svg
                    v-if="key === 'back'"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <path d="M9 6h11v12H9l-5-6z" />
                    <path d="m12 10 4 4m0-4-4 4" />
                </svg>
                <span
                    v-else-if="key === 'ok'"
                    class="font-sans text-base font-semibold"
                    >OK</span
                >
                <span v-else>{{ key }}</span>
            </button>
        </div>
    </div>
</template>

<style>
@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-6px);
    }
    75% {
        transform: translateX(6px);
    }
}
</style>

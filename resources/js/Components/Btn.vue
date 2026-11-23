<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger | accent
    size: { type: String, default: 'md' }, // sm 32 · md 36 · lg 44 · xl 52 · xxl 64
    href: { type: String, default: null },
    method: { type: String, default: null },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const sizes = {
    sm: 'h-8 px-3 text-[13px]',
    md: 'h-9 px-3.5 text-sm',
    lg: 'h-11 px-4 text-[15px]',
    xl: 'h-13 px-5 text-base',
    xxl: 'h-16 px-6 text-lg',
};

const variants = {
    primary:
        'bg-brand text-brand-ink border border-brand hover:bg-brand-hover hover:border-brand-hover font-semibold',
    accent: 'bg-accent text-accent-ink border border-accent hover:brightness-95 font-semibold',
    secondary:
        'bg-surface text-ink border border-ink-2 hover:bg-surface-2 hover:border-ink font-medium',
    ghost: 'bg-transparent text-ink border border-transparent hover:bg-surface-2 font-medium',
    danger: 'bg-transparent text-danger border border-danger hover:bg-danger-soft font-medium',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-md select-none whitespace-nowrap',
    'transition-colors duration-[120ms] active:translate-y-px',
    'disabled:bg-surface-2 disabled:text-ink-3 disabled:border-surface-2 disabled:cursor-not-allowed disabled:translate-y-0',
    sizes[props.size] ?? sizes.md,
    variants[props.variant] ?? variants.primary,
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :method="method"
        :as="method ? 'button' : 'a'"
        :class="classes"
    >
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="disabled || loading"
        :aria-busy="loading"
    >
        <svg
            v-if="loading"
            class="size-4 animate-spin"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
        >
            <circle
                cx="8"
                cy="8"
                r="6"
                stroke="currentColor"
                stroke-opacity=".3"
                stroke-width="2"
            />
            <path
                d="M14 8a6 6 0 0 0-6-6"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
            />
        </svg>
        <slot />
    </button>
</template>

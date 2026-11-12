<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Minimal geometric mark: an isometric product box. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 42"
        :class="className"
        v-bind="$attrs"
    >
        <path fill="currentColor" d="M20 3.9 34.4 12 20 20.1 5.6 12 20 3.9Z" />
        <path
            fill="currentColor"
            fill-opacity="0.55"
            d="M4.8 13.35 19.2 21.45V37.65L4.8 29.55V13.35Z"
        />
        <path
            fill="currentColor"
            fill-opacity="0.8"
            d="M35.2 13.35V29.55L20.8 37.65V21.45L35.2 13.35Z"
        />
    </svg>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import LangSwitch from '@/Components/LangSwitch.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import FlashToast from '@/Components/FlashToast.vue';
import { useTheme } from '@/composables/useTheme';

defineProps({ title: { type: String, default: '' } });
useTheme();
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-[100dvh] flex-col bg-paper">
        <header class="flex items-center justify-between px-6 py-4">
            <span
                class="font-mono text-xs tracking-[0.08em] text-ink-2 uppercase"
                >{{ $page.props.app.restaurant_name }}</span
            >
            <div class="flex items-center gap-3">
                <ThemeToggle />
                <LangSwitch />
            </div>
        </header>
        <main class="flex flex-1 items-center justify-center px-4 pb-16">
            <slot />
        </main>
        <FlashToast />
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({ dark: { type: Boolean, default: false } });

const { locale } = useI18n();
const set = (l) => {
    if (l !== locale.value)
        router.post(route('locale.switch', l), {}, { preserveScroll: true });
};
</script>

<template>
    <div
        class="flex overflow-hidden rounded-md border border-line font-mono text-xs"
        role="group"
        aria-label="TM / RU"
    >
        <button
            v-for="l in ['tk', 'ru']"
            :key="l"
            type="button"
            class="h-8 px-2.5"
            :class="
                locale === l
                    ? 'bg-ink text-paper'
                    : 'text-ink-2 hover:bg-surface-2'
            "
            :aria-pressed="locale === l"
            @click="set(l)"
        >
            {{ l === 'tk' ? 'TM' : 'RU' }}
        </button>
    </div>
</template>

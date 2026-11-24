<script setup>
// Переключатель темы: дорожка с солнцем и луной, кружок едет к выбранной.
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { dark, toggleTheme } from '@/composables/useTheme';

const { t } = useI18n();
</script>

<template>
    <button
        type="button"
        role="switch"
        class="relative flex h-8 w-[60px] shrink-0 items-center justify-between rounded-full border border-line bg-surface-2 px-[7px] text-ink-3 hover:text-ink-2"
        :aria-checked="dark"
        :aria-label="t('theme.dark')"
        :title="dark ? t('theme.to_light') : t('theme.to_dark')"
        @click="toggleTheme"
    >
        <Icon name="sun" :size="15" />
        <Icon name="moon" :size="15" />
        <span
            class="absolute top-[3px] left-[3px] flex size-6 items-center justify-center rounded-full bg-ink text-paper shadow-[0_1px_3px_rgb(0_0_0/0.25)] transition-transform duration-200 ease-out motion-reduce:transition-none"
            :class="dark ? 'translate-x-7' : 'translate-x-0'"
            aria-hidden="true"
        >
            <Icon :name="dark ? 'moon' : 'sun'" :size="14" />
        </span>
    </button>
</template>

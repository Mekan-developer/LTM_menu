<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { useIdleLogout } from '@/composables/useIdleLogout';

defineProps({ title: { type: String, default: '' } });

const { t } = useI18n();
const { warning, left } = useIdleLogout();
const online = ref(true);

// Ночная смена: тема переключается кнопкой и запоминается на устройстве.
const night = ref(true);
// Класс на <html>, чтобы тема доходила и до модалок (Teleport в body).
const applyTheme = (v) =>
    document.documentElement.classList.toggle('theme-night', v);
onMounted(() => {
    try {
        night.value = localStorage.getItem('waiter-theme') !== 'day';
    } catch {
        /* приватный режим */
    }
    // При переходе между страницами официанта onUnmounted старого layout срабатывает после setup нового,
    // поэтому класс ставим в onMounted — он выполняется уже после снятия класса старым экземпляром.
    applyTheme(night.value);
    online.value = navigator.onLine;
    window.addEventListener('online', () => {
        online.value = true;
    });
    window.addEventListener('offline', () => {
        online.value = false;
    });
});
watch(night, applyTheme);
onUnmounted(() => document.documentElement.classList.remove('theme-night'));

const toggleTheme = () => {
    night.value = !night.value;
    try {
        localStorage.setItem('waiter-theme', night.value ? 'night' : 'day');
    } catch {
        /* */
    }
};
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-[100dvh] flex-col bg-paper text-ink">
        <div
            v-if="warning"
            class="bg-ochre px-4 py-1.5 text-center text-sm font-medium text-[#121714]"
        >
            {{ t('auth.idle_warning', { s: left }) }}
        </div>

        <header
            class="sticky top-0 z-20 flex items-center gap-2.5 border-b border-line bg-paper px-3 py-2.5"
        >
            <slot name="header">
                <span
                    class="flex size-10 items-center justify-center rounded-sm bg-surface-2 text-sm font-semibold"
                    >{{
                        $page.props.auth.user?.name?.slice(0, 2).toUpperCase()
                    }}</span
                >
                <div class="flex flex-1 flex-col leading-tight">
                    <span class="text-base font-semibold">{{ title }}</span>
                    <span class="font-mono text-xs text-ink-2">{{
                        $page.props.auth.user?.name
                    }}</span>
                </div>
            </slot>
            <span
                class="flex h-7 items-center gap-1.5 rounded-sm border border-line px-2 font-mono text-[11px] text-ink-2"
            >
                <span
                    class="size-[7px] rounded-full"
                    :class="online ? 'bg-ok' : 'bg-danger'"
                />{{ online ? t('waiter.online') : t('waiter.offline') }}
            </span>
        </header>

        <main class="flex-1 px-3 pt-3 pb-28">
            <slot />
        </main>

        <nav
            :aria-label="t('nav.sections')"
            class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-3 border-t border-line bg-surface px-2 pt-1.5 pb-[max(env(safe-area-inset-bottom),12px)]"
        >
            <Link
                :href="route('waiter.index')"
                class="flex h-14 flex-col items-center justify-center gap-1 text-xs font-semibold"
            >
                <Icon name="tables" :size="22" />{{ t('waiter.tables') }}
            </Link>
            <button
                type="button"
                class="flex h-14 flex-col items-center justify-center gap-1 text-xs text-ink-2"
                @click="toggleTheme"
            >
                <Icon name="settings" :size="22" />{{
                    night ? t('waiter.day_theme') : t('waiter.night_theme')
                }}
            </button>
            <button
                type="button"
                class="flex h-14 flex-col items-center justify-center gap-1 text-xs text-ink-2"
                @click="router.post(route('logout'))"
            >
                <Icon name="logout" :size="22" />{{ t('nav.logout') }}
            </button>
        </nav>

        <FlashToast />
    </div>
</template>

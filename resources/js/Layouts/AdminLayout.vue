<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';
import LangSwitch from '@/Components/LangSwitch.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { useIdleLogout } from '@/composables/useIdleLogout';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const { t } = useI18n();
const { warning, left } = useIdleLogout();
const navOpen = ref(false);

const can = (p) => page.props.auth.user?.permissions?.includes(p);

// Пункты меню видны только с правом — кнопок, которые нельзя нажать, нет.
const groups = computed(() =>
    [
        {
            title: t('nav.shift'),
            items: [
                {
                    label: t('nav.dashboard'),
                    route: 'dashboard',
                    icon: 'dashboard',
                    perm: 'reports',
                },
                {
                    label: t('nav.reports'),
                    route: 'reports.index',
                    icon: 'chart',
                    perm: 'reports',
                },
                {
                    label: t('nav.waiter'),
                    route: 'waiter.index',
                    icon: 'waiter',
                    perm: 'area_waiter',
                },
                {
                    label: t('nav.kitchen'),
                    route: 'kitchen.index',
                    icon: 'kitchen',
                    perm: 'area_kitchen',
                },
                {
                    label: t('nav.cashier'),
                    route: 'cashier.index',
                    icon: 'cashier',
                    perm: 'area_cashier',
                },
            ],
        },
        {
            title: t('nav.menu_group'),
            items: [
                {
                    label: t('nav.menu'),
                    route: 'menu.index',
                    icon: 'menu',
                    perm: 'menu_prices',
                },
                {
                    label: t('nav.stop_list'),
                    route: 'stop-list.index',
                    icon: 'stop',
                    perm: 'stop_list',
                },
                {
                    label: t('nav.tables'),
                    route: 'tables.index',
                    icon: 'tables',
                    perm: 'tables',
                },
            ],
        },
        {
            title: t('nav.team'),
            items: [
                {
                    label: t('nav.staff'),
                    route: 'staff.index',
                    icon: 'staff',
                    perm: 'staff_permissions',
                },
                {
                    label: t('nav.roles'),
                    route: 'roles.index',
                    icon: 'roles',
                    perm: 'staff_permissions',
                },
            ],
        },
        {
            title: t('nav.system'),
            items: [
                {
                    label: t('nav.reviews'),
                    route: 'reviews.index',
                    icon: 'star',
                    perm: 'reviews',
                    badge: page.props.reviews_unseen,
                },
                {
                    label: t('nav.logs'),
                    route: 'logs.index',
                    icon: 'log',
                    perm: 'action_log',
                },
                {
                    label: t('nav.settings'),
                    route: 'settings.edit',
                    icon: 'settings',
                    perm: 'settings',
                },
            ],
        },
    ]
        .map((g) => ({ ...g, items: g.items.filter((i) => can(i.perm)) }))
        .filter((g) => g.items.length),
);

const active = (name) =>
    route().current(name) || route().current(name.replace('.index', '.*'));
const logout = () => router.post(route('logout'));

// Низкая оценка гостя — сразу обновляем счётчик в меню (ТЗ 2.9).
onMounted(() => {
    if (can('reviews') && window.Echo) {
        window.Echo.private('reviews').listen('.review.low', () =>
            router.reload({ only: ['reviews_unseen'] }),
        );
    }
});
onUnmounted(() => {
    if (can('reviews')) window.Echo?.leave('reviews');
});
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-paper text-ink">
        <div
            v-if="warning"
            class="fixed inset-x-0 top-0 z-50 bg-ochre px-4 py-1.5 text-center text-sm font-medium text-ink no-print"
        >
            {{ t('auth.idle_warning', { s: left }) }}
        </div>

        <header
            class="no-print flex min-h-14 flex-wrap items-center gap-3 border-b border-line bg-surface px-4 sm:px-6"
        >
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-md hover:bg-surface-2 lg:hidden"
                :aria-label="t('nav.open')"
                @click="navOpen = !navOpen"
            >
                <Icon name="menu" />
            </button>
            <Link :href="route('home')" class="flex items-center gap-2.5">
                <span
                    class="flex size-7 items-center justify-center rounded-sm bg-brand text-sm font-bold text-brand-ink"
                    >{{
                        ($page.props.app.restaurant_name || 'R').slice(0, 1)
                    }}</span
                >
                <span class="text-[15px] font-semibold">{{
                    $page.props.app.restaurant_name
                }}</span>
            </Link>
            <div class="ml-auto flex items-center gap-3">
                <LangSwitch />
                <div
                    class="hidden items-center gap-2 border-l border-line pl-3 sm:flex"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-sm bg-surface-2 text-xs font-semibold"
                        >{{
                            $page.props.auth.user?.name
                                ?.slice(0, 2)
                                .toUpperCase()
                        }}</span
                    >
                    <div class="flex flex-col leading-tight">
                        <span class="text-[13px] font-medium">{{
                            $page.props.auth.user?.name
                        }}</span>
                        <span class="text-[11px] text-ink-3">{{
                            $page.props.auth.user?.role
                        }}</span>
                    </div>
                </div>
                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-md border border-line text-ink-2 hover:bg-surface-2"
                    :aria-label="t('nav.logout')"
                    @click="logout"
                >
                    <Icon name="logout" />
                </button>
            </div>
        </header>

        <div class="flex flex-wrap">
            <nav
                :aria-label="t('nav.sections')"
                class="no-print w-full border-b border-line bg-surface px-3 py-4 lg:block lg:min-h-[calc(100vh-56px)] lg:w-[232px] lg:border-r lg:border-b-0"
                :class="navOpen ? 'block' : 'hidden'"
            >
                <div
                    v-for="group in groups"
                    :key="group.title"
                    class="mb-4 flex flex-col gap-0.5"
                >
                    <span
                        class="px-2.5 pb-1 font-mono text-[10px] tracking-[0.08em] text-ink-3 uppercase"
                        >{{ group.title }}</span
                    >
                    <Link
                        v-for="item in group.items"
                        :key="item.route"
                        :href="route(item.route)"
                        class="flex h-9 items-center gap-2.5 rounded-sm px-2.5 text-sm"
                        :class="
                            active(item.route)
                                ? 'bg-brand-soft font-semibold'
                                : 'hover:bg-surface-2'
                        "
                    >
                        <Icon :name="item.icon" class="text-ink-2" />
                        {{ item.label }}
                        <span
                            v-if="item.badge"
                            class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1.5 font-mono text-[11px] font-semibold text-white"
                            :aria-label="t('reviews.unseen', { n: item.badge })"
                            >{{ item.badge }}</span
                        >
                    </Link>
                </div>
            </nav>

            <main class="min-w-0 flex-[999_1_560px] px-4 py-6 sm:px-8">
                <slot />
            </main>
        </div>

        <FlashToast />
    </div>
</template>

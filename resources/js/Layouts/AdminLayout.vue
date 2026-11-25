<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';
import LangSwitch from '@/Components/LangSwitch.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { useIdleLogout } from '@/composables/useIdleLogout';
import { useTheme } from '@/composables/useTheme';

const props = defineProps({
    title: { type: String, default: '' },
    // Свернуть сайдбар по умолчанию (полоса иконок 56 px) — для широких экранов вроде схемы зала.
    compact: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const { warning, left } = useIdleLogout();
useTheme();
const navOpen = ref(false);

// Сайдбар на компьютере сворачивается в полосу иконок и разворачивается обратно.
// Выбор пользователя запоминается в браузере; без выбора — как просит страница (compact).
const collapsed = ref(props.compact);
const desktop = () => window.matchMedia('(min-width: 1024px)').matches;
onMounted(() => {
    try {
        const saved = localStorage.getItem('admin-sidebar');
        if (saved) collapsed.value = saved === 'collapsed';
    } catch {
        /* приватный режим */
    }
});
function toggleNav() {
    if (!desktop()) {
        navOpen.value = !navOpen.value;
        return;
    }
    collapsed.value = !collapsed.value;
    try {
        localStorage.setItem(
            'admin-sidebar',
            collapsed.value ? 'collapsed' : 'expanded',
        );
    } catch {
        /* */
    }
}

// perm: null — пункт для всех сотрудников (например, «Мой график»).
const can = (p) =>
    p === null ||
    [p].flat().some((x) => page.props.auth.user?.permissions?.includes(x));

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
                    label: t('nav.hall'),
                    route: 'hall.index',
                    icon: 'tables',
                    perm: ['area_waiter', 'tables', 'reports'],
                },
                {
                    label: t('nav.orders'),
                    route: 'orders.index',
                    icon: 'orders',
                    perm: 'orders',
                },
                {
                    label: t('nav.deliveries'),
                    route: 'deliveries.index',
                    icon: 'bag',
                    perm: 'delivery',
                },
                {
                    label: t('nav.courier'),
                    route: 'courier.index',
                    icon: 'pin',
                    perm: 'area_courier',
                },
                {
                    label: t('nav.bookings'),
                    route: 'bookings.index',
                    icon: 'calendar',
                    perm: 'bookings',
                },
                {
                    label: t('nav.guests'),
                    route: 'guests.index',
                    icon: 'guests',
                    perm: 'guests',
                },
                {
                    label: t('nav.waiter'),
                    route: 'waiter.index',
                    icon: 'waiter',
                    perm: 'area_waiter',
                    newTab: true,
                },
                {
                    label: t('nav.kitchen'),
                    route: 'kitchen.index',
                    icon: 'kitchen',
                    perm: 'area_kitchen',
                    newTab: true,
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
                    label: t('nav.costing'),
                    route: 'costing.index',
                    icon: 'chart',
                    perm: 'costing',
                },
                {
                    label: t('nav.products'),
                    route: 'costing.products.index',
                    icon: 'bag',
                    perm: 'costing',
                },
                {
                    label: t('nav.stock'),
                    route: 'stock.index',
                    icon: 'tables',
                    perm: 'stock',
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
                {
                    label: t('nav.shifts'),
                    route: 'shifts.index',
                    icon: 'calendar',
                    perm: 'shifts',
                },
                {
                    label: t('nav.my_shifts'),
                    route: 'my-shifts',
                    icon: 'staff',
                    perm: null,
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
            class="no-print sticky top-0 z-30 flex min-h-14 flex-wrap items-center gap-3 border-b border-line bg-surface px-4 sm:px-6"
        >
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-md hover:bg-surface-2"
                :aria-label="t('nav.toggle')"
                :aria-expanded="navOpen || !collapsed"
                aria-controls="admin-nav"
                @click="toggleNav"
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
                <ThemeToggle />
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
                id="admin-nav"
                :aria-label="t('nav.sections')"
                class="no-print w-full border-b border-line bg-surface py-4 transition-[width] duration-150 lg:sticky lg:top-14 lg:block lg:h-[calc(100vh-56px)] lg:self-start lg:overflow-y-auto lg:border-r lg:border-b-0"
                :class="[
                    navOpen ? 'block' : 'hidden',
                    collapsed ? 'px-3 lg:w-14 lg:px-1.5' : 'px-3 lg:w-[232px]',
                ]"
            >
                <div
                    v-for="group in groups"
                    :key="group.title"
                    class="mb-4 flex flex-col gap-0.5"
                >
                    <span
                        class="px-2.5 pb-1 font-mono text-[10px] tracking-[0.08em] text-ink-3 uppercase"
                        :class="collapsed ? 'lg:sr-only' : ''"
                        >{{ group.title }}</span
                    >
                    <!-- Рабочие экраны (официант, кухня) открываются в новой вкладке:
                         обычная ссылка, потому что Link не умеет target="_blank". -->
                    <component
                        :is="item.newTab ? 'a' : Link"
                        v-for="item in group.items"
                        :key="item.route"
                        :href="route(item.route)"
                        :target="item.newTab ? '_blank' : undefined"
                        :rel="item.newTab ? 'noopener' : undefined"
                        class="relative flex h-9 items-center gap-2.5 rounded-sm px-2.5 text-sm"
                        :class="[
                            active(item.route)
                                ? 'bg-brand-soft font-semibold'
                                : 'hover:bg-surface-2',
                            collapsed ? 'lg:justify-center lg:px-0' : '',
                        ]"
                        :title="collapsed ? item.label : undefined"
                    >
                        <Icon :name="item.icon" class="shrink-0 text-ink-2" />
                        <span :class="collapsed ? 'lg:sr-only' : ''">{{
                            item.label
                        }}</span>
                        <span
                            v-if="item.badge"
                            class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1.5 font-mono text-[11px] font-semibold text-paper"
                            :class="
                                collapsed
                                    ? 'lg:absolute lg:-top-1 lg:right-0 lg:h-4 lg:min-w-4 lg:px-1 lg:text-[10px]'
                                    : ''
                            "
                            :aria-label="t('reviews.unseen', { n: item.badge })"
                            >{{ item.badge }}</span
                        >
                    </component>
                </div>
            </nav>

            <main class="min-w-0 flex-[999_1_560px] px-4 py-6 sm:px-8">
                <slot />
            </main>
        </div>

        <FlashToast />
    </div>
</template>

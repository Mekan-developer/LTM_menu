<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';
import { useI18n } from '@/i18n';
import { elapsed, minutesSince, time } from '@/utils/format';
import { useNow } from '@/composables/useNow';
import { useRealtime } from '@/composables/useRealtime';
import { useSound } from '@/composables/useSound';
import { useIdleLogout } from '@/composables/useIdleLogout';

const props = defineProps({
    station: { type: String, required: true },
    board: { type: Object, required: true },
    thresholds: { type: Object, required: true },
});

const { t, loc } = useI18n();
const page = usePage();
const now = useNow();
const sound = useSound();
useIdleLogout();

const canStop = computed(() =>
    page.props.auth.user?.permissions.includes('stop_list'),
);
const doneKeys = ref(new Set()); // скрываем карточку сразу после «Готово», не дожидаясь сервера

const tickets = computed(() =>
    props.board.tickets.filter((tk) => !doneKeys.value.has(tk.key)),
);
const late = computed(
    () =>
        tickets.value.filter(
            (tk) =>
                minutesSince(tk.sent_at, now.value) >= props.thresholds.late,
        ).length,
);

// Возраст заказа = цвет шапки: до warn — нейтральная, до late — охра, дальше — красная.
function tone(ticket) {
    const m = minutesSince(ticket.sent_at, now.value);
    if (m >= props.thresholds.late) return 'bg-k-late text-white';
    if (m >= props.thresholds.warn) return 'bg-k-warn text-k-bg';
    return 'bg-k-line text-k-ink';
}

let knownKeys = new Set(props.board.tickets.map((tk) => tk.key));
const freshKeys = ref(new Set());
watch(
    () => props.board.tickets,
    (list) => {
        const added = list
            .filter((tk) => !knownKeys.has(tk.key))
            .map((tk) => tk.key);
        if (added.length) {
            sound.newOrder();
            freshKeys.value = new Set(added);
            setTimeout(() => {
                freshKeys.value = new Set();
            }, 1500);
        }
        knownKeys = new Set(list.map((tk) => tk.key));
        doneKeys.value = new Set(
            [...doneKeys.value].filter((k) => knownKeys.has(k)),
        );
    },
);

const reload = () => router.reload({ only: ['board'], preserveScroll: true });
useRealtime(`kitchen.${props.station}`, 'kitchen.changed', reload, 10000);

function ready(ticket) {
    doneKeys.value = new Set(doneKeys.value).add(ticket.key);
    router.post(
        route('kitchen.ready', [ticket.order_id, ticket.batch]),
        { station: props.station },
        {
            preserveScroll: true,
            onError: () => {
                const d = new Set(doneKeys.value);
                d.delete(ticket.key);
                doneKeys.value = d;
            },
        },
    );
}

const clock = computed(() => time(new Date(now.value).toISOString()));
</script>

<template>
    <Head :title="t(`station.${station}`)" />
    <div class="flex min-h-[100dvh] flex-col bg-k-bg font-kds text-k-ink">
        <header
            class="flex flex-wrap items-center gap-6 border-b border-k-line px-6 py-3"
        >
            <nav
                class="flex overflow-hidden rounded-md border border-k-line"
                :aria-label="t('kitchen.stations')"
            >
                <Link
                    v-for="s in ['kitchen', 'bar']"
                    :key="s"
                    :href="route('kitchen.index', s)"
                    class="flex h-14 items-center px-6 text-[28px] font-extrabold tracking-wide uppercase"
                    :class="station === s ? 'bg-k-ink text-k-bg' : 'text-k-dim'"
                    >{{ t(`station.${s}`) }}</Link
                >
            </nav>
            <span class="text-2xl font-bold text-k-dim uppercase"
                >{{ t('kitchen.in_work') }}
                <span class="font-mono text-k-ink">{{
                    tickets.length
                }}</span></span
            >
            <span class="text-2xl font-bold text-k-dim uppercase"
                >{{ t('kitchen.late') }}
                <span
                    class="font-mono"
                    :class="late ? 'text-[#FF7A6E]' : 'text-k-ink'"
                    >{{ late }}</span
                ></span
            >
            <div class="ml-auto flex items-center gap-4">
                <Link
                    v-if="canStop"
                    :href="route('stop-list.index')"
                    class="flex h-14 items-center rounded-md border-2 border-k-warn px-5 text-2xl font-bold text-k-warn uppercase"
                    >{{ t('nav.stop_list') }}</Link
                >
                <span class="font-mono text-4xl font-semibold">{{
                    clock
                }}</span>
                <button
                    type="button"
                    class="h-14 rounded-md border border-k-line px-4 text-lg text-k-dim"
                    @click="router.post(route('logout'))"
                >
                    {{ t('nav.logout') }}
                </button>
            </div>
        </header>

        <main
            class="grid flex-1 auto-rows-min grid-cols-[repeat(auto-fill,minmax(340px,1fr))] gap-4 p-5"
        >
            <article
                v-for="ticket in tickets"
                :key="ticket.key"
                class="flex flex-col overflow-hidden rounded-lg border-2 bg-k-card"
                :class="
                    freshKeys.has(ticket.key)
                        ? 'slide-in border-k-ink'
                        : 'border-k-card'
                "
            >
                <div
                    class="flex items-center justify-between gap-3 px-4 py-3"
                    :class="tone(ticket)"
                >
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="truncate text-4xl leading-[38px] font-extrabold uppercase"
                            >{{ t('tables.table') }}
                            {{ ticket.table ?? '—' }}</span
                        >
                        <span
                            class="truncate text-xl font-bold uppercase opacity-85"
                            >{{ ticket.source === 'qr' ? 'QR · ' : ''
                            }}{{ ticket.waiter ?? '' }}</span
                        >
                    </div>
                    <span class="font-mono text-4xl font-semibold">{{
                        elapsed(ticket.sent_at, now)
                    }}</span>
                </div>
                <ul class="flex flex-1 flex-col gap-2 px-4 py-3">
                    <li
                        v-for="item in ticket.items"
                        :key="item.id"
                        class="flex flex-col gap-1"
                    >
                        <div class="flex items-baseline gap-3">
                            <span
                                class="min-w-11 font-mono text-[26px] font-semibold text-k-warn"
                                >{{ item.qty }}×</span
                            >
                            <span
                                class="text-[30px] leading-[34px] font-bold uppercase"
                                >{{ loc(item, 'name') }}</span
                            >
                        </div>
                        <span
                            v-if="item.comment"
                            class="ml-14 self-start rounded-sm bg-k-ink px-2.5 py-0.5 text-2xl font-extrabold text-k-bg uppercase"
                            >{{ item.comment }}</span
                        >
                    </li>
                </ul>
                <button
                    type="button"
                    class="h-[72px] border-t border-k-line bg-k-ink text-[32px] font-extrabold tracking-[0.06em] text-k-bg uppercase active:bg-k-ready"
                    @click="ready(ticket)"
                >
                    {{ t('kitchen.ready') }}
                </button>
            </article>
            <p
                v-if="!tickets.length"
                class="col-span-full pt-24 text-center text-3xl text-k-dim"
            >
                {{ t('kitchen.empty') }}
            </p>
        </main>

        <footer
            class="flex min-h-[72px] flex-wrap items-center gap-3 border-t border-k-line bg-[#111312] px-6 py-2"
        >
            <span class="text-2xl font-bold text-k-dim uppercase">{{
                t('kitchen.pickup')
            }}</span>
            <span
                v-for="p in board.pickup"
                :key="p.key"
                class="flex h-12 items-center gap-2 rounded-md border border-k-ready bg-[#1E3A2A] px-4 text-2xl font-bold"
            >
                {{ t('tables.table') }} {{ p.table }}
                <span class="font-mono text-lg text-k-ready">{{
                    elapsed(p.ready_at, now)
                }}</span>
            </span>
        </footer>

        <FlashToast />
    </div>
</template>

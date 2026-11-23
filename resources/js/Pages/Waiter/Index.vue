<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import WaiterLayout from '@/Layouts/WaiterLayout.vue';
import Btn from '@/Components/Btn.vue';
import { useI18n } from '@/i18n';
import { money, time } from '@/utils/format';
import { useRealtime } from '@/composables/useRealtime';
import { useSound } from '@/composables/useSound';

const props = defineProps({
    orders: { type: Array, default: () => [] },
    freeTables: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const page = usePage();
const sound = useSound();
const me = computed(() => page.props.auth.user);
const seeAll = computed(() => me.value.permissions.includes('transfer_tables'));

const reload = () =>
    router.reload({ only: ['orders', 'freeTables'], preserveScroll: true });

useRealtime('waiters', 'waiter.alert', (payload) => {
    if (payload) {
        const mine =
            payload.waiter_id === null ||
            payload.waiter_id === me.value.id ||
            seeAll.value;
        if (!mine) return;
        if (payload.type === 'qr_order') sound.newOrder();
        else sound.ready();
    }
    reload();
});

// Состояние стола для карточки: что от официанта нужно прямо сейчас.
const cards = computed(() =>
    props.orders.map((o) => {
        const byStatus = (s) => o.items.filter((i) => i.status === s);
        const pending = byStatus('pending');
        const ready = byStatus('ready');
        const sent = byStatus('sent');
        const pendingBatches = [...new Set(pending.map((i) => i.batch))].map(
            (batch) => ({
                batch,
                items: pending.filter((i) => i.batch === batch),
                total: pending
                    .filter((i) => i.batch === batch)
                    .reduce((s, i) => s + i.total, 0),
            }),
        );

        let head = t('waiter.state_open');
        let tone = 'text-ink-2';
        if (pending.length) {
            head = t('waiter.state_qr');
            tone = 'text-accent';
        } else if (ready.length) {
            head = t('waiter.state_ready');
            tone = 'text-ok';
        } else if (o.status === 'bill_requested') {
            head = t('waiter.state_bill');
            tone = 'text-accent';
        } else if (sent.length) {
            head = t('waiter.state_kitchen', { n: sent.length });
            tone = 'text-ochre-ink';
        }

        return {
            ...o,
            pendingBatches,
            ready,
            sent,
            head,
            tone,
            bill: o.status === 'bill_requested',
        };
    }),
);

const confirm = (order, batch) =>
    router.post(
        route('waiter.qr.confirm', [order.id, batch]),
        {},
        { preserveScroll: true },
    );
const reject = (order, batch) =>
    router.post(
        route('waiter.qr.reject', [order.id, batch]),
        {},
        { preserveScroll: true },
    );
const openTable = (table) => router.post(route('waiter.open', table.id));
</script>

<template>
    <WaiterLayout :title="t('waiter.my_tables')">
        <div class="flex flex-col gap-2.5">
            <!-- QR-заказы — первыми и крупно: одно нажатие отправляет на кухню -->
            <template v-for="o in cards" :key="`qr-${o.id}`">
                <section
                    v-for="b in o.pendingBatches"
                    :key="b.batch"
                    class="pulse-ring flex flex-col gap-2.5 rounded-lg border-2 border-accent bg-surface p-3"
                    :aria-label="`${t('waiter.qr_order')} ${o.table?.number}`"
                >
                    <div class="flex items-baseline justify-between">
                        <span class="text-[17px] font-bold"
                            >{{ t('waiter.qr_order') }} ·
                            {{ t('tables.table') }} {{ o.table?.number }}</span
                        >
                        <span class="font-mono text-xs text-accent">{{
                            time(b.items[0]?.sent_at ?? o.opened_at)
                        }}</span>
                    </div>
                    <p class="text-[15px] text-ink-2">
                        {{
                            b.items
                                .map(
                                    (i) =>
                                        `${loc(i, 'name')}${i.quantity > 1 ? ' ×' + i.quantity : ''}`,
                                )
                                .join(' · ')
                        }}
                        <span class="font-mono text-ink"
                            >· {{ money(b.total) }}</span
                        >
                    </p>
                    <p v-if="o.guest_comment" class="text-[13px] text-accent">
                        «{{ o.guest_comment }}»
                    </p>
                    <div class="grid grid-cols-[96px_1fr] gap-2">
                        <Btn
                            variant="secondary"
                            size="xl"
                            @click="reject(o, b.batch)"
                            >{{ t('waiter.reject') }}</Btn
                        >
                        <Btn size="xl" @click="confirm(o, b.batch)">{{
                            t('waiter.to_kitchen')
                        }}</Btn>
                    </div>
                </section>
            </template>

            <Link
                v-for="o in cards"
                :key="o.id"
                :href="route('waiter.order', o.id)"
                class="grid min-h-[76px] grid-cols-[64px_1fr_auto] items-center gap-3 rounded-lg border bg-surface py-2.5 pr-3 pl-2.5"
                :class="o.ready.length ? 'border-ok' : 'border-line'"
            >
                <span
                    class="flex h-14 w-16 flex-col items-center justify-center rounded-sm"
                    :class="
                        o.bill
                            ? 'bg-accent text-accent-ink'
                            : 'bg-brand text-brand-ink'
                    "
                >
                    <span
                        class="font-mono text-[22px] leading-6 font-semibold"
                        >{{ o.table?.number }}</span
                    >
                </span>
                <span class="flex min-w-0 flex-col gap-1">
                    <span class="text-base font-bold" :class="o.tone">{{
                        o.head
                    }}</span>
                    <span class="truncate text-[13px] text-ink-2">
                        <template v-if="o.ready.length">{{
                            o.ready.map((i) => loc(i, 'name')).join(', ')
                        }}</template>
                        <template v-else>{{
                            o.waiter?.name ?? t('waiter.no_waiter')
                        }}</template>
                    </span>
                </span>
                <span class="flex flex-col items-end gap-1">
                    <span class="font-mono text-sm num">{{
                        money(o.total)
                    }}</span>
                    <span class="font-mono text-xs text-ink-3">{{
                        time(o.opened_at)
                    }}</span>
                </span>
            </Link>

            <p
                v-if="!cards.length"
                class="rounded-lg border border-dashed border-line px-4 py-8 text-center text-sm text-ink-2"
            >
                {{ t('waiter.no_tables') }}
            </p>

            <div class="mt-2 flex flex-col gap-2 px-0.5">
                <span
                    class="font-mono text-[11px] tracking-[0.08em] text-ink-3 uppercase"
                    >{{ t('waiter.free_tables') }}</span
                >
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="tb in freeTables"
                        :key="tb.id"
                        type="button"
                        class="flex h-13 items-center justify-center rounded-md border-2 border-dashed border-free font-mono text-lg font-semibold active:translate-y-px"
                        :aria-label="`${t('waiter.open_table')} ${tb.number}`"
                        @click="openTable(tb)"
                    >
                        {{ tb.number }}
                    </button>
                </div>
            </div>
        </div>
    </WaiterLayout>
</template>

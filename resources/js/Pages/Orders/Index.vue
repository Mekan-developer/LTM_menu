<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Drawer from '@/Components/Drawer.vue';
import Icon from '@/Components/Icon.vue';
import Select from '@/Components/Select.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { dateTime, money, phone, time } from '@/utils/format';

const props = defineProps({
    period: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    orders: { type: Object, required: true },
    waiters: { type: Array, default: () => [] },
    detail: { type: Object, default: null },
});

const { t, loc } = useI18n();

// --- Период: по умолчанию сегодня -------------------------------------------
const iso = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const presets = computed(() => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const day = (n) => iso(new Date(y, m, now.getDate() - n));
    return [
        { key: 'today', from: day(0), to: day(0) },
        { key: 'yesterday', from: day(1), to: day(1) },
        { key: 'days7', from: day(6), to: day(0) },
        { key: 'month', from: iso(new Date(y, m, 1)), to: day(0) },
    ];
});
const activePreset = computed(
    () =>
        presets.value.find(
            (p) => p.from === props.period.from && p.to === props.period.to,
        )?.key,
);
const oneDay = computed(() => props.period.from === props.period.to);
const includesToday = computed(() => props.period.to >= iso(new Date()));

const from = ref(props.period.from);
const to = ref(props.period.to);
const search = ref(props.filters.q ?? '');

// Все фильтры — в адресе: экран можно обновить или переслать ссылкой.
function apply(change = {}) {
    const params = {
        from: from.value,
        to: to.value,
        channel: props.filters.channel,
        status: props.filters.status,
        waiter: props.filters.waiter,
        q: search.value.trim() || null,
        ...change,
    };
    router.get(
        route('orders.index'),
        Object.fromEntries(
            Object.entries(params).filter(([, v]) => v !== null && v !== ''),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function pickPreset(p) {
    from.value = p.from;
    to.value = p.to;
    apply({ from: p.from, to: p.to });
}
watch(
    () => props.period,
    (p) => {
        from.value = p.from;
        to.value = p.to;
    },
);

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply(), 350);
});

const pageUrl = (n) =>
    route('orders.index', {
        ...Object.fromEntries(
            Object.entries({
                from: props.period.from,
                to: props.period.to,
                ...props.filters,
            }).filter(([, v]) => v !== null && v !== ''),
        ),
        page: n,
    });

// --- Живое обновление: период включает сегодня — раз в 20 секунд -------------
let poll = null;
onMounted(() => {
    poll = setInterval(() => {
        if (includesToday.value && document.visibilityState === 'visible') {
            router.reload({
                only: ['orders', 'summary'],
                preserveScroll: true,
            });
        }
    }, 20000);
});
onUnmounted(() => {
    clearInterval(poll);
    clearTimeout(searchTimer);
});

// --- Карточка заказа ----------------------------------------------------------
const openId = ref(null);
function open(order) {
    openId.value = order.id;
    router.reload({
        only: ['detail'],
        data: { show: order.id },
        preserveScroll: true,
    });
}
const shown = computed(() =>
    props.detail && props.detail.id === openId.value ? props.detail : null,
);

// --- Подписи -----------------------------------------------------------------
const channels = ['hall', 'delivery', 'pickup'];
const statuses = [
    'active',
    'open',
    'bill_requested',
    'paid',
    'cancelled',
    'refunded',
];

// У активной доставки важнее её этап («ждёт звонка», «у курьера»), чем «открыт».
function statusLabel(o) {
    if (o.delivery_status && ['open', 'bill_requested'].includes(o.status)) {
        return t(`delivery.status_${o.delivery_status}`);
    }
    return t(`orders.status_${o.status}`);
}
function statusTone(o) {
    if (o.delivery_status === 'new') return 'ochre';
    return (
        {
            open: 'brand',
            bill_requested: 'accent',
            paid: 'ok',
            cancelled: 'danger',
            refunded: 'danger',
        }[o.status] ?? 'neutral'
    );
}
const where = (o) =>
    o.channel === 'hall'
        ? o.table
            ? `${t('tables.table')} ${o.table}`
            : '—'
        : t(`delivery.type_${o.channel}`);

const kpis = computed(() => [
    { key: 'count', value: props.summary.count },
    { key: 'active', value: props.summary.active },
    { key: 'paid', value: props.summary.paid },
    { key: 'revenue', value: money(props.summary.revenue), unit: 'TMT' },
    { key: 'cancelled', value: props.summary.cancelled },
]);

const itemTone = (s) =>
    ({ removed: 'danger', pending: 'ochre', draft: 'muted', ready: 'ok' })[s] ??
    'neutral';
</script>

<template>
    <AdminLayout :title="t('nav.orders')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('nav.orders') }}
                </h1>
                <span
                    v-if="includesToday"
                    class="flex items-center gap-1.5 text-xs text-ink-3"
                    ><span class="size-2 rounded-full bg-ok" />{{
                        t('orders.live')
                    }}</span
                >
            </div>

            <!-- Период -->
            <div
                class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface p-3"
            >
                <button
                    v-for="p in presets"
                    :key="p.key"
                    type="button"
                    class="h-8 rounded-sm border px-3 text-[13px]"
                    :class="
                        activePreset === p.key
                            ? 'border-ink bg-ink text-paper'
                            : 'border-line hover:bg-surface-2'
                    "
                    @click="pickPreset(p)"
                >
                    {{ t(`orders.preset_${p.key}`) }}
                </button>
                <span class="mx-1 hidden h-6 w-px bg-line sm:block" />
                <label class="flex items-center gap-1.5 text-[13px] text-ink-2"
                    >{{ t('reports.from')
                    }}<span class="block w-36"
                        ><TextInput
                            v-model="from"
                            type="date"
                            size="sm"
                            mono /></span
                ></label>
                <label class="flex items-center gap-1.5 text-[13px] text-ink-2"
                    >{{ t('reports.to')
                    }}<span class="block w-36"
                        ><TextInput
                            v-model="to"
                            type="date"
                            size="sm"
                            mono /></span
                ></label>
                <button
                    type="button"
                    class="h-8 rounded-sm bg-brand px-3 text-[13px] font-semibold text-brand-ink"
                    @click="apply()"
                >
                    {{ t('reports.show') }}
                </button>
            </div>

            <!-- Сводка -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                <div
                    v-for="k in kpis"
                    :key="k.key"
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t(`orders.kpi_${k.key}`)
                    }}</span>
                    <span
                        class="font-mono text-[26px] leading-8 font-semibold num"
                        >{{ k.value }}
                        <span v-if="k.unit" class="text-sm text-ink-3">{{
                            k.unit
                        }}</span></span
                    >
                </div>
            </div>

            <!-- Фильтры -->
            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="flex overflow-hidden rounded-md border border-line bg-surface"
                    role="group"
                    :aria-label="t('orders.channel')"
                >
                    <button
                        v-for="c in [null, ...channels]"
                        :key="c ?? 'all'"
                        type="button"
                        class="h-9 border-l border-line px-3 text-sm first:border-l-0"
                        :class="
                            filters.channel === c
                                ? 'bg-ink text-paper'
                                : 'hover:bg-surface-2'
                        "
                        :aria-pressed="filters.channel === c"
                        @click="apply({ channel: c })"
                    >
                        {{
                            c === null
                                ? t('common.all')
                                : t(`orders.channel_${c}`)
                        }}
                    </button>
                </div>
                <div class="w-44">
                    <Select
                        :model-value="filters.status ?? ''"
                        :aria-label="t('orders.status')"
                        @update:model-value="apply({ status: $event || null })"
                    >
                        <option value="">{{ t('orders.all_statuses') }}</option>
                        <option v-for="s in statuses" :key="s" :value="s">
                            {{ t(`orders.status_${s}`) }}
                        </option>
                    </Select>
                </div>
                <div v-if="waiters.length" class="w-44">
                    <Select
                        :model-value="filters.waiter ?? ''"
                        :aria-label="t('orders.waiter')"
                        @update:model-value="apply({ waiter: $event || null })"
                    >
                        <option value="">{{ t('orders.all_waiters') }}</option>
                        <option v-for="w in waiters" :key="w.id" :value="w.id">
                            {{ w.name }}
                        </option>
                    </Select>
                </div>
                <div class="w-full sm:ml-auto sm:w-72">
                    <TextInput
                        v-model="search"
                        type="search"
                        icon="search"
                        :placeholder="t('orders.search')"
                        :aria-label="t('orders.search')"
                    />
                </div>
            </div>

            <!-- Список -->
            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[860px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">№</th>
                            <th class="px-4 py-2.5">{{ t('orders.time') }}</th>
                            <th class="px-4 py-2.5">
                                {{ t('orders.where') }}
                            </th>
                            <th class="px-4 py-2.5">
                                {{ t('orders.guest') }}
                            </th>
                            <th class="px-4 py-2.5">
                                {{ t('orders.waiter') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('orders.items') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('orders.total') }}
                            </th>
                            <th class="px-4 py-2.5">
                                {{ t('orders.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="o in orders.data"
                            :key="o.id"
                            tabindex="0"
                            class="h-12 cursor-pointer border-t border-line hover:bg-surface-2 focus-visible:bg-surface-2 focus-visible:outline-none"
                            :class="openId === o.id ? 'bg-brand-soft' : ''"
                            @click="open(o)"
                            @keydown.enter="open(o)"
                        >
                            <td class="px-4 font-mono font-semibold">
                                {{ o.id }}
                            </td>
                            <td class="px-4 font-mono text-ink-2">
                                {{
                                    oneDay
                                        ? time(o.opened_at)
                                        : dateTime(o.opened_at)
                                }}
                            </td>
                            <td class="px-4">
                                <span class="flex flex-col leading-tight">
                                    <span class="font-medium">{{
                                        where(o)
                                    }}</span>
                                    <span class="text-xs text-ink-3">{{
                                        t(`orders.source_${o.source}`)
                                    }}</span>
                                </span>
                            </td>
                            <td class="px-4">
                                <span class="flex flex-col leading-tight">
                                    <span>{{ o.guest ?? '—' }}</span>
                                    <span
                                        v-if="o.phone"
                                        class="font-mono text-xs text-ink-3"
                                        >{{ phone(o.phone) }}</span
                                    >
                                </span>
                            </td>
                            <td class="px-4 text-ink-2">
                                {{ o.waiter ?? '—' }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ o.items_qty }}
                            </td>
                            <td class="px-4 text-right">
                                <span class="flex flex-col leading-tight">
                                    <span class="font-mono font-semibold num">{{
                                        money(o.total)
                                    }}</span>
                                    <span
                                        v-if="o.method"
                                        class="text-xs text-ink-3"
                                        >{{ t(`pay.${o.method}`) }}</span
                                    >
                                </span>
                            </td>
                            <td class="px-4">
                                <Badge :tone="statusTone(o)">{{
                                    statusLabel(o)
                                }}</Badge>
                            </td>
                        </tr>
                        <tr v-if="!orders.data.length">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-ink-3"
                            >
                                {{ t('orders.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="orders.last_page > 1"
                class="flex items-center justify-end gap-2 text-sm"
            >
                <a
                    v-if="orders.current_page > 1"
                    :href="pageUrl(orders.current_page - 1)"
                    class="h-9 rounded-md border border-line bg-surface px-3 leading-9 hover:bg-surface-2"
                    @click.prevent="
                        router.get(
                            pageUrl(orders.current_page - 1),
                            {},
                            {
                                preserveState: true,
                            },
                        )
                    "
                    >←</a
                >
                <span class="font-mono text-ink-2"
                    >{{ orders.current_page }} / {{ orders.last_page }} ·
                    {{ orders.total }}</span
                >
                <a
                    v-if="orders.current_page < orders.last_page"
                    :href="pageUrl(orders.current_page + 1)"
                    class="h-9 rounded-md border border-line bg-surface px-3 leading-9 hover:bg-surface-2"
                    @click.prevent="
                        router.get(
                            pageUrl(orders.current_page + 1),
                            {},
                            {
                                preserveState: true,
                            },
                        )
                    "
                    >→</a
                >
            </div>
        </div>

        <!-- Карточка заказа -->
        <Drawer
            :show="openId !== null"
            :title="openId ? `${t('orders.order')} №${openId}` : ''"
            @close="openId = null"
        >
            <p v-if="!shown" class="py-10 text-center text-sm text-ink-3">…</p>
            <div v-else class="flex flex-col gap-5 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :tone="statusTone(shown)">{{
                        statusLabel(shown)
                    }}</Badge>
                    <Badge tone="outline">{{
                        t(`orders.channel_${shown.channel}`)
                    }}</Badge>
                    <span class="text-ink-3">{{
                        t(`orders.source_${shown.source}`)
                    }}</span>
                </div>

                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
                    <template v-if="shown.channel === 'hall'">
                        <dt class="text-ink-2">{{ t('tables.table') }}</dt>
                        <dd>
                            {{ shown.table ?? '—' }}
                            <span v-if="shown.hall" class="text-ink-3"
                                >· {{ loc(shown.hall, 'name') }}</span
                            >
                            <span v-if="shown.table_deleted" class="text-ink-3"
                                >({{ t('orders.table_deleted') }})</span
                            >
                        </dd>
                    </template>
                    <dt class="text-ink-2">{{ t('orders.waiter') }}</dt>
                    <dd>{{ shown.waiter ?? '—' }}</dd>
                    <dt class="text-ink-2">{{ t('orders.guest') }}</dt>
                    <dd>
                        {{ shown.guest ?? '—' }}
                        <a
                            v-if="shown.phone"
                            :href="`tel:${shown.phone}`"
                            class="ml-1 font-mono text-ink-2 hover:underline"
                            >{{ phone(shown.phone) }}</a
                        >
                    </dd>
                    <template v-if="shown.delivery?.address">
                        <dt class="text-ink-2">{{ t('delivery.address') }}</dt>
                        <dd>{{ shown.delivery.address }}</dd>
                    </template>
                    <template v-if="shown.delivery?.courier">
                        <dt class="text-ink-2">{{ t('delivery.courier') }}</dt>
                        <dd>{{ shown.delivery.courier }}</dd>
                    </template>
                    <template v-if="shown.guest_comment">
                        <dt class="text-ink-2">
                            {{ t('delivery.comment') }}
                        </dt>
                        <dd class="italic">«{{ shown.guest_comment }}»</dd>
                    </template>
                </dl>

                <!-- Позиции -->
                <section class="flex flex-col gap-1.5">
                    <h3 class="text-xs font-semibold text-ink-2 uppercase">
                        {{ t('orders.items_title') }}
                    </h3>
                    <ul
                        class="flex flex-col divide-y divide-line rounded-md border border-line"
                    >
                        <li
                            v-for="i in shown.items"
                            :key="i.id"
                            class="flex flex-col gap-0.5 px-3 py-2"
                            :class="i.status === 'removed' ? 'opacity-70' : ''"
                        >
                            <div class="flex items-baseline gap-2">
                                <span class="font-mono text-ink-2"
                                    >{{ i.qty }}×</span
                                >
                                <span
                                    class="flex-1"
                                    :class="
                                        i.status === 'removed'
                                            ? 'line-through'
                                            : ''
                                    "
                                    >{{ loc(i, 'name') }}</span
                                >
                                <Badge :tone="itemTone(i.status)">{{
                                    t(`orders.item_${i.status}`)
                                }}</Badge>
                                <span class="w-20 text-right font-mono num">{{
                                    money(i.total)
                                }}</span>
                            </div>
                            <span
                                v-if="i.comment"
                                class="text-xs text-ink-3 italic"
                                >«{{ i.comment }}»</span
                            >
                            <span
                                v-if="i.status === 'removed'"
                                class="text-xs text-danger"
                                >{{ i.removed_reason }} ·
                                {{ i.removed_by ?? '—' }}</span
                            >
                            <span
                                v-else-if="i.sent_at"
                                class="font-mono text-xs text-ink-3"
                                >{{ t('orders.sent') }} {{ time(i.sent_at)
                                }}<template v-if="i.ready_at">
                                    · {{ t('orders.ready') }}
                                    {{ time(i.ready_at) }}</template
                                ><template v-if="i.added_by">
                                    · {{ i.added_by }}</template
                                ></span
                            >
                        </li>
                        <li
                            v-if="!shown.items.length"
                            class="px-3 py-3 text-ink-3"
                        >
                            {{ t('orders.no_items') }}
                        </li>
                    </ul>
                    <dl
                        class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 px-1 pt-1"
                    >
                        <dt class="text-ink-2">{{ t('orders.subtotal') }}</dt>
                        <dd class="text-right font-mono num">
                            {{ money(shown.subtotal) }}
                        </dd>
                        <template v-if="shown.discount_amount">
                            <dt class="text-ink-2">
                                {{ t('orders.discount') }}
                                {{ shown.discount_percent }}%
                            </dt>
                            <dd class="text-right font-mono num">
                                −{{ money(shown.discount_amount) }}
                            </dd>
                        </template>
                        <template v-if="shown.delivery_fee">
                            <dt class="text-ink-2">{{ t('delivery.fee') }}</dt>
                            <dd class="text-right font-mono num">
                                {{ money(shown.delivery_fee) }}
                            </dd>
                        </template>
                        <dt class="font-semibold">{{ t('orders.total') }}</dt>
                        <dd class="text-right font-mono font-semibold num">
                            {{ money(shown.total, true) }}
                        </dd>
                    </dl>
                </section>

                <!-- Оплаты -->
                <section
                    v-if="shown.payments.length"
                    class="flex flex-col gap-1.5"
                >
                    <h3 class="text-xs font-semibold text-ink-2 uppercase">
                        {{ t('orders.payments') }}
                    </h3>
                    <div
                        v-for="p in shown.payments"
                        :key="p.id"
                        class="flex flex-col gap-0.5 rounded-md border border-line px-3 py-2"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <span
                                >{{ t(`pay.${p.method}`)
                                }}<template v-if="p.method === 'mixed'">
                                    ({{ money(p.cash) }} +
                                    {{ money(p.card) }})</template
                                ></span
                            >
                            <span
                                class="font-mono font-semibold num"
                                :class="p.cancelled_at ? 'line-through' : ''"
                                >{{ money(p.total) }}</span
                            >
                        </div>
                        <span class="font-mono text-xs text-ink-3"
                            >{{ dateTime(p.created_at) }} · {{ p.cashier ?? '—'
                            }}<template v-if="p.tips">
                                · {{ t('orders.tips') }}
                                {{ money(p.tips) }}</template
                            ></span
                        >
                        <span v-if="p.cancelled_at" class="text-xs text-danger"
                            >{{ t('orders.payment_cancelled') }}
                            {{ dateTime(p.cancelled_at) }}:
                            {{ p.cancel_reason }}</span
                        >
                    </div>
                </section>

                <!-- Хронология -->
                <section class="flex flex-col gap-1.5">
                    <h3 class="text-xs font-semibold text-ink-2 uppercase">
                        {{ t('orders.timeline') }}
                    </h3>
                    <ol class="flex flex-col gap-1 font-mono text-xs">
                        <li>
                            <span class="text-ink-3">{{
                                dateTime(shown.opened_at)
                            }}</span>
                            · {{ t('orders.t_opened') }}
                        </li>
                        <template v-if="shown.delivery">
                            <li
                                v-for="k in [
                                    'accepted_at',
                                    'cooking_at',
                                    'ready_at',
                                    'picked_at',
                                    'finished_at',
                                    'cancelled_at',
                                ].filter((k) => shown.delivery[k])"
                                :key="k"
                            >
                                <span class="text-ink-3">{{
                                    dateTime(shown.delivery[k])
                                }}</span>
                                · {{ t(`orders.t_${k}`) }}
                            </li>
                        </template>
                        <li v-if="shown.bill_requested_at">
                            <span class="text-ink-3">{{
                                dateTime(shown.bill_requested_at)
                            }}</span>
                            · {{ t('orders.t_bill') }}
                        </li>
                        <li v-if="shown.paid_at">
                            <span class="text-ink-3">{{
                                dateTime(shown.paid_at)
                            }}</span>
                            · {{ t('orders.t_paid') }}
                        </li>
                        <li v-for="l in shown.logs" :key="l.id">
                            <span class="text-ink-3">{{
                                dateTime(l.created_at)
                            }}</span>
                            · {{ t(`log.${l.action}`) }}
                            <span v-if="l.user" class="text-ink-3"
                                >· {{ l.user }}</span
                            >
                        </li>
                    </ol>
                </section>

                <p
                    v-if="shown.delivery?.cancel_reason"
                    class="flex items-center gap-1.5 text-danger"
                >
                    <Icon name="stop" :size="14" />{{
                        shown.delivery.cancel_reason
                    }}
                </p>
            </div>
        </Drawer>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import StackedBars from '@/Components/Charts/StackedBars.vue';
import Heatmap from '@/Components/Charts/Heatmap.vue';
import HBars from '@/Components/Charts/HBars.vue';
import { axisMoney, shortDate } from '@/Components/Charts/chart';
import { useI18n } from '@/i18n';
import { date, money, phone } from '@/utils/format';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    period: { type: Object, required: true },
    revenue: { type: Object, required: true },
    guests: { type: Object, required: true },
    canGuests: { type: Boolean, default: false },
    load: { type: Object, required: true },
    dishes: { type: Object, required: true },
    staff: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();

// --- Период ---------------------------------------------------------------
const from = ref(props.period.from);
const to = ref(props.period.to);
const iso = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const presets = computed(() => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const back = (n) => iso(new Date(y, m, now.getDate() - n));
    return [
        { key: 'today', from: iso(now), to: iso(now) },
        { key: 'days7', from: back(6), to: iso(now) },
        { key: 'days30', from: back(29), to: iso(now) },
        { key: 'month', from: iso(new Date(y, m, 1)), to: iso(now) },
        {
            key: 'last_month',
            from: iso(new Date(y, m - 1, 1)),
            to: iso(new Date(y, m, 0)),
        },
    ];
});
const activePreset = computed(
    () =>
        presets.value.find(
            (p) => p.from === props.period.from && p.to === props.period.to,
        )?.key,
);
function apply(f = from.value, tt = to.value) {
    router.get(
        route('reports.index'),
        { from: f, to: tt },
        { preserveState: true, preserveScroll: true },
    );
}
function pick(p) {
    from.value = p.from;
    to.value = p.to;
    apply(p.from, p.to);
}

// --- Вкладки ----------------------------------------------------------------
const tabs = ['revenue', 'guests', 'load', 'dishes', 'staff'];
const tab = ref('revenue');
const exportUrl = computed(() =>
    route('reports.export', {
        report: tab.value,
        from: props.period.from,
        to: props.period.to,
    }),
);

// --- Сводка -----------------------------------------------------------------
const delta = (cur, prev) => (prev ? ((cur - prev) / prev) * 100 : null);
const tot = computed(() => props.revenue.totals);
const prev = computed(() => props.revenue.previous);
const kpis = computed(() => [
    {
        label: t('reports.revenue'),
        value: money(tot.value.revenue),
        unit: 'TMT',
        delta: delta(tot.value.revenue, prev.value.revenue),
    },
    {
        label: t('reports.bills'),
        value: String(tot.value.bills),
        delta: delta(tot.value.bills, prev.value.bills),
    },
    {
        label: t('reports.avg_check'),
        value: money(tot.value.avg_check),
        unit: 'TMT',
        delta: delta(tot.value.avg_check, prev.value.avg_check),
    },
    {
        label: t('reports.tips'),
        value: money(tot.value.tips),
        unit: 'TMT',
        note: `${t('pay.cash')} ${money(tot.value.tips_cash)} · ${t('pay.card')} ${money(tot.value.tips_card)}`,
    },
]);
const pct = (d) =>
    `${d >= 0 ? '+' : '−'}${Math.abs(d).toFixed(1).replace('.', ',')} %`;

// --- Данные графиков ---------------------------------------------------------
const days = computed(() => props.revenue.days);
const revenuePoints = computed(() =>
    days.value.map((d) => ({
        date: d.date,
        value: d.revenue,
        prev: d.prev_revenue,
        prevDate: d.prev_date,
    })),
);
const avgPoints = computed(() =>
    days.value.map((d) => ({
        date: d.date,
        value: d.avg_check,
        prev: d.prev_avg_check,
        prevDate: d.prev_date,
    })),
);
const methodPoints = computed(() =>
    days.value.map((d) => ({ date: d.date, a: d.cash, b: d.card })),
);
const prevLabel = computed(
    () =>
        `${t('reports.prev_period')} · ${shortDate(prev.value.from)}–${shortDate(prev.value.to)}`,
);
const moneyFull = (v) => money(v, true);
// Выручка по каналам: зал, доставка, самовывоз (ТЗ 2.10).
const channelRows = computed(() =>
    ['hall', 'delivery', 'pickup'].map((c) => ({
        label: t(`reports.channel_${c}`),
        value: props.revenue.channels?.[c] ?? 0,
    })),
);

const weekdays = computed(() => t('dash.weekdays'));
const tablesFmt = (v) =>
    `${String(v).replace('.', ',')} ${t('reports.tables_short')}`;

const topRows = computed(() =>
    props.dishes.top.map((d) => ({
        label: loc(d, 'name'),
        value: d.qty,
        hint: money(d.amount, true),
    })),
);
const staffRows = computed(() =>
    props.staff.map((s) => ({ label: s.name, value: s.revenue })),
);
// --- Постоянные гости ---------------------------------------------------------
const share = (part, whole) =>
    whole ? `${Math.round((part / whole) * 100)} %` : '—';
const gt = computed(() => props.guests.totals);
const guestTiles = computed(() => [
    { label: t('reports.guests_total'), value: String(gt.value.guests) },
    {
        label: t('reports.guests_new'),
        value: String(gt.value.new),
        note: share(gt.value.new, gt.value.guests),
    },
    {
        label: t('reports.guests_returning'),
        value: String(gt.value.returning),
        note: share(gt.value.returning, gt.value.guests),
    },
    {
        label: t('reports.bills_with_guest'),
        value: share(gt.value.bills_with_guest, gt.value.bills),
        note: t('reports.of', {
            n: gt.value.bills_with_guest,
            m: gt.value.bills,
        }),
    },
]);
const guestPoints = computed(() =>
    props.guests.days.map((d) => ({ date: d.date, a: d.new, b: d.returning })),
);
const countFmt = (v) => String(v);
// Гостей считаем целыми: дробные деления оси не подписываем.
const countAxis = (v) => (Number.isInteger(v) ? String(v) : '');

const qtyFmt = (v) => `${v} ${t('reports.pcs')}`;
const rating = (v) => (v === null ? '—' : String(v).replace('.', ','));
</script>

<template>
    <AdminLayout :title="t('nav.reports')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('nav.reports') }}
                </h1>
            </div>

            <!-- Период: пресеты и свои даты в одной строке -->
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
                    @click="pick(p)"
                >
                    {{ t(`reports.preset_${p.key}`) }}
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

            <!-- Сводка за период -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div
                    v-for="k in kpis"
                    :key="k.label"
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{ k.label }}</span>
                    <span
                        class="font-mono text-[26px] leading-8 font-semibold num"
                        >{{ k.value }}
                        <span v-if="k.unit" class="text-sm text-ink-3">{{
                            k.unit
                        }}</span></span
                    >
                    <span
                        v-if="k.delta !== undefined"
                        class="font-mono text-xs"
                        :class="
                            k.delta === null
                                ? 'text-ink-3'
                                : k.delta >= 0
                                  ? 'text-ok'
                                  : 'text-danger'
                        "
                        >{{ k.delta === null ? '—' : pct(k.delta) }}
                        <span class="font-sans text-ink-3">{{
                            t('reports.vs_prev')
                        }}</span></span
                    >
                    <span v-else class="text-xs text-ink-3">{{ k.note }}</span>
                </div>
            </div>

            <!-- Вкладки + выгрузка -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-line"
            >
                <div class="flex gap-5 overflow-x-auto" role="tablist">
                    <button
                        v-for="tb in tabs"
                        :key="tb"
                        type="button"
                        role="tab"
                        :aria-selected="tab === tb"
                        class="-mb-px h-10 shrink-0 border-b-2 text-sm"
                        :class="
                            tab === tb
                                ? 'border-brand font-semibold'
                                : 'border-transparent text-ink-2 hover:text-ink'
                        "
                        @click="tab = tb"
                    >
                        {{ t(`reports.${tb}`) }}
                    </button>
                </div>
                <a
                    :href="exportUrl"
                    class="mb-1.5 inline-flex h-8 items-center gap-1.5 rounded-sm border border-line bg-surface px-3 text-[13px] font-medium hover:bg-surface-2"
                    ><Icon name="excel" :size="16" />Excel</a
                >
            </div>

            <!-- Выручка и средний чек -->
            <template v-if="tab === 'revenue'">
                <section class="rounded-lg border border-line bg-surface p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        {{ t('reports.revenue_by_day') }}
                    </h2>
                    <LineChart
                        :points="revenuePoints"
                        :format="moneyFull"
                        :axis-format="axisMoney"
                        :label="t('reports.this_period')"
                        :prev-label="prevLabel"
                    />
                </section>
                <div class="grid gap-5 xl:grid-cols-2">
                    <section
                        class="rounded-lg border border-line bg-surface p-5"
                    >
                        <h2 class="mb-3 text-base font-semibold">
                            {{ t('reports.avg_check_by_day') }}
                        </h2>
                        <LineChart
                            :points="avgPoints"
                            :format="moneyFull"
                            :axis-format="axisMoney"
                            :label="t('reports.this_period')"
                            :prev-label="prevLabel"
                        />
                    </section>
                    <section
                        class="rounded-lg border border-line bg-surface p-5"
                    >
                        <h2 class="mb-3 text-base font-semibold">
                            {{ t('reports.by_method') }}
                        </h2>
                        <StackedBars
                            :points="methodPoints"
                            :labels="[t('pay.cash'), t('pay.card')]"
                            :format="moneyFull"
                            :axis-format="axisMoney"
                            :title="t('reports.by_method')"
                        />
                    </section>
                </div>
                <section class="rounded-lg border border-line bg-surface p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        {{ t('reports.by_channel') }}
                    </h2>
                    <HBars
                        :rows="channelRows"
                        :format="moneyFull"
                        :title="t('reports.by_channel')"
                    />
                </section>

                <!-- Таблица — те же числа, что на графиках -->
                <div
                    class="overflow-x-auto rounded-lg border border-line bg-surface"
                >
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                        >
                            <tr>
                                <th class="px-4 py-2.5">
                                    {{ t('reports.date') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.revenue') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.bills') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.avg_check') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('pay.cash') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('pay.card') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.tips') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="font-mono">
                            <tr
                                v-for="d in [...days].reverse()"
                                :key="d.date"
                                class="h-10 border-t border-line"
                            >
                                <td class="px-4 text-ink-2">
                                    {{ shortDate(d.date) }}
                                </td>
                                <td class="px-4 text-right num">
                                    {{ money(d.revenue) }}
                                </td>
                                <td class="px-4 text-right num">
                                    {{ d.bills }}
                                </td>
                                <td class="px-4 text-right num">
                                    {{ money(d.avg_check) }}
                                </td>
                                <td class="px-4 text-right text-ink-2 num">
                                    {{ money(d.cash) }}
                                </td>
                                <td class="px-4 text-right text-ink-2 num">
                                    {{ money(d.card) }}
                                </td>
                                <td class="px-4 text-right text-ink-2 num">
                                    {{ money(d.tips) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Постоянные гости -->
            <template v-else-if="tab === 'guests'">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div
                        v-for="k in guestTiles"
                        :key="k.label"
                        class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                    >
                        <span class="text-[13px] text-ink-2">{{
                            k.label
                        }}</span>
                        <span
                            class="font-mono text-[26px] leading-8 font-semibold num"
                            >{{ k.value }}</span
                        >
                        <span
                            v-if="k.note"
                            class="font-mono text-xs text-ink-3"
                            >{{ k.note }}</span
                        >
                    </div>
                </div>

                <section class="rounded-lg border border-line bg-surface p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        {{ t('reports.guests_by_day') }}
                    </h2>
                    <StackedBars
                        :points="guestPoints"
                        :labels="[
                            t('reports.guests_new'),
                            t('reports.guests_returning'),
                        ]"
                        :format="countFmt"
                        :axis-format="countAxis"
                        :title="t('reports.guests_by_day')"
                    />
                </section>
                <p class="text-xs text-ink-3">{{ t('reports.guests_hint') }}</p>

                <section
                    class="overflow-x-auto rounded-lg border border-line bg-surface"
                >
                    <h2
                        class="border-b border-line px-4 py-3 text-base font-semibold"
                    >
                        {{ t('reports.guests_top') }}
                    </h2>
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                        >
                            <tr>
                                <th class="w-10 px-4 py-2.5">#</th>
                                <th class="px-4 py-2.5">
                                    {{ t('guests.name') }}
                                </th>
                                <th v-if="canGuests" class="px-4 py-2.5">
                                    {{ t('guests.phone') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('guests.visits') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('guests.spent') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.avg_check') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('guests.last_visit') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(g, i) in guests.top"
                                :key="g.id"
                                class="h-10 border-t border-line"
                            >
                                <td class="px-4 font-mono text-ink-3 num">
                                    {{ i + 1 }}
                                </td>
                                <td class="px-4">
                                    <span class="flex items-center gap-2">
                                        <Link
                                            v-if="canGuests"
                                            :href="route('guests.show', g.id)"
                                            class="font-medium hover:underline"
                                            >{{ g.name }}</Link
                                        >
                                        <span v-else class="font-medium">{{
                                            g.name
                                        }}</span>
                                        <Badge v-if="g.is_new" tone="ok">{{
                                            t('reports.new_badge')
                                        }}</Badge>
                                    </span>
                                </td>
                                <td
                                    v-if="canGuests"
                                    class="px-4 font-mono text-ink-2"
                                >
                                    {{ phone(g.phone) }}
                                </td>
                                <td class="px-4 text-right font-mono num">
                                    {{ g.visits }}
                                </td>
                                <td class="px-4 text-right font-mono num">
                                    {{ money(g.spent) }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono text-ink-2 num"
                                >
                                    {{ money(g.avg_check) }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono text-ink-2 num"
                                >
                                    {{ date(g.last_at) }}
                                </td>
                            </tr>
                            <tr v-if="!guests.top.length">
                                <td
                                    :colspan="canGuests ? 7 : 6"
                                    class="px-4 py-8 text-center text-ink-3"
                                >
                                    {{ t('reports.guests_empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>

            <!-- Загрузка зала -->
            <section
                v-else-if="tab === 'load'"
                class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
            >
                <div class="flex flex-col gap-0.5">
                    <h2 class="text-base font-semibold">
                        {{ t('reports.load_title') }}
                    </h2>
                    <span class="text-xs text-ink-3">{{
                        t('reports.load_hint', { n: load.tables })
                    }}</span>
                </div>
                <Heatmap
                    :matrix="load.matrix"
                    :max="load.max"
                    :days="weekdays"
                    :format="tablesFmt"
                    :title="t('reports.load_title')"
                />
            </section>

            <!-- Блюда -->
            <div v-else-if="tab === 'dishes'" class="grid gap-5 xl:grid-cols-2">
                <section class="rounded-lg border border-line bg-surface p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        {{ t('reports.top_dishes') }}
                    </h2>
                    <HBars
                        v-if="topRows.length"
                        :rows="topRows"
                        :format="qtyFmt"
                        :title="t('reports.top_dishes')"
                    />
                    <p v-else class="py-6 text-center text-sm text-ink-3">
                        {{ t('reports.no_data') }}
                    </p>
                </section>
                <section class="rounded-lg border border-line bg-surface p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        {{ t('reports.rare_dishes') }}
                    </h2>
                    <div
                        v-for="d in dishes.rare"
                        :key="`${d.dish_id}${d.name_tm}`"
                        class="flex justify-between gap-3 border-t border-line py-2 text-sm first:border-t-0"
                    >
                        <span>{{ loc(d, 'name') }}</span>
                        <span
                            class="font-mono num"
                            :class="d.qty ? 'text-ink-2' : 'text-danger'"
                            >{{ qtyFmt(d.qty) }}</span
                        >
                    </div>
                    <p
                        v-if="!dishes.rare.length"
                        class="py-6 text-center text-sm text-ink-3"
                    >
                        {{ t('reports.no_data') }}
                    </p>
                </section>
                <p class="text-xs text-ink-3 xl:col-span-2">
                    {{ t('reports.cost_note') }}
                </p>
            </div>

            <!-- Сотрудники -->
            <template v-else>
                <section class="rounded-lg border border-line bg-surface p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        {{ t('reports.revenue_by_waiter') }}
                    </h2>
                    <HBars
                        v-if="staffRows.length"
                        :rows="staffRows"
                        :format="moneyFull"
                        :title="t('reports.revenue_by_waiter')"
                    />
                    <p v-else class="py-6 text-center text-sm text-ink-3">
                        {{ t('reports.no_data') }}
                    </p>
                </section>
                <div
                    class="overflow-x-auto rounded-lg border border-line bg-surface"
                >
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                        >
                            <tr>
                                <th class="px-4 py-2.5">
                                    {{ t('reports.waiter') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.bills') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.revenue') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.avg_check') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.avg_rating') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.removed') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('reports.tips') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in staff"
                                :key="s.id"
                                class="h-10 border-t border-line"
                            >
                                <td class="px-4 font-medium">{{ s.name }}</td>
                                <td class="px-4 text-right font-mono num">
                                    {{ s.bills }}
                                </td>
                                <td class="px-4 text-right font-mono num">
                                    {{ money(s.revenue) }}
                                </td>
                                <td class="px-4 text-right font-mono num">
                                    {{ money(s.avg_check) }}
                                </td>
                                <td class="px-4 text-right font-mono num">
                                    {{ rating(s.avg_rating) }}
                                    <span
                                        v-if="s.reviews"
                                        class="text-xs text-ink-3"
                                        >({{ s.reviews }})</span
                                    >
                                </td>
                                <td
                                    class="px-4 text-right font-mono num"
                                    :class="s.removed ? 'text-danger' : ''"
                                >
                                    {{ s.removed }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono text-ink-2 num"
                                >
                                    {{ money(s.tips) }}
                                </td>
                            </tr>
                            <tr v-if="!staff.length">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-ink-3"
                                >
                                    {{ t('reports.no_data') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>

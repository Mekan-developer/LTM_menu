<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    today: { type: Object, required: true },
    series: { type: Array, default: () => [] },
    top_dishes: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();

const delta = computed(() => {
    const prev = props.today.revenue_last_week;
    if (!prev) return null;
    return ((props.today.revenue - prev) / prev) * 100;
});

const kpis = computed(() => [
    {
        label: t('dash.revenue'),
        value: money(props.today.revenue),
        unit: 'TMT',
    },
    { label: t('dash.bills'), value: String(props.today.bills) },
    {
        label: t('dash.avg_check'),
        value: money(props.today.avg_check),
        unit: 'TMT',
    },
    {
        label: t('dash.tables'),
        value: String(props.today.open_tables),
        unit: `/ ${props.today.tables}`,
        bar: props.today.tables
            ? props.today.open_tables / props.today.tables
            : 0,
    },
]);

// График 7 дней: линия текущей недели + пунктир прошлой.
const W = 700;
const H = 200;
const max = computed(() =>
    Math.max(1, ...props.series.flatMap((d) => [d.revenue, d.previous])),
);
const points = (key) =>
    props.series
        .map(
            (d, i) =>
                `${40 + i * ((W - 80) / Math.max(1, props.series.length - 1))},${(H - 20 - (d[key] / max.value) * (H - 40)).toFixed(1)}`,
        )
        .join(' ');
// День недели по времени Ашхабада; подписи — из словаря (Intl в браузерах не знает туркменский).
const WEEK = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const weekday = (iso) => {
    const en = new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
        timeZone: 'Asia/Ashgabat',
    }).format(new Date(iso));
    return String(t('dash.weekdays')[WEEK.indexOf(en)] ?? en).toUpperCase();
};
</script>

<template>
    <AdminLayout :title="t('nav.dashboard')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('dash.today') }}
                </h1>
                <span
                    v-if="delta !== null"
                    class="font-mono text-sm"
                    :class="delta >= 0 ? 'text-ok' : 'text-danger'"
                >
                    {{ delta >= 0 ? '▲' : '▼' }}
                    {{ Math.abs(delta).toFixed(1).replace('.', ',') }} %
                    {{ t('dash.vs_last_week') }}
                </span>
            </div>

            <div
                class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-line bg-line lg:grid-cols-4"
            >
                <div
                    v-for="k in kpis"
                    :key="k.label"
                    class="flex flex-col gap-2 bg-surface px-[18px] py-4"
                >
                    <span class="text-[13px] text-ink-2">{{ k.label }}</span>
                    <span
                        class="font-mono text-[28px] leading-8 font-medium num"
                        >{{ k.value
                        }}<span v-if="k.unit" class="text-sm text-ink-3">
                            {{ k.unit }}</span
                        ></span
                    >
                    <div
                        v-if="k.bar !== undefined"
                        class="h-1.5 overflow-hidden rounded-[1px] bg-surface-2"
                    >
                        <div
                            class="h-1.5 bg-brand"
                            :style="{ width: `${Math.round(k.bar * 100)}%` }"
                        />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-5">
                <section
                    class="min-w-0 flex-[999_1_520px] rounded-lg border border-line bg-surface p-5"
                >
                    <div
                        class="mb-3 flex flex-wrap items-baseline justify-between gap-2"
                    >
                        <h2 class="text-lg font-semibold">
                            {{ t('dash.revenue_7') }}
                        </h2>
                        <div class="flex gap-4 text-xs text-ink-2">
                            <span class="flex items-center gap-1.5"
                                ><span class="h-0.5 w-4 bg-brand" />{{
                                    t('dash.this_week')
                                }}</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="w-4 border-t-2 border-dashed border-ink-3"
                                />{{ t('dash.last_week') }}</span
                            >
                        </div>
                    </div>
                    <svg
                        :viewBox="`0 0 ${W} ${H}`"
                        class="h-[220px] w-full"
                        role="img"
                        :aria-label="t('dash.revenue_7')"
                    >
                        <line
                            v-for="y in [20, 80, 140]"
                            :key="y"
                            x1="0"
                            :y1="y"
                            :x2="W"
                            :y2="y"
                            stroke="var(--color-line)"
                        />
                        <line
                            x1="0"
                            :y1="H - 20"
                            :x2="W"
                            :y2="H - 20"
                            stroke="var(--color-ink-3)"
                        />
                        <polyline
                            :points="points('previous')"
                            fill="none"
                            stroke="var(--color-ink-3)"
                            stroke-width="2"
                            stroke-dasharray="5 5"
                        />
                        <polyline
                            :points="points('revenue')"
                            fill="none"
                            stroke="var(--color-brand)"
                            stroke-width="2.5"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        <div
                            v-for="d in series"
                            :key="d.date"
                            class="flex flex-col"
                        >
                            <span class="font-mono text-[11px] text-ink-3">{{
                                weekday(d.date)
                            }}</span>
                            <span class="font-mono text-xs num">{{
                                money(d.revenue).split(',')[0]
                            }}</span>
                        </div>
                    </div>
                    <div
                        class="mt-4 grid grid-cols-2 gap-4 border-t border-line pt-4 text-sm"
                    >
                        <div class="flex justify-between">
                            <span class="text-ink-2">{{ t('pay.cash') }}</span
                            ><span class="font-mono num">{{
                                money(today.cash)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-ink-2">{{ t('pay.card') }}</span
                            ><span class="font-mono num">{{
                                money(today.card)
                            }}</span>
                        </div>
                    </div>
                </section>

                <aside
                    class="min-w-0 flex-[1_1_300px] rounded-lg border border-line bg-surface"
                >
                    <h2
                        class="border-b border-line px-5 py-4 text-lg font-semibold"
                    >
                        {{ t('dash.top_dishes') }}
                    </h2>
                    <ol>
                        <li
                            v-for="(d, i) in top_dishes"
                            :key="i"
                            class="grid grid-cols-[24px_1fr_auto] items-center gap-3 border-b border-line px-5 py-3 last:border-b-0"
                        >
                            <span class="font-mono text-xs text-ink-3">{{
                                i + 1
                            }}</span>
                            <span class="text-sm font-medium">{{
                                loc(d, 'name')
                            }}</span>
                            <span class="text-right font-mono text-sm num"
                                >{{ d.qty }}×<span class="ml-2 text-ink-3">{{
                                    money(d.amount)
                                }}</span></span
                            >
                        </li>
                        <li
                            v-if="!top_dishes.length"
                            class="px-5 py-6 text-sm text-ink-3"
                        >
                            {{ t('dash.no_sales') }}
                        </li>
                    </ol>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import { useI18n } from '@/i18n';
import { date, time } from '@/utils/format';

defineProps({
    upcoming: { type: Array, default: () => [] },
    month: { type: Object, default: null },
    recent: { type: Array, default: () => [] },
    onShift: { type: Boolean, default: false },
});

const { t } = useI18n();
const hm = (min) =>
    `${Math.floor(min / 60)}:${String(min % 60).padStart(2, '0')}`;
const weekday = (iso) =>
    new Date(iso).toLocaleDateString('ru-RU', {
        weekday: 'short',
        timeZone: 'Asia/Ashgabat',
    });
</script>

<template>
    <AdminLayout :title="t('nav.my_shifts')">
        <div class="mx-auto flex max-w-3xl flex-col gap-5">
            <div class="flex flex-wrap items-center gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('nav.my_shifts') }}
                </h1>
                <Badge v-if="onShift" tone="ok">{{
                    t('shifts.on_shift')
                }}</Badge>
            </div>
            <p class="text-sm text-ink-2">{{ t('shifts.mine_hint') }}</p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="k in [
                        { key: 'count', value: month?.shifts ?? 0 },
                        {
                            key: 'planned_h',
                            value: hm(month?.planned_minutes ?? 0),
                        },
                        {
                            key: 'worked_h',
                            value: hm(month?.worked_minutes ?? 0),
                        },
                        { key: 'late', value: month?.late ?? 0 },
                    ]"
                    :key="k.key"
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t(`shifts.${k.key}`)
                    }}</span>
                    <span
                        class="font-mono text-[24px] leading-8 font-semibold"
                        >{{ k.value }}</span
                    >
                </div>
            </div>
            <p class="-mt-3 text-xs text-ink-3">{{ t('shifts.this_month') }}</p>

            <section
                class="overflow-hidden rounded-lg border border-line bg-surface"
            >
                <h2
                    class="border-b border-line px-4 py-3 text-base font-semibold"
                >
                    {{ t('shifts.upcoming') }}
                </h2>
                <div
                    v-for="s in upcoming"
                    :key="s.id"
                    class="flex items-center justify-between gap-3 border-t border-line px-4 py-2.5 first:border-t-0"
                >
                    <span
                        ><span class="capitalize text-ink-2">{{
                            weekday(s.starts_at)
                        }}</span>
                        {{ date(s.starts_at) }}</span
                    >
                    <span class="font-mono font-semibold"
                        >{{ time(s.starts_at) }}–{{ time(s.ends_at) }}</span
                    >
                </div>
                <p
                    v-if="!upcoming.length"
                    class="px-4 py-8 text-center text-sm text-ink-3"
                >
                    {{ t('shifts.no_upcoming') }}
                </p>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-line bg-surface"
            >
                <h2
                    class="border-b border-line px-4 py-3 text-base font-semibold"
                >
                    {{ t('shifts.recent_marks') }}
                </h2>
                <div
                    v-for="a in recent"
                    :key="a.id"
                    class="flex items-center justify-between gap-3 border-t border-line px-4 py-2.5 text-sm first:border-t-0"
                >
                    <span>{{ date(a.clock_in) }}</span>
                    <span class="font-mono"
                        >{{ time(a.clock_in) }}–{{
                            a.clock_out ? time(a.clock_out) : '…'
                        }}
                        <span class="text-ink-3"
                            >· {{ hm(a.minutes) }}</span
                        ></span
                    >
                </div>
                <p
                    v-if="!recent.length"
                    class="px-4 py-8 text-center text-sm text-ink-3"
                >
                    {{ t('shifts.no_marks') }}
                </p>
            </section>
        </div>
    </AdminLayout>
</template>

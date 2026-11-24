<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import { useI18n } from '@/i18n';
import { dateTime, money } from '@/utils/format';

const props = defineProps({
    filter: { type: Object, required: true },
    stats: { type: Object, required: true },
    reviews: { type: Object, required: true },
});

const { t } = useI18n();

const setLow = (low) =>
    router.get(route('reviews.index'), low ? { low: 1 } : {}, {
        preserveState: true,
    });
const pageUrl = (n) =>
    route('reviews.index', {
        ...(props.filter.low ? { low: 1 } : {}),
        page: n,
    });

// Распределение оценок 5 → 1, ширина полосы — доля от максимума.
const bars = computed(() => {
    const d = props.stats.distribution ?? {};
    const max = Math.max(1, ...Object.values(d));
    return [5, 4, 3, 2, 1].map((r) => ({
        rating: r,
        n: d[r] ?? 0,
        width: `${((d[r] ?? 0) / max) * 100}%`,
    }));
});
const avg = computed(() =>
    props.stats.avg === null
        ? '—'
        : Number(props.stats.avg).toFixed(1).replace('.', ','),
);
</script>

<template>
    <AdminLayout :title="t('nav.reviews')">
        <div class="flex flex-col gap-5">
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ t('nav.reviews') }}
            </h1>

            <section
                class="grid gap-5 rounded-lg border border-line bg-surface p-5 sm:grid-cols-[auto_1fr]"
            >
                <div class="flex flex-col gap-1 sm:pr-6">
                    <span class="text-[13px] text-ink-2">{{
                        t('reviews.avg')
                    }}</span>
                    <span
                        class="font-mono text-[40px] leading-[44px] font-semibold num"
                        >{{ avg
                        }}<span class="text-lg text-ink-3"> / 5</span></span
                    >
                    <span class="text-[13px] text-ink-3">{{
                        t('reviews.count', { n: stats.count })
                    }}</span>
                </div>
                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="b in bars"
                        :key="b.rating"
                        class="grid grid-cols-[28px_1fr_40px] items-center gap-2.5 text-[13px]"
                    >
                        <span class="font-mono text-ink-2"
                            >{{ b.rating }}★</span
                        >
                        <span
                            class="h-2.5 overflow-hidden rounded-full bg-surface-2"
                            ><span
                                class="block h-full rounded-full"
                                :class="
                                    b.rating <= 3 ? 'bg-accent' : 'bg-brand'
                                "
                                :style="{ width: b.width }"
                        /></span>
                        <span class="text-right font-mono text-ink-2 num">{{
                            b.n
                        }}</span>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="h-8 rounded-sm border px-3 text-[13px]"
                    :class="
                        !filter.low
                            ? 'border-ink bg-ink text-paper'
                            : 'border-line hover:bg-surface-2'
                    "
                    @click="setLow(false)"
                >
                    {{ t('common.all') }}
                </button>
                <button
                    type="button"
                    class="h-8 rounded-sm border px-3 text-[13px]"
                    :class="
                        filter.low
                            ? 'border-ink bg-ink text-paper'
                            : 'border-line hover:bg-surface-2'
                    "
                    @click="setLow(true)"
                >
                    {{ t('reviews.low') }}
                </button>
            </div>

            <div class="flex flex-col gap-3">
                <article
                    v-for="r in reviews.data"
                    :key="r.id"
                    class="flex flex-col gap-2 rounded-lg border bg-surface p-4"
                    :class="r.rating <= 3 ? 'border-accent/50' : 'border-line'"
                >
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span
                            class="flex gap-0.5"
                            :aria-label="`${r.rating} / 5`"
                        >
                            <svg
                                v-for="n in 5"
                                :key="n"
                                class="size-4"
                                :class="
                                    n <= r.rating
                                        ? r.rating <= 3
                                            ? 'text-accent'
                                            : 'text-ochre'
                                        : 'text-line'
                                "
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"
                                />
                            </svg>
                        </span>
                        <Badge v-if="r.is_new" tone="danger">{{
                            t('reviews.new')
                        }}</Badge>
                        <span class="ml-auto font-mono text-xs text-ink-3">{{
                            dateTime(r.submitted_at)
                        }}</span>
                    </div>
                    <p v-if="r.comment" class="text-[15px] leading-[22px]">
                        «{{ r.comment }}»
                    </p>
                    <p v-else class="text-sm text-ink-3">
                        {{ t('reviews.no_comment') }}
                    </p>
                    <span class="text-[13px] text-ink-2"
                        >{{ t('tables.table') }} {{ r.table ?? '—' }} · #{{
                            r.order_id
                        }}
                        · {{ money(r.total ?? 0, true) }} ·
                        {{ r.waiter ?? t('waiter.no_waiter') }}</span
                    >
                </article>
                <p
                    v-if="!reviews.data.length"
                    class="rounded-lg border border-dashed border-ink-3 bg-surface p-10 text-center text-sm text-ink-2"
                >
                    {{ t('reviews.empty') }}
                </p>
            </div>

            <div v-if="reviews.last_page > 1" class="flex justify-end gap-2">
                <Link
                    v-if="reviews.current_page > 1"
                    :href="pageUrl(reviews.current_page - 1)"
                    class="inline-flex h-9 items-center rounded-md border border-line px-3 text-sm"
                    >←</Link
                >
                <Link
                    v-if="reviews.current_page < reviews.last_page"
                    :href="pageUrl(reviews.current_page + 1)"
                    class="inline-flex h-9 items-center rounded-md border border-line px-3 text-sm"
                    >→</Link
                >
            </div>
        </div>
    </AdminLayout>
</template>

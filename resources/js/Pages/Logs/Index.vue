<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import { useI18n } from '@/i18n';
import { date, dateTime, money } from '@/utils/format';

defineProps({
    logs: { type: Array, default: () => [] },
    next: { type: String, default: null },
    prev: { type: String, default: null },
    filter: { type: String, default: '' },
});

const { t } = useI18n();
const actions = [
    'item_removed',
    'qr_rejected',
    'discount_applied',
    'payment_cancelled',
    'price_changed',
    'sale_changed',
    'product_price_changed',
    'recipe_changed',
    'table_transferred',
    'table_moved',
    'hall_layout_saved',
    'guests_exported',
    'booking_cancelled',
    'order_cancelled',
    'delivery_fee_changed',
    'payment_method_changed',
    'courier_cash_received',
    'stop_added',
    'stop_removed',
    'role_permissions_changed',
];
const setFilter = (a) =>
    router.get(route('logs.index'), a ? { action: a } : {}, {
        preserveState: true,
    });

// Акция в журнале: «45,00 (01.12–07.12)» или «—», если её сняли.
function saleText(sale) {
    if (!sale?.price) return '—';
    const period = [sale.from, sale.until].some(Boolean)
        ? ` (${sale.from ? date(sale.from) : '…'}–${sale.until ? date(sale.until) : '…'})`
        : '';
    return `${money(sale.price)}${period}`;
}

function details(log) {
    const p = log.payload ?? {};
    switch (log.action) {
        case 'item_removed':
            return `${p.name} ×${p.qty} · ${p.reason}`;
        case 'discount_applied':
            return `${p.percent} % · ${money(p.amount, true)}`;
        case 'payment_cancelled':
            return `${money(p.total, true)} · ${p.reason}`;
        case 'price_changed':
            return `${p.name}: ${money(p.old)} → ${money(p.new)}`;
        case 'product_price_changed':
            return `${p.name}: ${money(p.old)} → ${money(p.new)} / ${t(`costing.unit_${p.unit}`)}`;
        case 'recipe_changed':
            return p.name;
        case 'sale_changed':
            return `${p.name}: ${saleText(p.old)} → ${saleText(p.new)}`;
        case 'table_moved':
            return `${t('tables.table')} ${p.from} → ${p.to}`;
        default:
            return '';
    }
}
</script>

<template>
    <AdminLayout :title="t('nav.logs')">
        <div class="flex flex-col gap-5">
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ t('nav.logs') }}
            </h1>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="h-8 rounded-sm border px-3 text-[13px]"
                    :class="
                        !filter ? 'border-ink bg-ink text-paper' : 'border-line'
                    "
                    @click="setFilter(null)"
                >
                    {{ t('common.all') }}
                </button>
                <button
                    v-for="a in actions"
                    :key="a"
                    type="button"
                    class="h-8 rounded-sm border px-3 text-[13px]"
                    :class="
                        filter === a
                            ? 'border-ink bg-ink text-paper'
                            : 'border-line hover:bg-surface-2'
                    "
                    @click="setFilter(a)"
                >
                    {{ t(`log.${a}`) }}
                </button>
            </div>
            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[720px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">{{ t('log.when') }}</th>
                            <th class="px-4 py-2.5">{{ t('log.who') }}</th>
                            <th class="px-4 py-2.5">{{ t('log.what') }}</th>
                            <th class="px-4 py-2.5">{{ t('log.details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in logs"
                            :key="log.id"
                            class="h-11 border-t border-line"
                        >
                            <td class="px-4 font-mono text-xs text-ink-2">
                                {{ dateTime(log.created_at) }}
                            </td>
                            <td class="px-4">{{ log.user ?? '—' }}</td>
                            <td class="px-4">
                                <Badge
                                    :tone="
                                        [
                                            'payment_cancelled',
                                            'item_removed',
                                        ].includes(log.action)
                                            ? 'danger'
                                            : 'neutral'
                                    "
                                    >{{ t(`log.${log.action}`) }}</Badge
                                >
                            </td>
                            <td class="px-4 text-ink-2">{{ details(log) }}</td>
                        </tr>
                        <tr v-if="!logs.length">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-ink-3"
                            >
                                {{ t('log.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-2">
                <Link
                    v-if="prev"
                    :href="prev"
                    class="inline-flex h-9 items-center rounded-md border border-line px-3 text-sm"
                    >←</Link
                >
                <Link
                    v-if="next"
                    :href="next"
                    class="inline-flex h-9 items-center rounded-md border border-line px-3 text-sm"
                    >→</Link
                >
            </div>
        </div>
    </AdminLayout>
</template>

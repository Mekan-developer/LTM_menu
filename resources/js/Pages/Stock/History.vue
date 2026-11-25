<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Btn from '@/Components/Btn.vue';
import Icon from '@/Components/Icon.vue';
import Select from '@/Components/Select.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { dateTime, money } from '@/utils/format';
import { qty } from '@/utils/quantity';

const props = defineProps({
    period: { type: Object, required: true },
    filters: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    movements: { type: Object, required: true },
});

const { t, loc } = useI18n();
const unit = (u) => t(`costing.unit_${u}`);

const from = ref(props.period.from);
const to = ref(props.period.to);
watch(
    () => props.period,
    (p) => {
        from.value = p.from;
        to.value = p.to;
    },
);

function apply(change = {}) {
    const params = {
        from: from.value,
        to: to.value,
        type: props.filters.type,
        product: props.filters.product,
        ...change,
    };
    router.get(
        route('stock.history'),
        Object.fromEntries(
            Object.entries(params).filter(([, v]) => v !== null && v !== ''),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
const pageUrl = (n) =>
    route('stock.history', {
        ...Object.fromEntries(
            Object.entries({
                from: props.period.from,
                to: props.period.to,
                ...props.filters,
            }).filter(([, v]) => v !== null && v !== ''),
        ),
        page: n,
    });

const tone = (type) =>
    ({
        receipt: 'ok',
        sale: 'neutral',
        sale_return: 'brand',
        write_off: 'danger',
        inventory: 'ochre',
    })[type] ?? 'neutral';
const signed = (v) => (v > 0 ? '+' : '') + qty(v);

// Открыт один документ (после прихода, списания или инвентаризации).
const doc = computed(() =>
    props.filters.document && props.movements.data.length
        ? props.movements.data[0]
        : null,
);
const docTotal = computed(() =>
    props.movements.data.reduce((s, m) => s + m.amount, 0),
);
</script>

<template>
    <AdminLayout :title="t('stock.history')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <Btn
                        variant="ghost"
                        size="sm"
                        class="self-start"
                        :href="route('stock.index')"
                        ><Icon name="back" :size="16" />{{
                            t('nav.stock')
                        }}</Btn
                    >
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{
                            doc
                                ? `${t(`stock.type_${doc.type}`)} №${filters.document}`
                                : t('stock.history')
                        }}
                    </h1>
                </div>
            </div>

            <!-- Один документ: кто, когда, причина, итог -->
            <div
                v-if="doc"
                class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-line bg-surface p-4 text-sm"
            >
                <span
                    ><span class="text-ink-2">{{ t('stock.when') }}:</span>
                    {{ dateTime(doc.created_at) }}</span
                >
                <span v-if="doc.user"
                    ><span class="text-ink-2">{{ t('stock.who') }}:</span>
                    {{ doc.user }}</span
                >
                <span v-if="doc.reason"
                    ><span class="text-ink-2">{{ t('stock.reason') }}:</span>
                    {{ t(`stock.reason_${doc.reason}`) }}</span
                >
                <span v-if="doc.note" class="italic">«{{ doc.note }}»</span>
                <span class="ml-auto font-mono font-semibold num"
                    >{{ t('stock.total') }}:
                    <span
                        :class="
                            doc.type === 'inventory' && docTotal < 0
                                ? 'text-danger'
                                : ''
                        "
                        >{{ money(docTotal, true) }}</span
                    ></span
                >
                <Link
                    :href="route('stock.history')"
                    class="w-full text-ink-2 underline decoration-dotted underline-offset-2"
                    >{{ t('stock.all_history') }}</Link
                >
            </div>

            <!-- Фильтры -->
            <div
                v-if="!filters.document"
                class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface p-3"
            >
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
                <span class="mx-1 hidden h-6 w-px bg-line sm:block" />
                <div class="w-44">
                    <Select
                        :model-value="filters.type ?? ''"
                        size="sm"
                        :aria-label="t('stock.type')"
                        @update:model-value="apply({ type: $event || null })"
                    >
                        <option value="">{{ t('stock.all_types') }}</option>
                        <option v-for="ty in types" :key="ty" :value="ty">
                            {{ t(`stock.type_${ty}`) }}
                        </option>
                    </Select>
                </div>
                <div class="w-56">
                    <Select
                        :model-value="filters.product ?? ''"
                        size="sm"
                        :aria-label="t('costing.product')"
                        @update:model-value="apply({ product: $event || null })"
                    >
                        <option value="">{{ t('stock.all_products') }}</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">
                            {{ loc(p, 'name') }}
                        </option>
                    </Select>
                </div>
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[860px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">{{ t('stock.when') }}</th>
                            <th class="px-4 py-2.5">{{ t('stock.type') }}</th>
                            <th class="px-4 py-2.5">
                                {{ t('costing.product') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.quantity') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.line_cost') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.balance_after') }}
                            </th>
                            <th class="px-4 py-2.5">{{ t('stock.who') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="m in movements.data"
                            :key="m.id"
                            class="h-11 border-t border-line"
                        >
                            <td class="px-4 font-mono text-ink-2">
                                {{ dateTime(m.created_at) }}
                            </td>
                            <td class="px-4">
                                <span
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge :tone="tone(m.type)">{{
                                        t(`stock.type_${m.type}`)
                                    }}</Badge>
                                    <Link
                                        v-if="
                                            m.document_id && !filters.document
                                        "
                                        :href="
                                            route('stock.history', {
                                                document: m.document_id,
                                            })
                                        "
                                        class="font-mono text-xs text-ink-3 hover:underline"
                                        >№{{ m.document_id }}</Link
                                    >
                                    <span
                                        v-if="m.order_id"
                                        class="font-mono text-xs text-ink-3"
                                        >{{ t('orders.order') }} №{{
                                            m.order_id
                                        }}</span
                                    >
                                </span>
                            </td>
                            <td class="px-4">
                                {{ m.product ? loc(m.product, 'name') : '—' }}
                            </td>
                            <td
                                class="px-4 text-right font-mono num"
                                :class="
                                    m.quantity < 0 ? 'text-danger' : 'text-ok'
                                "
                            >
                                {{ signed(m.quantity) }}
                                <span class="text-xs text-ink-3">{{
                                    m.product ? unit(m.product.unit) : ''
                                }}</span>
                                <span
                                    v-if="m.type === 'inventory'"
                                    class="block text-[11px] text-ink-3"
                                    >{{
                                        t('stock.was_became', {
                                            a: qty(m.expected),
                                            b: qty(m.balance_after),
                                        })
                                    }}</span
                                >
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ money(m.amount) }}
                            </td>
                            <td
                                class="px-4 text-right font-mono num"
                                :class="
                                    m.balance_after < 0
                                        ? 'text-danger'
                                        : 'text-ink-2'
                                "
                            >
                                {{ qty(m.balance_after) }}
                            </td>
                            <td class="px-4 text-ink-2">{{ m.user ?? '—' }}</td>
                        </tr>
                        <tr v-if="!movements.data.length">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-ink-3"
                            >
                                {{ t('stock.no_movements') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="movements.last_page > 1"
                class="flex items-center justify-end gap-2 text-sm"
            >
                <Link
                    v-if="movements.current_page > 1"
                    :href="pageUrl(movements.current_page - 1)"
                    preserve-state
                    class="h-9 rounded-md border border-line bg-surface px-3 leading-9 hover:bg-surface-2"
                    >←</Link
                >
                <span class="font-mono text-ink-2"
                    >{{ movements.current_page }} /
                    {{ movements.last_page }}</span
                >
                <Link
                    v-if="movements.current_page < movements.last_page"
                    :href="pageUrl(movements.current_page + 1)"
                    preserve-state
                    class="h-9 rounded-md border border-line bg-surface px-3 leading-9 hover:bg-surface-2"
                    >→</Link
                >
            </div>
        </div>
    </AdminLayout>
</template>

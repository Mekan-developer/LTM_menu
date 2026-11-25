<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';
import { parseQty, qty } from '@/utils/quantity';

const props = defineProps({
    balances: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const { t, loc } = useI18n();
const unit = (u) => t(`costing.unit_${u}`);

// Фильтр: все / нужно закупить / в минусе + поиск.
const view = ref('all');
const search = ref('');
const shown = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.balances.filter(
        (b) =>
            (view.value === 'all' ||
                (view.value === 'to_buy' && b.to_buy > 0) ||
                (view.value === 'negative' && b.quantity < 0)) &&
            (!q || `${b.name_tm} ${b.name_ru}`.toLowerCase().includes(q)),
    );
});
const toBuy = computed(() => props.balances.filter((b) => b.to_buy > 0));

// Минимум — в маленьком окне.
const editing = ref(null);
const minInput = ref('');
const minError = ref(null);
function editMin(b) {
    editing.value = b;
    minInput.value = b.min ? qty(b.min).replace(/\s/g, '') : '';
    minError.value = null;
}
function saveMin() {
    const value = parseQty(minInput.value) ?? 0;
    if (value < 0) return;
    router.put(
        route('stock.minimum', editing.value.id),
        { min: value },
        {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
            onError: (e) => (minError.value = e.min),
        },
    );
}

const print = () => window.print();
const tiles = computed(() => [
    { key: 'value', value: money(props.summary.value), unit: 'TMT' },
    { key: 'to_buy', value: props.summary.to_buy, view: 'to_buy' },
    { key: 'negative', value: props.summary.negative, view: 'negative' },
]);
</script>

<template>
    <AdminLayout :title="t('nav.stock')">
        <div class="no-print flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.stock') }}
                    </h1>
                    <p class="mt-1 text-sm text-ink-2">
                        {{ t('stock.hint') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Btn :href="route('stock.create', 'receipt')"
                        ><Icon name="plus" />{{ t('stock.receipt') }}</Btn
                    >
                    <Btn
                        variant="secondary"
                        :href="route('stock.create', 'write_off')"
                        ><Icon name="minus" />{{ t('stock.write_off') }}</Btn
                    >
                    <Btn variant="secondary" :href="route('stock.inventory')"
                        ><Icon name="check" />{{ t('stock.inventory') }}</Btn
                    >
                    <Btn variant="ghost" :href="route('stock.history')"
                        ><Icon name="log" />{{ t('stock.history') }}</Btn
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <component
                    :is="k.view ? 'button' : 'div'"
                    v-for="k in tiles"
                    :key="k.key"
                    :type="k.view ? 'button' : undefined"
                    class="flex flex-col gap-1 rounded-lg border bg-surface p-4 text-left"
                    :class="[
                        k.view && view === k.view
                            ? 'border-ink'
                            : 'border-line',
                        k.view ? 'hover:bg-surface-2' : '',
                    ]"
                    @click="k.view && (view = view === k.view ? 'all' : k.view)"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t(`stock.kpi_${k.key}`)
                    }}</span>
                    <span
                        class="font-mono text-[26px] leading-8 font-semibold num"
                        :class="
                            k.key === 'negative' && k.value
                                ? 'text-danger'
                                : k.key === 'to_buy' && k.value
                                  ? 'text-ochre-ink'
                                  : ''
                        "
                        >{{ k.value }}
                        <span v-if="k.unit" class="text-sm text-ink-3">{{
                            k.unit
                        }}</span></span
                    >
                </component>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="flex overflow-hidden rounded-md border border-line bg-surface"
                    role="group"
                >
                    <button
                        v-for="v in ['all', 'to_buy', 'negative']"
                        :key="v"
                        type="button"
                        class="h-9 border-l border-line px-3 text-sm first:border-l-0"
                        :class="
                            view === v
                                ? 'bg-ink text-paper'
                                : 'hover:bg-surface-2'
                        "
                        :aria-pressed="view === v"
                        @click="view = v"
                    >
                        {{ t(`stock.view_${v}`) }}
                    </button>
                </div>
                <div class="w-full sm:w-72">
                    <TextInput
                        v-model="search"
                        type="search"
                        icon="search"
                        :placeholder="t('costing.search_product')"
                        :aria-label="t('costing.search_product')"
                    />
                </div>
                <div v-if="view === 'to_buy'" class="flex gap-2 sm:ml-auto">
                    <Btn variant="secondary" size="sm" @click="print"
                        ><Icon name="print" :size="16" />{{
                            t('tables.print')
                        }}</Btn
                    >
                    <a
                        :href="route('stock.to-buy.export')"
                        class="inline-flex h-8 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-surface-2"
                        ><Icon name="excel" :size="15" />Excel</a
                    >
                </div>
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[720px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">
                                {{ t('costing.product') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.balance') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.minimum') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.need') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.value') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="b in shown"
                            :key="b.id"
                            class="h-12 border-t border-line hover:bg-surface-2"
                            :class="b.quantity < 0 ? 'bg-danger-soft/40' : ''"
                        >
                            <td class="px-4">
                                <Link
                                    :href="
                                        route('stock.history', {
                                            product: b.id,
                                        })
                                    "
                                    class="font-medium hover:underline"
                                    >{{ loc(b, 'name') }}</Link
                                >
                            </td>
                            <td
                                class="px-4 text-right font-mono num"
                                :class="
                                    b.quantity < 0
                                        ? 'font-semibold text-danger'
                                        : ''
                                "
                            >
                                {{ qty(b.quantity) }}
                                <span class="text-xs text-ink-3">{{
                                    unit(b.unit)
                                }}</span>
                            </td>
                            <td class="px-4 text-right">
                                <button
                                    type="button"
                                    class="rounded-sm px-1.5 py-0.5 font-mono text-ink-2 underline decoration-dotted underline-offset-2 hover:bg-surface-2"
                                    :aria-label="t('stock.set_min')"
                                    @click="editMin(b)"
                                >
                                    {{ b.min ? qty(b.min) : '—' }}
                                </button>
                            </td>
                            <td class="px-4 text-right">
                                <Badge v-if="b.to_buy > 0" tone="ochre"
                                    >{{ qty(b.to_buy) }}
                                    {{ unit(b.unit) }}</Badge
                                >
                            </td>
                            <td
                                class="px-4 text-right font-mono text-ink-2 num"
                            >
                                {{ money(b.value) }}
                            </td>
                        </tr>
                        <tr v-if="!shown.length">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-ink-3"
                            >
                                {{
                                    balances.length
                                        ? t('menu.nothing_found')
                                        : t('stock.no_products')
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Печать «Нужно закупить» -->
        <div class="hidden text-[#1D2420] print:block">
            <h1 class="mb-3 text-2xl font-semibold">{{ t('stock.to_buy') }}</h1>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-black text-left">
                        <th class="py-1">{{ t('costing.product') }}</th>
                        <th class="py-1 text-right">
                            {{ t('stock.balance') }}
                        </th>
                        <th class="py-1 text-right">
                            {{ t('stock.minimum') }}
                        </th>
                        <th class="py-1 text-right">{{ t('stock.need') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in toBuy"
                        :key="b.id"
                        class="border-b border-gray-300"
                    >
                        <td class="py-1">{{ loc(b, 'name') }}</td>
                        <td class="py-1 text-right">
                            {{ qty(b.quantity) }} {{ unit(b.unit) }}
                        </td>
                        <td class="py-1 text-right">
                            {{ qty(b.min) }} {{ unit(b.unit) }}
                        </td>
                        <td class="py-1 text-right font-semibold">
                            {{ qty(b.to_buy) }} {{ unit(b.unit) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal
            :show="editing !== null"
            :title="
                editing ? `${t('stock.set_min')}: ${loc(editing, 'name')}` : ''
            "
            width="max-w-sm"
            @close="editing = null"
        >
            <form
                id="min-form"
                class="flex flex-col gap-3"
                @submit.prevent="saveMin"
            >
                <Field
                    :label="`${t('stock.minimum')}, ${editing ? unit(editing.unit) : ''}`"
                    for="s-min"
                    :error="minError"
                    :hint="t('stock.min_hint')"
                >
                    <TextInput
                        id="s-min"
                        v-model="minInput"
                        inputmode="decimal"
                        mono
                        autofocus
                    />
                </Field>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="editing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn type="submit" form="min-form">{{ t('common.save') }}</Btn>
            </template>
        </Modal>
    </AdminLayout>
</template>

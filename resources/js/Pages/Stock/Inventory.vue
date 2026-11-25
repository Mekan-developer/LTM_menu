<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import Icon from '@/Components/Icon.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';
import { parseQty, qty } from '@/utils/quantity';

/**
 * Инвентаризация (ТЗ 2.3): вводим фактический остаток, видим расхождение с расчётным
 * в количестве и TMT. Пустое поле — продукт не считали, его остаток не меняется.
 */
const props = defineProps({
    balances: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const unit = (u) => t(`costing.unit_${u}`);

const actual = reactive({}); // { [productId]: '12,5' }
const search = ref('');
const onlyCounted = ref(false);

const rows = computed(() =>
    props.balances.map((b) => {
        const a = parseQty(actual[b.id]);
        const counted = a !== null && a >= 0;
        const diff = counted ? Math.round((a - b.quantity) * 1000) / 1000 : 0;
        return { ...b, counted, diff, diffSum: Math.round(diff * b.price) };
    }),
);
const shown = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter(
        (r) =>
            (!onlyCounted.value || r.counted) &&
            (!q || `${r.name_tm} ${r.name_ru}`.toLowerCase().includes(q)),
    );
});
const counted = computed(() => rows.value.filter((r) => r.counted));
const shortage = computed(() =>
    counted.value.reduce((s, r) => s + Math.min(0, r.diffSum), 0),
);
const surplus = computed(() =>
    counted.value.reduce((s, r) => s + Math.max(0, r.diffSum), 0),
);

// Факт = расчёт: быстро отметить, что продукт сошёлся.
const fillExpected = (r) =>
    (actual[r.id] = qty(Math.max(0, r.quantity)).replace(/\s/g, ''));

const form = useForm({ counts: [], note: '' });
function submit() {
    if (!counted.value.length || form.processing) return;
    if (
        !confirm(
            t('stock.inventory_confirm', {
                n: counted.value.length,
                s: money(shortage.value, true),
                p: money(surplus.value, true),
            }),
        )
    )
        return;
    form.transform((d) => ({
        ...d,
        counts: counted.value.map((r) => ({
            product_id: r.id,
            actual: parseQty(actual[r.id]),
        })),
    })).post(route('stock.inventory.store'));
}
const firstError = computed(() => Object.values(form.errors)[0] ?? null);
</script>

<template>
    <AdminLayout :title="t('stock.inventory')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <Btn
                    variant="ghost"
                    size="sm"
                    class="self-start"
                    :href="route('stock.index')"
                    ><Icon name="back" :size="16" />{{ t('nav.stock') }}</Btn
                >
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('stock.inventory') }}
                </h1>
                <p class="text-sm text-ink-2">
                    {{ t('stock.inventory_hint') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t('stock.counted')
                    }}</span>
                    <span class="font-mono text-[26px] leading-8 font-semibold"
                        >{{ counted.length }}
                        <span class="text-sm text-ink-3"
                            >/ {{ balances.length }}</span
                        ></span
                    >
                </div>
                <div
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t('stock.shortage')
                    }}</span>
                    <span
                        class="font-mono text-[26px] leading-8 font-semibold num"
                        :class="shortage < 0 ? 'text-danger' : ''"
                        >{{ money(shortage) }}
                        <span class="text-sm text-ink-3">TMT</span></span
                    >
                </div>
                <div
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t('stock.surplus')
                    }}</span>
                    <span
                        class="font-mono text-[26px] leading-8 font-semibold num"
                        :class="surplus > 0 ? 'text-ok' : ''"
                        >{{ money(surplus) }}
                        <span class="text-sm text-ink-3">TMT</span></span
                    >
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-72">
                    <TextInput
                        v-model="search"
                        type="search"
                        icon="search"
                        :placeholder="t('costing.search_product')"
                        :aria-label="t('costing.search_product')"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="onlyCounted"
                        type="checkbox"
                        class="size-4 accent-[var(--color-brand)]"
                    />{{ t('stock.only_counted') }}</label
                >
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[760px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">
                                {{ t('costing.product') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.expected') }}
                            </th>
                            <th class="w-48 px-4 py-2.5">
                                {{ t('stock.actual') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.diff') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('stock.diff_sum') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in shown"
                            :key="r.id"
                            class="h-12 border-t border-line"
                            :class="r.counted ? '' : 'text-ink-2'"
                        >
                            <td class="px-4 font-medium">
                                {{ loc(r, 'name') }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                <button
                                    type="button"
                                    class="rounded-sm px-1 underline decoration-dotted underline-offset-2 hover:bg-surface-2"
                                    :title="t('stock.same_as_expected')"
                                    @click="fillExpected(r)"
                                >
                                    {{ qty(r.quantity) }}
                                </button>
                                <span class="text-xs text-ink-3">
                                    {{ unit(r.unit) }}</span
                                >
                            </td>
                            <td class="px-4 py-1.5">
                                <TextInput
                                    v-model="actual[r.id]"
                                    inputmode="decimal"
                                    size="sm"
                                    mono
                                    :prefix="unit(r.unit)"
                                    :aria-label="`${t('stock.actual')}: ${loc(r, 'name')}`"
                                />
                            </td>
                            <td
                                class="px-4 text-right font-mono num"
                                :class="
                                    r.diff < 0
                                        ? 'text-danger'
                                        : r.diff > 0
                                          ? 'text-ok'
                                          : 'text-ink-3'
                                "
                            >
                                {{
                                    r.counted
                                        ? (r.diff > 0 ? '+' : '') + qty(r.diff)
                                        : ''
                                }}
                            </td>
                            <td
                                class="px-4 text-right font-mono num"
                                :class="
                                    r.diffSum < 0
                                        ? 'font-semibold text-danger'
                                        : r.diffSum > 0
                                          ? 'text-ok'
                                          : 'text-ink-3'
                                "
                            >
                                {{
                                    r.counted && r.diff !== 0
                                        ? (r.diffSum > 0 ? '+' : '') +
                                          money(r.diffSum)
                                        : ''
                                }}
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

            <Field
                :label="t('stock.note')"
                for="i-note"
                :error="form.errors.note"
            >
                <TextInput
                    id="i-note"
                    v-model="form.note"
                    maxlength="255"
                    :placeholder="t('stock.inventory_note_ph')"
                />
            </Field>

            <p v-if="firstError" class="text-sm text-danger" role="alert">
                {{ firstError }}
            </p>

            <div class="flex justify-end gap-2">
                <Btn variant="ghost" size="lg" :href="route('stock.index')">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    size="lg"
                    class="min-w-48"
                    :disabled="!counted.length"
                    :loading="form.processing"
                    @click="submit"
                    >{{ t('stock.save_inventory') }}</Btn
                >
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Btn from '@/Components/Btn.vue';
import Icon from '@/Components/Icon.vue';
import Select from '@/Components/Select.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    dish: { type: Object, required: true },
    products: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const byId = computed(() =>
    Object.fromEntries(props.products.map((p) => [p.id, p])),
);
const sortedProducts = computed(() =>
    [...props.products].sort((a, b) =>
        loc(a, 'name').localeCompare(loc(b, 'name')),
    ),
);

// В рецепте кг и л вводят граммами и миллилитрами — так привычнее на кухне.
const small = (unit) => unit === 'kg' || unit === 'l';
const inputUnit = (p) =>
    p ? t(`costing.input_${p.unit}`) : t('costing.unit').toLowerCase();
const toInput = (qty, unit) =>
    small(unit)
        ? String(Math.round(qty * 1000))
        : String(qty).replace('.', ',');
const toQty = (amount, unit) => {
    const n = Number(String(amount ?? '').replace(',', '.'));
    if (!Number.isFinite(n) || n <= 0) return 0;
    return small(unit) ? n / 1000 : n;
};

let seq = 0;
const lines = ref(
    props.dish.ingredients.map((i) => ({
        key: ++seq,
        product_id: i.product_id,
        amount: toInput(i.quantity, byId.value[i.product_id]?.unit),
    })),
);
const resale = computed(() => props.dish.kind === 'resale');
if (resale.value && !lines.value.length)
    lines.value.push({ key: ++seq, product_id: null, amount: '1' });

const canAdd = computed(() => !resale.value || lines.value.length === 0);
const addLine = () =>
    lines.value.push({ key: ++seq, product_id: null, amount: '' });
const removeLine = (key) =>
    (lines.value = lines.value.filter((l) => l.key !== key));

const rows = computed(() =>
    lines.value.map((l) => {
        const p = byId.value[l.product_id] ?? null;
        const qty = p ? toQty(l.amount, p.unit) : 0;
        return {
            ...l,
            product: p,
            qty,
            cost: p ? Math.round(qty * p.price) : 0,
            invalid: !p || qty <= 0,
        };
    }),
);
const cost = computed(() => rows.value.reduce((s, r) => s + r.cost, 0));
const markup = computed(() =>
    cost.value > 0
        ? Math.round(((props.dish.price - cost.value) / cost.value) * 1000) / 10
        : null,
);
const share = computed(() =>
    props.dish.price > 0
        ? Math.round((cost.value / props.dish.price) * 1000) / 10
        : null,
);
const percent = (v) => (v === null ? '—' : `${String(v).replace('.', ',')} %`);
const qtyText = (r) =>
    r.product
        ? `${small(r.product.unit) ? Math.round(r.qty * 1000) : String(r.qty).replace('.', ',')} ${inputUnit(r.product)}`
        : '';

const used = computed(
    () => new Set(lines.value.map((l) => l.product_id).filter(Boolean)),
);
const hasInvalid = computed(() => rows.value.some((r) => r.invalid));
const dirty = ref(false);

const saving = ref(false);
const errors = ref({});
function save() {
    if (hasInvalid.value || saving.value) return;
    saving.value = true;
    router.put(
        route('costing.update', props.dish.id),
        {
            lines: rows.value.map((r) => ({
                product_id: r.product_id,
                quantity: Math.round(r.qty * 1000) / 1000,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => (dirty.value = false),
            onError: (e) => (errors.value = e),
            onFinish: () => (saving.value = false),
        },
    );
}
const firstError = computed(() => Object.values(errors.value)[0] ?? null);
const print = () => window.print();
</script>

<template>
    <AdminLayout :title="`${t('nav.costing')} · ${loc(dish, 'name')}`">
        <div class="no-print mx-auto flex max-w-4xl flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <Btn
                        variant="ghost"
                        size="sm"
                        class="self-start"
                        :href="route('costing.index')"
                        ><Icon name="back" :size="16" />{{
                            t('nav.costing')
                        }}</Btn
                    >
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ loc(dish, 'name') }}
                    </h1>
                    <span class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-ink-2">{{
                            dish.category ? loc(dish.category, 'name') : ''
                        }}</span>
                        <Badge
                            :tone="
                                dish.kind === 'resale' ? 'outline' : 'neutral'
                            "
                            >{{ t(`menu.kind_${dish.kind}`) }}</Badge
                        >
                    </span>
                </div>
                <div class="flex gap-2">
                    <Btn variant="secondary" @click="print"
                        ><Icon name="print" />{{ t('tables.print') }}</Btn
                    >
                    <a
                        :href="route('costing.export', { dish: dish.id })"
                        class="inline-flex h-9 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-surface-2"
                        ><Icon name="excel" :size="16" />Excel</a
                    >
                </div>
            </div>

            <!-- Итоги -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="k in [
                        { key: 'price', value: money(dish.price) },
                        { key: 'cost', value: money(cost) },
                        { key: 'markup', value: percent(markup) },
                        { key: 'share', value: percent(share) },
                    ]"
                    :key="k.key"
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{
                        t(`costing.${k.key}`)
                    }}</span>
                    <span
                        class="font-mono text-[24px] leading-8 font-semibold num"
                        :class="
                            k.key === 'markup' && markup !== null && markup < 0
                                ? 'text-danger'
                                : ''
                        "
                        >{{ k.value }}</span
                    >
                </div>
            </div>

            <!-- Состав -->
            <section
                class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h2 class="text-lg font-semibold">
                        {{
                            resale
                                ? t('costing.resale_title')
                                : t('costing.recipe_title')
                        }}
                    </h2>
                    <span class="text-xs text-ink-3">{{
                        resale
                            ? t('costing.resale_hint')
                            : t('costing.recipe_hint')
                    }}</span>
                </div>

                <div
                    v-for="r in rows"
                    :key="r.key"
                    class="grid grid-cols-[1fr_auto] items-center gap-2 border-b border-line pb-3 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_9rem_8rem_auto]"
                >
                    <Select
                        :model-value="r.product_id"
                        :placeholder="t('costing.pick_product')"
                        :aria-label="t('costing.product')"
                        :invalid="!r.product && dirty"
                        @update:model-value="
                            lines.find((l) => l.key === r.key).product_id =
                                $event ? Number($event) : null;
                            dirty = true;
                        "
                    >
                        <option
                            v-for="p in sortedProducts"
                            :key="p.id"
                            :value="p.id"
                            :disabled="used.has(p.id) && p.id !== r.product_id"
                        >
                            {{ loc(p, 'name') }} · {{ money(p.price) }}/{{
                                t(`costing.unit_${p.unit}`)
                            }}
                        </option>
                    </Select>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-md border border-line hover:bg-surface-2 sm:order-last"
                        :aria-label="t('common.delete')"
                        @click="
                            removeLine(r.key);
                            dirty = true;
                        "
                    >
                        <Icon name="trash" :size="15" />
                    </button>
                    <TextInput
                        :model-value="lines.find((l) => l.key === r.key).amount"
                        inputmode="decimal"
                        mono
                        :prefix="inputUnit(r.product)"
                        :aria-label="t('costing.quantity')"
                        :invalid="r.product !== null && r.qty <= 0 && dirty"
                        @update:model-value="
                            lines.find((l) => l.key === r.key).amount = $event;
                            dirty = true;
                        "
                    />
                    <span
                        class="text-right font-mono text-sm num"
                        :title="t('costing.line_cost')"
                        >{{ money(r.cost) }}</span
                    >
                </div>

                <p
                    v-if="!rows.length"
                    class="py-4 text-center text-sm text-ink-3"
                >
                    {{ t('costing.empty_recipe') }}
                </p>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Btn
                        v-if="canAdd"
                        variant="secondary"
                        size="sm"
                        @click="addLine"
                        ><Icon name="plus" :size="16" />{{
                            t('costing.add_line')
                        }}</Btn
                    >
                    <a
                        :href="route('costing.products.index')"
                        target="_blank"
                        class="text-sm text-ink-2 underline decoration-dotted underline-offset-2"
                        >{{ t('costing.no_product_link') }}</a
                    >
                </div>
            </section>

            <p v-if="firstError" class="text-sm text-danger" role="alert">
                {{ firstError }}
            </p>
            <p v-else-if="hasInvalid && dirty" class="text-sm text-danger">
                {{ t('costing.fill_lines') }}
            </p>

            <div class="flex justify-end gap-2">
                <Btn variant="ghost" size="lg" :href="route('costing.index')">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    size="lg"
                    class="min-w-36"
                    :loading="saving"
                    :disabled="hasInvalid"
                    @click="save"
                    >{{ t('common.save') }}</Btn
                >
            </div>
        </div>

        <!-- Печать: карточка калькуляции без кнопок -->
        <div class="hidden text-[#1D2420] print:block">
            <h1 class="text-2xl font-semibold">{{ loc(dish, 'name') }}</h1>
            <p class="mb-4 text-sm">
                {{ dish.category ? loc(dish.category, 'name') : '' }} ·
                {{ t(`menu.kind_${dish.kind}`) }}
            </p>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-black text-left">
                        <th class="py-1">{{ t('costing.product') }}</th>
                        <th class="py-1 text-right">
                            {{ t('costing.quantity') }}
                        </th>
                        <th class="py-1 text-right">
                            {{ t('costing.unit_price') }}
                        </th>
                        <th class="py-1 text-right">
                            {{ t('costing.line_cost') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in rows.filter((x) => x.product)"
                        :key="r.key"
                        class="border-b border-gray-300"
                    >
                        <td class="py-1">{{ loc(r.product, 'name') }}</td>
                        <td class="py-1 text-right">{{ qtyText(r) }}</td>
                        <td class="py-1 text-right">
                            {{ money(r.product.price) }}/{{
                                t(`costing.unit_${r.product.unit}`)
                            }}
                        </td>
                        <td class="py-1 text-right">{{ money(r.cost) }}</td>
                    </tr>
                </tbody>
            </table>
            <dl class="mt-4 grid grid-cols-[1fr_auto] gap-1 text-sm">
                <dt>{{ t('costing.cost') }}</dt>
                <dd class="text-right font-semibold">
                    {{ money(cost, true) }}
                </dd>
                <dt>{{ t('costing.price') }}</dt>
                <dd class="text-right">{{ money(dish.price, true) }}</dd>
                <dt>{{ t('costing.markup') }}</dt>
                <dd class="text-right">{{ percent(markup) }}</dd>
                <dt>{{ t('costing.share') }}</dt>
                <dd class="text-right">{{ percent(share) }}</dd>
            </dl>
        </div>
    </AdminLayout>
</template>

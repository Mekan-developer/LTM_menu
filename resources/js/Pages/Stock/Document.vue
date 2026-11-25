<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import Icon from '@/Components/Icon.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import Select from '@/Components/Select.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money, toMinor } from '@/utils/format';
import { parseQty, qty } from '@/utils/quantity';

/** Приход (receipt) или ручное списание (write_off): строки «продукт — количество (— цена)». */
const props = defineProps({
    type: { type: String, required: true },
    products: { type: Array, default: () => [] },
    reasons: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const receipt = computed(() => props.type === 'receipt');
const byId = computed(() =>
    Object.fromEntries(props.products.map((p) => [p.id, p])),
);
const unit = (u) => t(`costing.unit_${u}`);
const plain = (minor) => money(minor).replace(/\s/g, '');

let seq = 0;
const lines = ref([{ key: ++seq, product_id: null, quantity: '', price: '' }]);
const addLine = () =>
    lines.value.push({ key: ++seq, product_id: null, quantity: '', price: '' });
const removeLine = (key) =>
    (lines.value = lines.value.filter((l) => l.key !== key));

// Выбрали продукт — в приход подставляем его текущую цену закупки.
function pick(line, id) {
    line.product_id = id ? Number(id) : null;
    if (receipt.value && line.product_id && !line.price)
        line.price = plain(byId.value[line.product_id]?.price ?? 0);
}

const rows = computed(() =>
    lines.value.map((l) => {
        const p = byId.value[l.product_id] ?? null;
        const q = parseQty(l.quantity) ?? 0;
        const price = receipt.value ? toMinor(l.price || '0') : (p?.price ?? 0);
        return {
            line: l,
            product: p,
            q,
            sum: Math.round(q * price),
            after: p ? p.quantity + (receipt.value ? q : -q) : null,
            invalid: !p || q <= 0,
        };
    }),
);
const total = computed(() => rows.value.reduce((s, r) => s + r.sum, 0));
const used = computed(
    () => new Set(lines.value.map((l) => l.product_id).filter(Boolean)),
);
const sorted = computed(() =>
    [...props.products].sort((a, b) =>
        loc(a, 'name').localeCompare(loc(b, 'name')),
    ),
);

const form = useForm({ lines: [], reason: 'spoiled', note: '' });
const tried = ref(false);
const ready = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((r) => !r.invalid) &&
        (receipt.value || form.reason !== 'other' || form.note.trim()),
);

function submit() {
    tried.value = true;
    if (!ready.value || form.processing) return;
    form.transform((d) => ({
        ...d,
        reason: receipt.value ? null : d.reason,
        lines: rows.value.map((r) => ({
            product_id: r.line.product_id,
            quantity: r.q,
            ...(receipt.value ? { price: r.line.price || '0' } : {}),
        })),
    })).post(
        route(
            receipt.value ? 'stock.receipts.store' : 'stock.write-offs.store',
        ),
    );
}
const firstError = computed(() => Object.values(form.errors)[0] ?? null);
</script>

<template>
    <AdminLayout :title="t(`stock.${type}`)">
        <form
            class="mx-auto flex max-w-4xl flex-col gap-5"
            @submit.prevent="submit"
        >
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
                    {{ t(`stock.${type}`) }}
                </h1>
                <p class="text-sm text-ink-2">
                    {{ t(`stock.${type}_hint`) }}
                </p>
            </div>

            <!-- Причина списания -->
            <section
                v-if="!receipt"
                class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
            >
                <Field :label="t('stock.reason')" :error="form.errors.reason">
                    <div class="flex flex-wrap gap-2" role="radiogroup">
                        <label
                            v-for="r in reasons"
                            :key="r"
                            class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border px-4 text-sm has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand"
                            :class="
                                form.reason === r
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line hover:bg-surface-2'
                            "
                        >
                            <input
                                v-model="form.reason"
                                type="radio"
                                name="reason"
                                :value="r"
                                class="sr-only"
                            />
                            {{ t(`stock.reason_${r}`) }}
                        </label>
                    </div>
                </Field>
            </section>

            <!-- Строки -->
            <section
                class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
            >
                <div
                    class="hidden gap-2 text-xs font-medium text-ink-2 sm:grid"
                    :class="
                        receipt
                            ? 'sm:grid-cols-[minmax(0,1fr)_9rem_9rem_7rem_2.25rem]'
                            : 'sm:grid-cols-[minmax(0,1fr)_9rem_7rem_2.25rem]'
                    "
                >
                    <span>{{ t('costing.product') }}</span>
                    <span>{{ t('costing.quantity') }}</span>
                    <span v-if="receipt">{{ t('stock.price_per_unit') }}</span>
                    <span class="text-right">{{ t('costing.line_cost') }}</span>
                    <span />
                </div>
                <div
                    v-for="r in rows"
                    :key="r.line.key"
                    class="grid grid-cols-[1fr_2.25rem] items-start gap-2 border-b border-line pb-3 last:border-b-0"
                    :class="
                        receipt
                            ? 'sm:grid-cols-[minmax(0,1fr)_9rem_9rem_7rem_2.25rem]'
                            : 'sm:grid-cols-[minmax(0,1fr)_9rem_7rem_2.25rem]'
                    "
                >
                    <div class="flex min-w-0 flex-col gap-1">
                        <Select
                            :model-value="r.line.product_id"
                            :placeholder="t('costing.pick_product')"
                            :aria-label="t('costing.product')"
                            :invalid="tried && !r.product"
                            @update:model-value="pick(r.line, $event)"
                        >
                            <option
                                v-for="p in sorted"
                                :key="p.id"
                                :value="p.id"
                                :disabled="
                                    used.has(p.id) && p.id !== r.line.product_id
                                "
                            >
                                {{ loc(p, 'name') }} · {{ qty(p.quantity) }}
                                {{ unit(p.unit) }}
                            </option>
                        </Select>
                        <span
                            v-if="r.product && r.q > 0"
                            class="font-mono text-xs"
                            :class="r.after < 0 ? 'text-danger' : 'text-ink-3'"
                            >{{ t('stock.after') }} {{ qty(r.after) }}
                            {{ unit(r.product.unit) }}</span
                        >
                    </div>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-md border border-line hover:bg-surface-2 sm:order-last"
                        :aria-label="t('common.delete')"
                        :disabled="rows.length === 1"
                        @click="removeLine(r.line.key)"
                    >
                        <Icon name="trash" :size="15" />
                    </button>
                    <TextInput
                        v-model="r.line.quantity"
                        inputmode="decimal"
                        mono
                        :prefix="r.product ? unit(r.product.unit) : ''"
                        :aria-label="t('costing.quantity')"
                        :invalid="tried && r.q <= 0"
                    />
                    <MoneyInput
                        v-if="receipt"
                        v-model="r.line.price"
                        :aria-label="t('stock.price_per_unit')"
                    />
                    <span
                        class="self-center text-right font-mono text-sm num"
                        >{{ money(r.sum) }}</span
                    >
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Btn variant="secondary" size="sm" @click="addLine"
                        ><Icon name="plus" :size="16" />{{
                            t('costing.add_line')
                        }}</Btn
                    >
                    <span class="text-sm"
                        >{{ t('stock.total') }}:
                        <b class="font-mono num">{{
                            money(total, true)
                        }}</b></span
                    >
                </div>
            </section>

            <Field
                :label="
                    receipt
                        ? t('stock.note_receipt')
                        : form.reason === 'other'
                          ? t('stock.note_required_label')
                          : t('stock.note')
                "
                for="s-note"
                :error="form.errors.note"
            >
                <TextInput
                    id="s-note"
                    v-model="form.note"
                    maxlength="255"
                    :invalid="
                        tried &&
                        !receipt &&
                        form.reason === 'other' &&
                        !form.note.trim()
                    "
                    :placeholder="
                        receipt
                            ? t('stock.note_receipt_ph')
                            : t('stock.note_ph')
                    "
                />
            </Field>

            <p v-if="firstError" class="text-sm text-danger" role="alert">
                {{ firstError }}
            </p>
            <p v-else-if="tried && !ready" class="text-sm text-danger">
                {{ t('costing.fill_lines') }}
            </p>

            <div class="flex justify-end gap-2">
                <Btn variant="ghost" size="lg" :href="route('stock.index')">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    size="lg"
                    class="min-w-40"
                    :loading="form.processing"
                    >{{ t(`stock.save_${type}`) }}</Btn
                >
            </div>
        </form>
    </AdminLayout>
</template>

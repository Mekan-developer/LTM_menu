<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import Select from '@/Components/Select.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    products: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const units = ['kg', 'l', 'pcs'];

const search = ref('');
const shown = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.products.filter((p) =>
              `${p.name_tm} ${p.name_ru}`.toLowerCase().includes(q),
          )
        : props.products;
});

const editing = ref(null); // null | 'new' | product
const plain = (minor) => money(minor).replace(/\s/g, '');
const form = useForm({ name_tm: '', name_ru: '', unit: 'kg', price: '' });

function open(product = null) {
    editing.value = product ?? 'new';
    form.clearErrors();
    form.name_tm = product?.name_tm ?? '';
    form.name_ru = product?.name_ru ?? '';
    form.unit = product?.unit ?? 'kg';
    form.price = product ? plain(product.price) : '';
}

function save() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value === 'new')
        form.post(route('costing.products.store'), opts);
    else form.put(route('costing.products.update', editing.value.id), opts);
}

function remove(product) {
    if (confirm(t('costing.confirm_delete', { name: loc(product, 'name') }))) {
        router.delete(route('costing.products.destroy', product.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AdminLayout :title="t('nav.products')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.products') }}
                    </h1>
                    <p class="mt-1 text-sm text-ink-2">
                        {{ t('costing.products_hint') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Btn variant="secondary" :href="route('costing.index')"
                        ><Icon name="chart" />{{ t('nav.costing') }}</Btn
                    >
                    <Btn @click="open()"
                        ><Icon name="plus" />{{ t('costing.add_product') }}</Btn
                    >
                </div>
            </div>

            <div class="w-full max-w-sm">
                <TextInput
                    v-model="search"
                    type="search"
                    icon="search"
                    :placeholder="t('costing.search_product')"
                    :aria-label="t('costing.search_product')"
                />
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[620px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">
                                {{ t('costing.product') }}
                            </th>
                            <th class="px-4 py-2.5">{{ t('costing.unit') }}</th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.unit_price') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.in_dishes') }}
                            </th>
                            <th class="w-24 px-4 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in shown"
                            :key="p.id"
                            class="group h-12 border-t border-line hover:bg-surface-2"
                        >
                            <td class="px-4">
                                <span class="font-medium">{{
                                    loc(p, 'name')
                                }}</span>
                            </td>
                            <td class="px-4 text-ink-2">
                                {{ t(`costing.unit_${p.unit}`) }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ money(p.price) }}
                                <span class="text-xs text-ink-3"
                                    >/ {{ t(`costing.unit_${p.unit}`) }}</span
                                >
                            </td>
                            <td class="px-4 text-right font-mono text-ink-2">
                                {{ p.dishes_count || '—' }}
                            </td>
                            <td class="px-4">
                                <div
                                    class="flex justify-end gap-1 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                >
                                    <button
                                        type="button"
                                        class="flex size-8 items-center justify-center rounded-md border border-line bg-surface hover:bg-paper"
                                        :aria-label="t('common.edit')"
                                        @click="open(p)"
                                    >
                                        <Icon name="edit" :size="15" />
                                    </button>
                                    <button
                                        type="button"
                                        class="flex size-8 items-center justify-center rounded-md border border-line bg-surface hover:bg-paper disabled:opacity-40"
                                        :aria-label="t('common.delete')"
                                        :disabled="p.dishes_count > 0"
                                        :title="
                                            p.dishes_count > 0
                                                ? t('costing.in_use_hint')
                                                : ''
                                        "
                                        @click="remove(p)"
                                    >
                                        <Icon name="trash" :size="15" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!shown.length">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-ink-3"
                            >
                                {{
                                    products.length
                                        ? t('menu.nothing_found')
                                        : t('costing.no_products')
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal
            :show="editing !== null"
            :title="
                editing === 'new'
                    ? t('costing.add_product')
                    : t('costing.edit_product')
            "
            @close="editing = null"
        >
            <form
                id="product-form"
                class="flex flex-col gap-4"
                @submit.prevent="save"
            >
                <Field
                    v-for="lang in ['tm', 'ru']"
                    :key="lang"
                    :label="t(`menu.name_${lang}`)"
                    :for="`p-${lang}`"
                    :error="form.errors[`name_${lang}`]"
                >
                    <TextInput
                        :id="`p-${lang}`"
                        v-model="form[`name_${lang}`]"
                        maxlength="120"
                        :placeholder="t('costing.product_ph')"
                        autocomplete="off"
                    />
                </Field>
                <div class="grid grid-cols-2 gap-4">
                    <Field
                        :label="t('costing.unit')"
                        for="p-unit"
                        :error="form.errors.unit"
                    >
                        <Select id="p-unit" v-model="form.unit">
                            <option v-for="u in units" :key="u" :value="u">
                                {{ t(`costing.unit_${u}_long`) }}
                            </option>
                        </Select>
                    </Field>
                    <Field
                        :label="
                            t('costing.price_per', {
                                u: t(`costing.unit_${form.unit}`),
                            })
                        "
                        for="p-price"
                        :error="form.errors.price"
                    >
                        <MoneyInput
                            id="p-price"
                            v-model="form.price"
                            :placeholder="t('menu.price_ph')"
                        />
                    </Field>
                </div>
                <p
                    v-if="editing !== 'new' && editing?.dishes_count"
                    class="text-xs text-ink-3"
                >
                    {{
                        t('costing.price_affects', {
                            n: editing.dishes_count,
                        })
                    }}
                </p>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="editing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="product-form"
                    :loading="form.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

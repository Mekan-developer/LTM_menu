<script setup>
// Вручение заказа (ТЗ 2.6): как гость заплатил на самом деле — наличные, карта
// (терминал курьера) или часть наличными, часть картой. Плюс чаевые курьеру.
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money, toMinor } from '@/utils/format';

const props = defineProps({
    delivery: { type: Object, default: null },
    routeName: { type: String, required: true }, // deliveries.complete | courier.complete
});
const emit = defineEmits(['close']);
const { t } = useI18n();

const form = useForm({
    method: 'cash',
    cash_amount: '',
    tip_amount: '',
    tip_method: 'cash',
});

// Предварительный способ — тот, что гость выбрал при заказе.
watch(
    () => props.delivery,
    (d) => {
        if (!d) return;
        form.reset();
        form.clearErrors();
        form.method = d.planned_method;
    },
);

const methods = ['cash', 'card', 'mixed'];
const cardRest = computed(() =>
    Math.max(0, (props.delivery?.total ?? 0) - toMinor(form.cash_amount)),
);

function submit() {
    form.transform((data) => ({
        ...data,
        cash_amount: data.method === 'mixed' ? data.cash_amount : null,
        tip_amount: data.tip_amount || null,
        tip_method: data.tip_amount ? data.tip_method : null,
    })).post(route(props.routeName, props.delivery.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal
        :show="delivery !== null"
        :title="
            delivery
                ? t('delivery.complete_title', { n: delivery.order_id })
                : ''
        "
        @close="emit('close')"
    >
        <form
            v-if="delivery"
            id="delivery-complete"
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <div class="flex items-baseline justify-between">
                <span class="text-sm text-ink-2">{{
                    t('delivery.total')
                }}</span>
                <span class="font-mono text-3xl font-semibold num">{{
                    money(delivery.total, true)
                }}</span>
            </div>

            <div class="flex flex-col gap-2">
                <span class="text-[13px] font-medium"
                    >{{ t('delivery.actual_method') }}
                    <span class="font-normal text-ink-3"
                        >· {{ t('delivery.planned') }}:
                        {{ t(`pay.${delivery.planned_method}`) }}</span
                    ></span
                >
                <div role="radiogroup" class="grid grid-cols-3 gap-2">
                    <button
                        v-for="m in methods"
                        :key="m"
                        type="button"
                        role="radio"
                        :aria-checked="form.method === m"
                        class="flex h-14 items-center justify-center rounded-md text-sm font-semibold"
                        :class="
                            form.method === m
                                ? 'border-2 border-brand bg-brand-soft'
                                : 'border border-line bg-paper'
                        "
                        @click="form.method = m"
                    >
                        {{ t(`pay.${m}`) }}
                    </button>
                </div>
                <span v-if="form.errors.method" class="text-xs text-danger">{{
                    form.errors.method
                }}</span>
            </div>

            <div v-if="form.method === 'mixed'" class="grid grid-cols-2 gap-3">
                <Field
                    :label="t('delivery.cash_part')"
                    for="dc-cash"
                    :error="form.errors.cash_amount"
                    ><TextInput
                        id="dc-cash"
                        v-model="form.cash_amount"
                        mono
                        size="lg"
                        inputmode="decimal"
                        placeholder="0,00"
                /></Field>
                <div class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium">{{
                        t('delivery.card_rest')
                    }}</span>
                    <span
                        class="flex h-11 items-center rounded-md bg-surface-2 px-3 font-mono text-lg num"
                        >{{ money(cardRest) }}</span
                    >
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 border-t border-line pt-4">
                <Field
                    :label="t('delivery.tip')"
                    for="dc-tip"
                    :error="form.errors.tip_amount"
                    ><TextInput
                        id="dc-tip"
                        v-model="form.tip_amount"
                        mono
                        inputmode="decimal"
                        placeholder="0,00"
                /></Field>
                <Field :label="t('cashier.tip_method')" for="dc-tm">
                    <select
                        id="dc-tm"
                        v-model="form.tip_method"
                        class="h-11 w-full rounded-md border border-line bg-paper px-3 text-sm"
                    >
                        <option value="cash">{{ t('pay.cash') }}</option>
                        <option value="card">{{ t('pay.card') }}</option>
                    </select>
                </Field>
            </div>
            <p
                v-if="form.errors.order || form.errors.status"
                class="text-sm text-danger"
                role="alert"
            >
                {{ form.errors.order || form.errors.status }}
            </p>
        </form>
        <template #footer>
            <Btn variant="ghost" @click="emit('close')">{{
                t('common.cancel')
            }}</Btn>
            <Btn
                type="submit"
                form="delivery-complete"
                :loading="form.processing"
                >{{ t('delivery.do_complete') }}</Btn
            >
        </template>
    </Modal>
</template>

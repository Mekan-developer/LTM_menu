<script setup>
import { computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { dateTime, money } from '@/utils/format';

const props = defineProps({
    order: { type: Object, required: true },
    restaurant: { type: String, default: '' },
});

const { t, loc } = useI18n();
const items = computed(() =>
    props.order.items.filter((i) =>
        ['sent', 'ready', 'served'].includes(i.status),
    ),
);
const paid = computed(() => props.order.payment);
const print = () => window.print();

onMounted(() => setTimeout(print, 300));
</script>

<template>
    <Head :title="t('cashier.print_receipt')" />
    <div class="min-h-screen bg-white py-6 text-black">
        <div class="no-print mx-auto mb-4 flex w-[80mm] gap-2">
            <button
                type="button"
                class="h-9 flex-1 rounded-md bg-brand text-sm font-semibold text-brand-ink"
                @click="print"
            >
                {{ t('tables.print') }}
            </button>
        </div>
        <!-- Чековая лента 80 мм -->
        <div
            class="mx-auto w-[80mm] px-[3mm] font-mono text-[12px] leading-[1.35]"
        >
            <p class="text-center text-[15px] font-semibold">
                {{ restaurant }}
            </p>
            <p class="text-center">
                {{ paid ? t('receipt.receipt') : t('receipt.bill') }} #{{
                    order.id
                }}
            </p>
            <p class="text-center">
                {{ t('tables.table') }} {{ order.table?.number }} ·
                {{ order.waiter?.name ?? '' }}
            </p>
            <p class="text-center">
                {{ dateTime(paid?.created_at ?? new Date().toISOString()) }}
            </p>
            <p class="my-1.5 border-t border-dashed border-black" />
            <div v-for="i in items" :key="i.id" class="mb-1">
                <p>{{ loc(i, 'name') }}</p>
                <p class="flex justify-between">
                    <span>{{ i.quantity }} × {{ money(i.price) }}</span
                    ><span>{{ money(i.total) }}</span>
                </p>
            </div>
            <p class="my-1.5 border-t border-dashed border-black" />
            <p class="flex justify-between">
                <span>{{ t('cashier.subtotal') }}</span
                ><span>{{ money(order.subtotal) }}</span>
            </p>
            <p v-if="order.discount_amount" class="flex justify-between">
                <span
                    >{{ t('cashier.discount') }}
                    {{ order.discount_percent }}%</span
                ><span>−{{ money(order.discount_amount) }}</span>
            </p>
            <p class="flex justify-between text-[15px] font-semibold">
                <span>{{ t('receipt.total') }}</span
                ><span>{{ money(order.total, true) }}</span>
            </p>
            <template v-if="paid">
                <p v-if="paid.cash_amount" class="flex justify-between">
                    <span>{{ t('pay.cash') }}</span
                    ><span>{{ money(paid.cash_amount) }}</span>
                </p>
                <p v-if="paid.card_amount" class="flex justify-between">
                    <span>{{ t('pay.card') }}</span
                    ><span>{{ money(paid.card_amount) }}</span>
                </p>
            </template>
            <p class="my-1.5 border-t border-dashed border-black" />
            <p class="text-center">{{ t('receipt.thanks') }}</p>
            <p class="mt-1 text-center text-[10px]">
                {{ t('receipt.not_fiscal') }}
            </p>
        </div>
    </div>
</template>

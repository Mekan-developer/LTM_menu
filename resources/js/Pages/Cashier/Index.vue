<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { money, time, toMinor } from '@/utils/format';
import { useRealtime } from '@/composables/useRealtime';
import MoneyInput from '@/Components/MoneyInput.vue';

const props = defineProps({
    queue: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    summary: { type: Object, required: true },
    recent: { type: Array, default: () => [] },
    rules: { type: Object, required: true },
    couriers: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();

useRealtime('cashier', 'cashier.changed', () =>
    router.reload({
        only: ['queue', 'summary', 'recent', 'couriers'],
        preserveScroll: true,
    }),
);

const billable = computed(() =>
    (props.selected?.items ?? []).filter((i) =>
        ['sent', 'ready', 'served'].includes(i.status),
    ),
);
const unsent = computed(
    () =>
        (props.selected?.items ?? []).filter((i) =>
            ['draft', 'pending'].includes(i.status),
        ).length,
);
const subtotal = computed(() =>
    billable.value.reduce((s, i) => s + i.total, 0),
);

const form = useForm({
    method: 'card',
    discount_percent: 0,
    cash_amount: '',
    bonus_amount: '',
    tip_amount: '',
    tip_method: 'cash',
    guest_phone: '',
    guest_name: '',
});
const received = ref('');

// Бонусы гостя (ТЗ 2.8): баланс по телефону; у счёта из доставки или брони гость уже известен.
const bonusRules = computed(() => props.rules.bonus ?? { enabled: false });
const guestInfo = ref(null); // { name, balance } | null
function prefillGuest() {
    const g = props.selected?.guest;
    form.guest_phone = g?.phone ?? '';
    guestInfo.value = g ? { name: g.name, balance: g.bonus_balance } : null;
}
prefillGuest();
watch(
    () => props.selected?.id,
    () => {
        form.reset();
        form.clearErrors();
        received.value = '';
        prefillGuest();
    },
);
let lookupTimer = null;
watch(
    () => form.guest_phone,
    (phone) => {
        clearTimeout(lookupTimer);
        if (!bonusRules.value.enabled) return;
        const digits = String(phone ?? '').replace(/\D/g, '');
        if (digits.length < 8) {
            guestInfo.value = null;
            form.bonus_amount = '';
            return;
        }
        lookupTimer = setTimeout(async () => {
            try {
                const { data } = await axios.get(route('cashier.guest'), {
                    params: { phone },
                });
                guestInfo.value = data;
                if (!data) form.bonus_amount = '';
            } catch {
                guestInfo.value = null;
            }
        }, 350);
    },
);
onUnmounted(() => clearTimeout(lookupTimer));

const discountAmount = computed(() =>
    Math.floor(
        (subtotal.value * (Number(form.discount_percent) || 0) + 50) / 100,
    ),
);
const total = computed(() =>
    Math.max(0, subtotal.value - discountAmount.value),
);
const overLimit = computed(
    () =>
        Number(form.discount_percent) > props.rules.discount_limit_k &&
        !props.rules.can_discount_unlimited,
);
// Списать можно не больше M % счёта и не больше баланса; скидка и бонусы не суммируются.
const maxBonus = computed(() =>
    guestInfo.value && bonusRules.value.enabled
        ? Math.max(
              0,
              Math.min(
                  guestInfo.value.balance,
                  Math.floor(
                      (total.value * bonusRules.value.max_spend_percent) / 100,
                  ),
              ),
          )
        : 0,
);
const bonus = computed(() => Math.min(toMinor(form.bonus_amount), total.value));
const bonusTooMuch = computed(
    () => toMinor(form.bonus_amount) > maxBonus.value,
);
const moneyDue = computed(() => Math.max(0, total.value - bonus.value));
const accrual = computed(() =>
    guestInfo.value && bonusRules.value.enabled
        ? Math.floor((moneyDue.value * bonusRules.value.accrual_percent) / 100)
        : 0,
);
const useMaxBonus = () =>
    (form.bonus_amount = money(maxBonus.value).replace(/\s/g, ''));

const change = computed(() =>
    Math.max(0, toMinor(received.value) - moneyDue.value),
);
const mixedCard = computed(() =>
    Math.max(0, moneyDue.value - toMinor(form.cash_amount)),
);

const pay = () =>
    form.post(route('cashier.pay', props.selected.id), {
        preserveScroll: true,
    });

const methods = ['cash', 'card', 'mixed'];
const lastPaid = computed(() => props.recent.find((p) => !p.cancelled_at));

// Отмена оплаты — с причиной.
const cancelling = ref(null);
const cancelForm = useForm({ reason: '' });
const cancelPayment = () =>
    cancelForm.post(route('cashier.cancel', cancelling.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = null;
            cancelForm.reset();
        },
    });

// Самовывоз в очереди кассы — номер заказа вместо стола (ТЗ 2.6).
const orderLabel = (o) =>
    o.channel === 'pickup'
        ? t('cashier.pickup', { n: o.id })
        : `${t('tables.table')} ${o.table?.number ?? '—'}`;

function receiveCash(c) {
    if (!window.confirm(`${c.name}: ${money(c.amount, true)}?`)) return;
    router.post(
        route('cashier.courier-cash', c.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout :title="t('nav.cashier')">
        <div class="flex flex-wrap gap-5">
            <!-- Очередь счетов -->
            <aside
                class="flex min-w-0 flex-[1_1_260px] flex-col overflow-hidden rounded-lg border border-line bg-surface"
            >
                <h1
                    class="border-b border-line px-4 py-3 text-xl font-semibold"
                >
                    {{ t('nav.cashier') }}
                </h1>
                <span
                    class="px-4 pt-3 pb-1 font-mono text-[10px] tracking-[0.08em] text-ink-3 uppercase"
                    >{{ t('cashier.to_pay') }}</span
                >
                <Link
                    v-for="o in queue"
                    :key="o.id"
                    :href="route('cashier.index', { order: o.id })"
                    preserve-scroll
                    class="grid grid-cols-[1fr_auto] gap-x-2 gap-y-0.5 border-l-[3px] px-4 py-2.5"
                    :class="
                        selected?.id === o.id
                            ? 'border-brand bg-brand-soft'
                            : 'border-transparent hover:bg-surface-2'
                    "
                >
                    <span class="font-semibold">{{ orderLabel(o) }}</span>
                    <span class="text-right font-mono num">{{
                        money(o.total)
                    }}</span>
                    <span class="text-xs text-ink-2"
                        >{{ o.waiter?.name ?? '—' }} ·
                        {{ time(o.opened_at) }}</span
                    >
                    <span
                        class="text-right font-mono text-[11px]"
                        :class="
                            o.status === 'bill_requested'
                                ? 'text-accent'
                                : 'text-ink-3'
                        "
                        >{{
                            o.status === 'bill_requested'
                                ? t('cashier.bill_requested')
                                : t('cashier.open')
                        }}</span
                    >
                </Link>
                <p v-if="!queue.length" class="px-4 py-6 text-sm text-ink-3">
                    {{ t('cashier.queue_empty') }}
                </p>

                <!-- Курьеры сдают наличные за доставку (ТЗ 2.6) -->
                <div
                    v-if="couriers.length"
                    class="flex flex-col gap-2 border-t border-line bg-ochre-soft px-4 py-3 text-[13px]"
                >
                    <span class="flex flex-col"
                        ><span class="font-semibold text-ochre-ink">{{
                            t('cashier.courier_cash')
                        }}</span
                        ><span class="text-[11px] text-ink-3">{{
                            t('cashier.courier_cash_hint')
                        }}</span></span
                    >
                    <div
                        v-for="c in couriers"
                        :key="c.id"
                        class="flex items-center gap-2"
                    >
                        <span class="min-w-0 flex-1 truncate"
                            >{{ c.name }}
                            <span class="text-[11px] text-ink-3">{{
                                t('cashier.courier_orders', { n: c.orders })
                            }}</span></span
                        >
                        <span class="font-mono font-semibold num">{{
                            money(c.amount)
                        }}</span>
                        <Btn
                            size="sm"
                            variant="secondary"
                            @click="receiveCash(c)"
                            >{{ t('cashier.receive_cash') }}</Btn
                        >
                    </div>
                </div>

                <div
                    class="mt-auto flex flex-col gap-1.5 border-t border-line px-4 py-3 text-[13px]"
                >
                    <span
                        class="font-mono text-[10px] tracking-[0.08em] text-ink-3 uppercase"
                        >{{ t('cashier.day_total') }}</span
                    >
                    <div class="flex justify-between">
                        <span class="text-ink-2">{{ t('pay.cash') }}</span
                        ><span class="font-mono num">{{
                            money(summary.cash)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-2">{{ t('pay.card') }}</span
                        ><span class="font-mono num">{{
                            money(summary.card)
                        }}</span>
                    </div>
                    <!-- Наличные за доставку, ещё не сданные курьерами (ТЗ 2.6) -->
                    <div
                        v-if="summary.courier_cash"
                        class="flex justify-between text-ochre-ink"
                    >
                        <span>{{ t('cashier.at_couriers') }}</span
                        ><span class="font-mono num">{{
                            money(summary.courier_cash)
                        }}</span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span>{{
                            t('cashier.bills', { n: summary.count })
                        }}</span
                        ><span class="font-mono num">{{
                            money(summary.total)
                        }}</span>
                    </div>
                    <div
                        v-if="summary.tips_cash || summary.tips_card"
                        class="mt-1 flex justify-between border-t border-dashed border-line pt-1.5 text-ink-2"
                    >
                        <span>{{ t('cashier.tips_day') }}</span
                        ><span class="text-right font-mono num"
                            >{{ money(summary.tips_cash) }} /
                            {{ money(summary.tips_card) }}</span
                        >
                    </div>
                </div>
            </aside>

            <!-- Счёт -->
            <main class="flex min-w-0 flex-[999_1_420px] flex-col gap-4">
                <div
                    v-if="lastPaid && $page.url.includes('paid=')"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ok bg-ok-soft px-4 py-3"
                >
                    <span class="font-semibold text-ok"
                        >{{ t('cashier.paid_ok') }} ·
                        <span class="font-mono">{{
                            money(lastPaid.total, true)
                        }}</span></span
                    >
                    <a
                        :href="route('cashier.receipt', lastPaid.order_id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-9 items-center gap-2 rounded-md border border-ok px-3 text-sm font-medium text-ok"
                        ><Icon name="print" :size="16" />{{
                            t('cashier.print_receipt')
                        }}</a
                    >
                </div>

                <template v-if="selected">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="font-mono text-xs text-ink-3"
                                >#{{ selected.id }} ·
                                {{ time(selected.opened_at) }} ·
                                {{ selected.waiter?.name ?? '—' }}</span
                            >
                            <h2
                                class="text-[28px] leading-[34px] font-semibold"
                            >
                                {{ orderLabel(selected) }}
                            </h2>
                        </div>
                        <a
                            :href="route('cashier.receipt', selected.id)"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-9 items-center gap-2 rounded-md border border-ink-2 px-3.5 text-sm hover:bg-surface-2"
                            ><Icon name="print" :size="16" />{{
                                t('cashier.print_bill')
                            }}</a
                        >
                    </div>

                    <p
                        v-if="unsent"
                        class="rounded-md border border-ochre bg-ochre-soft px-3 py-2 text-sm text-ochre-ink"
                    >
                        {{ t('cashier.unsent_warning', { n: unsent }) }}
                    </p>

                    <div
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <div
                            class="grid h-9 grid-cols-[44px_1fr_90px_110px] items-center gap-2.5 bg-surface-2 px-4 text-xs font-medium text-ink-2"
                        >
                            <span>{{ t('cashier.qty') }}</span
                            ><span>{{ t('cashier.item') }}</span
                            ><span class="text-right">{{
                                t('cashier.price')
                            }}</span
                            ><span class="text-right">{{
                                t('cashier.sum')
                            }}</span>
                        </div>
                        <div
                            v-for="i in billable"
                            :key="i.id"
                            class="grid min-h-[42px] grid-cols-[44px_1fr_90px_110px] items-center gap-2.5 border-t border-line px-4"
                        >
                            <span class="font-mono font-semibold">{{
                                i.quantity
                            }}</span>
                            <span class="text-sm">{{ loc(i, 'name') }}</span>
                            <span
                                class="text-right font-mono text-[13px] text-ink-2 num"
                                >{{ money(i.price) }}</span
                            >
                            <span class="text-right font-mono num">{{
                                money(i.total)
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-1.5 border-t-2 border-ink px-4 py-3.5"
                        >
                            <div class="flex justify-between text-sm">
                                <span class="text-ink-2">{{
                                    t('cashier.subtotal')
                                }}</span
                                ><span class="font-mono num">{{
                                    money(subtotal)
                                }}</span>
                            </div>
                            <div
                                v-if="discountAmount"
                                class="flex justify-between text-sm"
                            >
                                <span class="text-ink-2"
                                    >{{ t('cashier.discount') }}
                                    {{ form.discount_percent }} %</span
                                ><span class="font-mono text-danger num"
                                    >−{{ money(discountAmount) }}</span
                                >
                            </div>
                            <div
                                v-if="bonus > 0"
                                class="flex justify-between text-sm"
                            >
                                <span class="text-ink-2">{{
                                    t('cashier.paid_bonus')
                                }}</span
                                ><span class="font-mono text-danger num"
                                    >−{{ money(bonus) }}</span
                                >
                            </div>
                            <div
                                class="flex items-baseline justify-between pt-1.5"
                            >
                                <span class="text-base font-semibold">{{
                                    t('cashier.to_pay_total')
                                }}</span
                                ><span
                                    class="font-mono text-[32px] leading-9 font-semibold num"
                                    >{{ money(moneyDue) }}
                                    <span class="text-[15px] text-ink-3"
                                        >TMT</span
                                    ></span
                                >
                            </div>
                        </div>
                    </div>
                </template>
                <p
                    v-else
                    class="rounded-lg border border-dashed border-ink-3 bg-surface p-10 text-center text-sm text-ink-2"
                >
                    {{ t('cashier.pick_bill') }}
                </p>

                <section
                    v-if="recent.length"
                    class="overflow-hidden rounded-lg border border-line bg-surface"
                >
                    <h3
                        class="border-b border-line px-4 py-2.5 text-sm font-semibold"
                    >
                        {{ t('cashier.recent') }}
                    </h3>
                    <div
                        v-for="p in recent"
                        :key="p.id"
                        class="grid grid-cols-[60px_1fr_auto_auto] items-center gap-3 border-t border-line px-4 py-2 text-sm first:border-t-0"
                        :class="p.cancelled_at ? 'opacity-55' : ''"
                    >
                        <span class="font-mono text-xs text-ink-3">{{
                            time(p.created_at)
                        }}</span>
                        <span
                            >{{
                                p.channel && p.channel !== 'hall'
                                    ? `${t(`delivery.type_${p.channel}`)} №${p.order_id}`
                                    : `${t('tables.table')} ${p.table ?? '—'}`
                            }}
                            · {{ t(`pay.${p.method}`) }}</span
                        >
                        <span
                            class="font-mono num"
                            :class="p.cancelled_at ? 'line-through' : ''"
                            >{{ money(p.total) }}</span
                        >
                        <Badge v-if="p.cancelled_at" tone="danger">{{
                            t('cashier.refund')
                        }}</Badge>
                        <Btn
                            v-else-if="rules.can_cancel"
                            variant="ghost"
                            size="sm"
                            @click="cancelling = p"
                            >{{ t('cashier.cancel') }}</Btn
                        >
                        <span v-else />
                    </div>
                </section>
            </main>

            <!-- Оплата -->
            <section
                v-if="selected"
                :aria-label="t('cashier.payment')"
                class="flex min-w-0 flex-[1_1_360px] flex-col gap-5 rounded-lg border border-line bg-surface p-5"
            >
                <Field
                    v-if="rules.can_discount"
                    :label="t('cashier.one_time_discount')"
                    for="k-disc"
                    :error="form.errors.discount_percent"
                    :hint="
                        t('cashier.discount_hint', {
                            k: rules.discount_limit_k,
                        })
                    "
                >
                    <TextInput
                        id="k-disc"
                        v-model="form.discount_percent"
                        type="number"
                        min="0"
                        max="100"
                        mono
                        size="lg"
                        :invalid="overLimit"
                        :disabled="bonus > 0"
                        placeholder="0"
                        inputmode="numeric"
                    />
                </Field>

                <div class="flex flex-col gap-2">
                    <span class="text-[13px] font-medium">{{
                        t('cashier.method')
                    }}</span>
                    <div role="radiogroup" class="grid grid-cols-3 gap-2">
                        <button
                            v-for="m in methods"
                            :key="m"
                            type="button"
                            role="radio"
                            :aria-checked="form.method === m"
                            class="flex h-14 flex-col items-center justify-center rounded-md text-sm font-semibold"
                            :class="
                                form.method === m
                                    ? 'border-2 border-brand bg-brand-soft'
                                    : 'border border-line bg-paper'
                            "
                            @click="form.method = m"
                        >
                            {{ t(`pay.${m}`)
                            }}<span
                                class="text-[11px] font-normal text-ink-3"
                                >{{ t(`pay.${m}_hint`) }}</span
                            >
                        </button>
                    </div>
                </div>

                <div
                    v-if="form.method === 'cash'"
                    class="grid grid-cols-2 gap-3"
                >
                    <Field :label="t('cashier.received')" for="k-got"
                        ><MoneyInput id="k-got" v-model="received" size="lg"
                    /></Field>
                    <div class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-medium">{{
                            t('cashier.change')
                        }}</span
                        ><span
                            class="flex h-11 items-center font-mono text-2xl font-semibold num"
                            >{{ money(change) }}</span
                        >
                    </div>
                </div>
                <div
                    v-if="form.method === 'mixed'"
                    class="grid grid-cols-2 gap-3"
                >
                    <Field
                        :label="t('pay.cash')"
                        for="k-mc"
                        :error="form.errors.cash_amount"
                        ><MoneyInput
                            id="k-mc"
                            v-model="form.cash_amount"
                            size="lg"
                    /></Field>
                    <div class="flex flex-col gap-1.5">
                        <span class="text-[13px] font-medium">{{
                            t('cashier.card_rest')
                        }}</span
                        ><span
                            class="flex h-11 items-center rounded-md bg-surface-2 px-3 font-mono text-lg num"
                            >{{ money(mixedCard) }}</span
                        >
                    </div>
                </div>
                <p
                    v-if="moneyDue === 0 && bonus > 0"
                    class="text-xs leading-[17px] text-ok"
                >
                    {{ t('cashier.all_by_bonus') }}
                </p>
                <p
                    v-else-if="form.method === 'card'"
                    class="text-xs leading-[17px] text-ink-2"
                >
                    {{ t('cashier.card_hint') }}
                </p>

                <!-- Гость по телефону: счёт попадёт в его карточку (ТЗ 2.1) -->
                <div
                    class="grid grid-cols-1 gap-2 border-t border-line pt-4 sm:grid-cols-2"
                >
                    <Field
                        :label="t('cashier.guest_phone')"
                        for="k-gphone"
                        :error="form.errors.guest_phone"
                        ><TextInput
                            id="k-gphone"
                            v-model="form.guest_phone"
                            mono
                            inputmode="tel"
                            placeholder="+993 6X XXXXXX"
                            type="tel"
                            autocomplete="off"
                            maxlength="32"
                    /></Field>
                    <Field
                        :label="t('cashier.guest_name')"
                        for="k-gname"
                        :error="form.errors.guest_name"
                        ><TextInput
                            id="k-gname"
                            v-model="form.guest_name"
                            :placeholder="t('guests.name_ph')"
                            maxlength="120"
                            autocomplete="off"
                    /></Field>
                </div>

                <!-- Бонусы гостя: часть счёта или весь счёт (до M %) -->
                <div
                    v-if="bonusRules.enabled && guestInfo"
                    class="flex flex-col gap-2 rounded-md bg-surface-2 p-3"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2 text-sm"
                    >
                        <span
                            >{{ guestInfo.name }} ·
                            {{ t('cashier.bonus_balance') }}
                            <b class="font-mono num">{{
                                money(guestInfo.balance)
                            }}</b></span
                        >
                        <span class="text-xs text-ink-3">{{
                            t('cashier.bonus_max', {
                                n: money(maxBonus),
                                m: bonusRules.max_spend_percent,
                            })
                        }}</span>
                    </div>
                    <div
                        v-if="maxBonus > 0"
                        class="grid grid-cols-[minmax(0,1fr)_auto] gap-2"
                    >
                        <MoneyInput
                            id="k-bonus"
                            v-model="form.bonus_amount"
                            :aria-label="t('cashier.bonus_spend')"
                            :placeholder="t('cashier.bonus_spend')"
                            :invalid="
                                bonusTooMuch ||
                                Boolean(form.errors.bonus_amount)
                            "
                            :disabled="Number(form.discount_percent) > 0"
                        />
                        <Btn
                            variant="secondary"
                            :disabled="Number(form.discount_percent) > 0"
                            @click="useMaxBonus"
                            >{{ t('cashier.bonus_all') }}</Btn
                        >
                    </div>
                    <p
                        v-if="Number(form.discount_percent) > 0 && maxBonus > 0"
                        class="text-xs text-ink-3"
                    >
                        {{ t('cashier.bonus_or_discount') }}
                    </p>
                    <p
                        v-if="form.errors.bonus_amount"
                        class="text-xs text-danger"
                        role="alert"
                    >
                        {{ form.errors.bonus_amount }}
                    </p>
                    <p v-if="accrual > 0" class="text-xs text-ok">
                        {{ t('cashier.bonus_accrual', { n: money(accrual) }) }}
                    </p>
                </div>

                <!-- Чаевые: не входят в выручку и в сумму счёта -->
                <div class="flex flex-col gap-2 border-t border-line pt-4">
                    <span class="text-[13px] font-medium">{{
                        t('cashier.tip')
                    }}</span>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <MoneyInput
                            id="k-tip"
                            v-model="form.tip_amount"
                            :aria-label="t('cashier.tip')"
                            :invalid="Boolean(form.errors.tip_amount)"
                            size="lg"
                        />
                        <div
                            role="radiogroup"
                            :aria-label="t('cashier.tip_method')"
                            class="flex overflow-hidden rounded-md border border-line"
                        >
                            <button
                                v-for="m in ['cash', 'card']"
                                :key="m"
                                type="button"
                                role="radio"
                                :aria-checked="form.tip_method === m"
                                class="h-11 px-3 text-sm"
                                :class="
                                    form.tip_method === m
                                        ? 'bg-brand-soft font-semibold'
                                        : 'bg-paper text-ink-2'
                                "
                                @click="form.tip_method = m"
                            >
                                {{ t(`pay.${m}`) }}
                            </button>
                        </div>
                    </div>
                    <span class="text-xs text-ink-3">{{
                        t('cashier.tip_hint')
                    }}</span>
                    <span
                        v-if="form.errors.tip_amount"
                        class="text-xs text-danger"
                        >{{ form.errors.tip_amount }}</span
                    >
                </div>

                <p v-if="form.errors.order" class="text-sm text-danger">
                    {{ form.errors.order }}
                </p>

                <Btn
                    class="mt-auto"
                    size="xxl"
                    block
                    :disabled="!total || overLimit || unsent > 0"
                    :loading="form.processing"
                    @click="pay"
                >
                    {{ t('cashier.accept') }}
                    <span class="font-mono font-medium"
                        >· {{ money(total, true) }}</span
                    >
                </Btn>
            </section>
        </div>

        <Modal
            :show="cancelling !== null"
            :title="t('cashier.cancel_title')"
            @close="cancelling = null"
        >
            <p class="mb-3 text-sm text-ink-2">
                {{ t('cashier.cancel_hint') }}
            </p>
            <Field
                :label="t('waiter.reason_ph')"
                for="k-reason"
                :error="cancelForm.errors.reason"
                :hint="t('cashier.reason_min')"
                ><TextInput
                    id="k-reason"
                    v-model="cancelForm.reason"
                    :placeholder="t('cashier.cancel_reason_ph')"
                    maxlength="200"
            /></Field>
            <template #footer>
                <Btn variant="ghost" @click="cancelling = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    variant="danger"
                    :loading="cancelForm.processing"
                    @click="cancelPayment"
                    >{{ t('cashier.cancel') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

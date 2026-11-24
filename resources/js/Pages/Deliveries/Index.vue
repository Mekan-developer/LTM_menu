<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import DeliveryCompleteModal from '@/Components/DeliveryCompleteModal.vue';
import { useI18n } from '@/i18n';
import { useRealtime } from '@/composables/useRealtime';
import { useSound } from '@/composables/useSound';
import { minutesSince, money, phone, time, toMinor } from '@/utils/format';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    couriers: { type: Array, default: () => [] },
    cashOnCouriers: { type: Array, default: () => [] },
    fee: { type: Number, default: 0 },
    menu: { type: Array, default: null },
});

const { t, loc } = useI18n();
const sound = useSound();

// Новый заказ со страницы — короткий сигнал (ТЗ 2.6).
useRealtime('deliveries', 'deliveries.changed', (e) => {
    if (e?.status === 'new') sound.newOrder();
    router.reload({
        only: ['deliveries', 'cashOnCouriers'],
        preserveScroll: true,
    });
});

const now = ref(Date.now());
let tick = null;
onMounted(() => (tick = setInterval(() => (now.value = Date.now()), 30000)));
onUnmounted(() => clearInterval(tick));

// --- Группы по статусу -------------------------------------------------------
const groups = computed(() => {
    const by = (statuses) =>
        props.deliveries.filter((d) => statuses.includes(d.status));
    return [
        { key: 'new', items: by(['new']) },
        { key: 'accepted', items: by(['accepted']) },
        { key: 'cooking', items: by(['cooking']) },
        { key: 'ready', items: by(['ready']) },
        { key: 'on_way', items: by(['on_way']) },
        {
            key: 'done',
            items: by(['delivered', 'issued', 'cancelled']).reverse(),
        },
    ];
});
const activeCount = computed(
    () =>
        groups.value.filter((g) => g.key !== 'done').flatMap((g) => g.items)
            .length,
);
const showDone = ref(false);

const tone = (s) =>
    ({
        new: 'ochre',
        accepted: 'brand',
        cooking: 'accent',
        ready: 'ok',
        on_way: 'brand',
        delivered: 'muted',
        issued: 'muted',
        cancelled: 'danger',
    })[s] ?? 'neutral';
const minutes = (d) =>
    Math.max(0, Math.floor(minutesSince(d.created_at, now.value)));

// --- Действия -------------------------------------------------------------------
const act = (d, action, data = {}) =>
    router.post(
        route('deliveries.action', d.id),
        { action, ...data },
        { preserveScroll: true },
    );
function cancel(d) {
    const reason = window.prompt(
        `${t('delivery.cancel_title', { n: d.order_id })}\n${t('delivery.cancel_reason')}`,
    );
    if (reason?.trim()) act(d, 'cancel', { reason: reason.trim() });
}
function editFee(d) {
    const v = window.prompt(t('delivery.fee_tmt'), money(d.delivery_fee));
    if (v !== null && v.trim() !== '')
        act(d, 'fee', { delivery_fee: v.trim() });
}
const completing = ref(null);

// --- Новый заказ по звонку ------------------------------------------------------
const creating = ref(false);
const form = useForm({
    type: 'delivery',
    phone: '',
    name: '',
    address: '',
    method: 'cash',
    delivery_fee: '',
    comment: '',
    items: [],
});
const knownGuest = ref(false);

function openNew() {
    form.reset();
    form.clearErrors();
    form.delivery_fee = money(props.fee);
    knownGuest.value = false;
    creating.value = true;
    if (!props.menu) router.reload({ only: ['menu'], preserveScroll: true });
}

// Гость уже в базе — подставляем имя и адрес, если их ещё не ввели.
async function lookupGuest() {
    if (form.phone.replace(/\D/g, '').length < 8) return;
    try {
        const { data } = await axios.get(route('deliveries.guest'), {
            params: { phone: form.phone },
        });
        knownGuest.value = Boolean(data);
        if (data) {
            if (!form.name) form.name = data.name;
            if (!form.address && data.address) form.address = data.address;
        }
    } catch {
        /* подсказка не обязательна */
    }
}

const dishes = computed(() =>
    (props.menu ?? []).flatMap((c) =>
        c.dishes.map((d) => ({ ...d, category: loc(c, 'name') })),
    ),
);
const dishById = (id) => dishes.value.find((d) => d.id === id);
const pick = ref(null);
function addItem() {
    if (!pick.value) return;
    const row = form.items.find((i) => i.dish_id === pick.value);
    if (row) row.quantity++;
    else form.items.push({ dish_id: pick.value, quantity: 1 });
    pick.value = null;
}
const itemsTotal = computed(() =>
    form.items.reduce(
        (s, i) => s + (dishById(i.dish_id)?.price ?? 0) * i.quantity,
        0,
    ),
);
const formTotal = computed(
    () =>
        itemsTotal.value +
        (form.type === 'delivery' ? toMinor(form.delivery_fee) : 0),
);

function save() {
    form.transform((data) => ({
        ...data,
        address: data.type === 'delivery' ? data.address : null,
        delivery_fee: data.type === 'delivery' ? data.delivery_fee : null,
    })).post(route('deliveries.store'), {
        preserveScroll: true,
        onSuccess: () => (creating.value = false),
    });
}
</script>

<template>
    <AdminLayout :title="t('nav.deliveries')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.deliveries') }}
                    </h1>
                    <a
                        :href="route('guest.delivery')"
                        target="_blank"
                        class="font-mono text-xs text-ink-3 hover:text-ink-2 hover:underline"
                        >{{ t('delivery.guest_page') }}: /delivery</a
                    >
                </div>
                <Btn @click="openNew"
                    ><Icon name="plus" />{{ t('delivery.add') }}</Btn
                >
            </div>

            <!-- Наличные у курьеров (ТЗ 2.6) -->
            <div
                v-if="cashOnCouriers.length"
                class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg border border-ochre bg-ochre-soft px-4 py-3 text-sm"
            >
                <span class="font-semibold text-ochre-ink">{{
                    t('delivery.cash_on_couriers')
                }}</span>
                <span
                    v-for="c in cashOnCouriers"
                    :key="c.courier_id"
                    class="font-mono num"
                    >{{ c.name }} · {{ money(c.amount, true) }}</span
                >
            </div>

            <template v-for="g in groups" :key="g.key">
                <section
                    v-if="g.key !== 'done' ? g.items.length : true"
                    class="flex flex-col gap-3"
                >
                    <button
                        v-if="g.key === 'done'"
                        type="button"
                        class="flex items-center gap-2 self-start text-sm font-semibold text-ink-2 hover:text-ink"
                        :aria-expanded="showDone"
                        @click="showDone = !showDone"
                    >
                        {{ t('delivery.group_done') }}
                        <span
                            class="rounded-sm bg-surface-2 px-1.5 font-mono text-xs"
                            >{{ g.items.length }}</span
                        >
                        <span aria-hidden="true">{{
                            showDone ? '▾' : '▸'
                        }}</span>
                    </button>
                    <h2
                        v-else
                        class="flex items-center gap-2 text-sm font-semibold text-ink-2"
                    >
                        {{ t(`delivery.group_${g.key}`) }}
                        <span
                            class="rounded-sm bg-surface-2 px-1.5 font-mono text-xs"
                            >{{ g.items.length }}</span
                        >
                    </h2>

                    <div
                        v-if="g.key !== 'done' || showDone"
                        class="grid gap-3 lg:grid-cols-2 2xl:grid-cols-3"
                    >
                        <article
                            v-for="d in g.items"
                            :key="d.id"
                            class="flex flex-col gap-3 rounded-lg border bg-surface p-4"
                            :class="
                                d.status === 'new'
                                    ? 'border-ochre'
                                    : 'border-line'
                            "
                        >
                            <!-- Шапка: номер, тип, статус, время -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-lg font-semibold"
                                    >№{{ d.order_id }}</span
                                >
                                <Badge
                                    :tone="
                                        d.type === 'delivery'
                                            ? 'outline'
                                            : 'neutral'
                                    "
                                    >{{ t(`delivery.type_${d.type}`) }}</Badge
                                >
                                <Badge :tone="tone(d.status)">{{
                                    t(`delivery.status_${d.status}`)
                                }}</Badge>
                                <span
                                    class="ml-auto font-mono text-xs text-ink-3"
                                    >{{ time(d.created_at) }}
                                    <template v-if="g.key !== 'done'"
                                        >·
                                        {{
                                            t('delivery.minutes', {
                                                n: minutes(d),
                                            })
                                        }}</template
                                    ></span
                                >
                            </div>

                            <!-- Гость -->
                            <div class="flex flex-col gap-0.5 text-sm">
                                <span
                                    class="flex flex-wrap items-center gap-x-2"
                                    ><span class="font-semibold">{{
                                        d.name
                                    }}</span
                                    ><a
                                        :href="`tel:${d.phone}`"
                                        class="font-mono text-ink-2 hover:underline"
                                        >{{ phone(d.phone) }}</a
                                    ><span class="text-xs text-ink-3">{{
                                        d.source === 'online'
                                            ? t('delivery.from_guest')
                                            : t('delivery.by_phone')
                                    }}</span></span
                                >
                                <span
                                    v-if="d.address"
                                    class="flex items-start gap-1.5 text-ink-2"
                                    ><Icon
                                        name="pin"
                                        :size="14"
                                        class="mt-0.5 shrink-0"
                                    />{{ d.address }}</span
                                >
                                <span
                                    v-if="d.comment"
                                    class="text-xs text-ink-3 italic"
                                    >«{{ d.comment }}»</span
                                >
                            </div>

                            <!-- Блюда и суммы -->
                            <ul
                                class="flex flex-col gap-0.5 border-t border-line pt-2 text-sm"
                            >
                                <li
                                    v-for="i in d.items"
                                    :key="i.id"
                                    class="flex justify-between gap-3"
                                >
                                    <span
                                        ><span class="font-mono text-ink-2"
                                            >{{ i.qty }}×</span
                                        >
                                        {{ loc(i, 'name')
                                        }}<span
                                            v-if="i.status === 'ready'"
                                            class="ml-1.5 text-xs text-ok"
                                            >✓</span
                                        ></span
                                    >
                                    <span class="font-mono text-ink-2 num">{{
                                        money(i.price * i.qty)
                                    }}</span>
                                </li>
                            </ul>
                            <div
                                class="flex flex-wrap items-baseline justify-between gap-2 text-sm"
                            >
                                <span class="text-ink-2"
                                    >{{ t(`pay.${d.planned_method}`) }}
                                    <span class="text-xs text-ink-3"
                                        >({{ t('delivery.planned') }})</span
                                    ></span
                                >
                                <span class="flex items-baseline gap-3">
                                    <button
                                        v-if="d.type === 'delivery'"
                                        type="button"
                                        class="font-mono text-xs text-ink-2 underline decoration-dotted underline-offset-2 disabled:no-underline"
                                        :disabled="
                                            [
                                                'delivered',
                                                'issued',
                                                'cancelled',
                                            ].includes(d.status)
                                        "
                                        @click="editFee(d)"
                                    >
                                        {{ t('delivery.fee') }}
                                        {{ money(d.delivery_fee) }}
                                    </button>
                                    <span
                                        class="font-mono text-lg font-semibold num"
                                        >{{ money(d.total, true) }}</span
                                    >
                                </span>
                            </div>

                            <!-- Курьер -->
                            <label
                                v-if="
                                    d.type === 'delivery' &&
                                    [
                                        'new',
                                        'accepted',
                                        'cooking',
                                        'ready',
                                    ].includes(d.status)
                                "
                                class="flex items-center gap-2 text-sm"
                            >
                                <span class="text-ink-2">{{
                                    t('delivery.courier')
                                }}</span>
                                <select
                                    class="h-9 min-w-0 flex-1 rounded-md border border-line bg-paper px-2 text-sm"
                                    :value="d.courier?.id ?? ''"
                                    @change="
                                        act(d, 'courier', {
                                            courier_id:
                                                $event.target.value || null,
                                        })
                                    "
                                >
                                    <option value="">
                                        — {{ t('delivery.no_courier') }} —
                                    </option>
                                    <option
                                        v-for="c in couriers"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </label>
                            <span
                                v-else-if="d.courier"
                                class="text-sm text-ink-2"
                                >{{ t('delivery.courier') }}:
                                <b class="text-ink">{{
                                    d.courier.name
                                }}</b></span
                            >

                            <!-- Итог завершённого -->
                            <span v-if="d.payment" class="text-sm text-ok"
                                >{{ t('delivery.paid') }}:
                                {{ t(`pay.${d.payment.method}`) }}
                                <template v-if="d.payment.method === 'mixed'"
                                    >({{ money(d.payment.cash) }} +
                                    {{ money(d.payment.card) }})</template
                                ></span
                            >
                            <span
                                v-if="d.cancel_reason"
                                class="text-sm text-danger"
                                >{{
                                    t('delivery.cancelled_reason', {
                                        r: d.cancel_reason,
                                    })
                                }}</span
                            >

                            <!-- Действия -->
                            <div
                                v-if="g.key !== 'done'"
                                class="flex flex-wrap items-center gap-1.5 border-t border-line pt-3"
                            >
                                <Btn
                                    v-if="d.status === 'new'"
                                    size="sm"
                                    variant="secondary"
                                    @click="act(d, 'accept')"
                                    >{{ t('delivery.do_accept') }}</Btn
                                >
                                <Btn
                                    v-if="
                                        ['new', 'accepted'].includes(d.status)
                                    "
                                    size="sm"
                                    @click="act(d, 'kitchen')"
                                    >{{ t('delivery.do_kitchen') }}</Btn
                                >
                                <span
                                    v-if="d.status === 'cooking'"
                                    class="text-sm text-ink-2"
                                    >{{ t('delivery.wait_kitchen') }}</span
                                >
                                <Btn
                                    v-if="
                                        d.status === 'ready' &&
                                        d.type === 'delivery'
                                    "
                                    size="sm"
                                    :disabled="!d.courier"
                                    @click="act(d, 'pick_up')"
                                    >{{ t('delivery.do_pick_up') }}</Btn
                                >
                                <span
                                    v-if="
                                        d.status === 'ready' &&
                                        d.type === 'pickup'
                                    "
                                    class="text-sm font-medium text-ok"
                                    >{{ t('delivery.pickup_pay_hint') }}</span
                                >
                                <Btn
                                    v-if="d.status === 'on_way'"
                                    size="sm"
                                    @click="completing = d"
                                    >{{ t('delivery.do_complete') }}</Btn
                                >
                                <Btn
                                    size="sm"
                                    variant="ghost"
                                    class="ml-auto"
                                    @click="cancel(d)"
                                    >{{ t('delivery.do_cancel') }}</Btn
                                >
                            </div>
                        </article>
                    </div>
                </section>
            </template>

            <p
                v-if="!activeCount"
                class="rounded-lg border border-dashed border-ink-3 bg-surface p-10 text-center text-sm text-ink-2"
            >
                {{ t('delivery.empty') }}
            </p>
        </div>

        <DeliveryCompleteModal
            :delivery="completing"
            route-name="deliveries.complete"
            @close="completing = null"
        />

        <!-- Новый заказ по звонку -->
        <Modal
            :show="creating"
            :title="t('delivery.new_title')"
            @close="creating = false"
        >
            <form
                id="delivery-form"
                class="grid grid-cols-2 gap-3"
                @submit.prevent="save"
            >
                <div
                    role="radiogroup"
                    class="col-span-2 grid grid-cols-2 gap-2"
                >
                    <button
                        v-for="tp in ['delivery', 'pickup']"
                        :key="tp"
                        type="button"
                        role="radio"
                        :aria-checked="form.type === tp"
                        class="h-11 rounded-md text-sm font-semibold"
                        :class="
                            form.type === tp
                                ? 'border-2 border-brand bg-brand-soft'
                                : 'border border-line bg-paper'
                        "
                        @click="form.type = tp"
                    >
                        {{ t(`delivery.type_${tp}`) }}
                    </button>
                </div>
                <Field
                    :label="t('delivery.phone')"
                    for="d-phone"
                    :error="form.errors.phone"
                    ><TextInput
                        id="d-phone"
                        v-model="form.phone"
                        mono
                        inputmode="tel"
                        placeholder="+993 6X XXXXXX"
                        @blur="lookupGuest"
                /></Field>
                <Field
                    :label="t('delivery.name')"
                    for="d-name"
                    :error="form.errors.name"
                    ><TextInput id="d-name" v-model="form.name"
                /></Field>
                <p v-if="knownGuest" class="col-span-2 -mt-1 text-xs text-ok">
                    {{ t('delivery.known_guest') }}
                </p>
                <div v-if="form.type === 'delivery'" class="col-span-2">
                    <Field
                        :label="t('delivery.address')"
                        for="d-address"
                        :error="form.errors.address"
                        ><TextInput
                            id="d-address"
                            v-model="form.address"
                            :placeholder="t('delivery.address_ph')"
                    /></Field>
                </div>
                <Field
                    :label="t('delivery.method')"
                    for="d-method"
                    :error="form.errors.method"
                >
                    <select
                        id="d-method"
                        v-model="form.method"
                        class="h-11 w-full rounded-md border border-line bg-paper px-3 text-sm"
                    >
                        <option value="cash">{{ t('pay.cash') }}</option>
                        <option value="card">{{ t('pay.card') }}</option>
                    </select>
                </Field>
                <Field
                    v-if="form.type === 'delivery'"
                    :label="t('delivery.fee_tmt')"
                    for="d-fee"
                    :error="form.errors.delivery_fee"
                    ><TextInput
                        id="d-fee"
                        v-model="form.delivery_fee"
                        mono
                        inputmode="decimal"
                /></Field>
                <div class="col-span-2">
                    <Field
                        :label="t('delivery.comment')"
                        for="d-comment"
                        :error="form.errors.comment"
                        ><TextInput id="d-comment" v-model="form.comment"
                    /></Field>
                </div>

                <!-- Блюда -->
                <div
                    class="col-span-2 flex flex-col gap-2 rounded-md border border-line p-3"
                >
                    <span class="text-[13px] font-medium">{{
                        t('delivery.items')
                    }}</span>
                    <div class="flex gap-2">
                        <select
                            v-model="pick"
                            class="h-10 min-w-0 flex-1 rounded-md border border-line bg-paper px-2 text-sm"
                            :aria-label="t('delivery.add_dish')"
                        >
                            <option :value="null" disabled>
                                {{ menu ? t('delivery.add_dish') : '…' }}
                            </option>
                            <option
                                v-for="dish in dishes"
                                :key="dish.id"
                                :value="dish.id"
                                :disabled="dish.stopped"
                            >
                                {{ dish.category }} · {{ loc(dish, 'name') }} ·
                                {{ money(dish.price) }}
                            </option>
                        </select>
                        <Btn
                            variant="secondary"
                            :disabled="!pick"
                            :aria-label="t('delivery.add_dish')"
                            @click="addItem"
                            ><Icon name="plus" :size="14"
                        /></Btn>
                    </div>
                    <div
                        v-for="(it, idx) in form.items"
                        :key="it.dish_id"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <span class="min-w-0 truncate">{{
                            loc(dishById(it.dish_id) ?? {}, 'name') ||
                            '#' + it.dish_id
                        }}</span>
                        <span class="flex items-center gap-1">
                            <button
                                type="button"
                                class="size-7 rounded-sm border border-line"
                                :aria-label="t('waiter.less')"
                                @click="
                                    it.quantity > 1
                                        ? it.quantity--
                                        : form.items.splice(idx, 1)
                                "
                            >
                                −
                            </button>
                            <span class="w-6 text-center font-mono">{{
                                it.quantity
                            }}</span>
                            <button
                                type="button"
                                class="size-7 rounded-sm border border-line"
                                :aria-label="t('waiter.more_qty')"
                                @click="it.quantity++"
                            >
                                +
                            </button>
                        </span>
                    </div>
                    <p v-if="!form.items.length" class="text-xs text-ink-3">
                        {{ t('delivery.no_items') }}
                    </p>
                    <span
                        v-if="form.errors.items"
                        class="text-xs text-danger"
                        >{{ form.errors.items }}</span
                    >
                </div>
                <div
                    class="col-span-2 flex items-baseline justify-between border-t border-line pt-3"
                >
                    <span class="text-sm text-ink-2">{{
                        t('delivery.total')
                    }}</span>
                    <span class="font-mono text-xl font-semibold num">{{
                        money(formTotal, true)
                    }}</span>
                </div>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="creating = false">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="delivery-form"
                    :loading="form.processing"
                    :disabled="!form.items.length"
                    >{{ t('delivery.add') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

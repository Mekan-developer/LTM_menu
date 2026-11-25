<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import WaiterLayout from '@/Layouts/WaiterLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';
import { useRealtime } from '@/composables/useRealtime';
import TextInput from '@/Components/TextInput.vue';
import Select from '@/Components/Select.vue';

const props = defineProps({
    order: { type: Object, required: true },
    menu: { type: Array, default: () => [] },
    waiters: { type: Array, default: () => [] },
    canRemoveSent: { type: Boolean, default: false },
    freeTables: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();

// Новые позиции копятся на телефоне и уходят одним нажатием «На кухню».
const storageKey = `picks:${props.order.id}`;
const picks = ref(load());
function load() {
    try {
        return JSON.parse(localStorage.getItem(storageKey) ?? '{}');
    } catch {
        return {};
    }
}
watch(
    picks,
    (v) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(v));
        } catch {
            /* */
        }
    },
    { deep: true },
);

const activeCat = ref(props.menu[0]?.id ?? null);
const dishes = computed(
    () => props.menu.find((c) => c.id === activeCat.value)?.dishes ?? [],
);
const dishById = computed(() =>
    Object.fromEntries(
        props.menu.flatMap((c) => c.dishes).map((d) => [d.id, d]),
    ),
);

const pickList = computed(() =>
    Object.entries(picks.value)
        .map(([id, p]) => ({ dish: dishById.value[id], ...p }))
        .filter((p) => p.dish),
);
const pickCount = computed(() => pickList.value.reduce((s, p) => s + p.qty, 0));

function add(dish) {
    if (dish.stopped) return;
    const cur = picks.value[dish.id] ?? { qty: 0, comment: '' };
    picks.value = { ...picks.value, [dish.id]: { ...cur, qty: cur.qty + 1 } };
}
function dec(id) {
    const cur = picks.value[id];
    if (!cur) return;
    const next = { ...picks.value };
    if (cur.qty <= 1) delete next[id];
    else next[id] = { ...cur, qty: cur.qty - 1 };
    picks.value = next;
}

const sending = ref(false);
function sendToKitchen() {
    const drafts = props.order.items.filter((i) => i.status === 'draft');
    const items = pickList.value.map((p) => ({
        dish_id: p.dish.id,
        quantity: p.qty,
        comment: p.comment || null,
    }));
    if (!items.length && !drafts.length) return;
    sending.value = true;

    const send = () =>
        router.post(
            route('waiter.send', props.order.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    picks.value = {};
                },
                onFinish: () => {
                    sending.value = false;
                },
            },
        );

    if (!items.length) {
        send();
        return;
    }
    router.post(
        route('waiter.items.store', props.order.id),
        { items },
        {
            preserveScroll: true,
            onSuccess: send,
            onError: () => {
                sending.value = false;
            },
        },
    );
}

// Отправленные позиции по партиям.
const groups = computed(() => {
    const visible = props.order.items.filter((i) => i.status !== 'removed');
    const by = (s) => visible.filter((i) => i.status === s);
    return {
        draft: by('draft'),
        pending: by('pending'),
        sent: by('sent'),
        ready: by('ready'),
        served: by('served'),
    };
});
const readyBatches = computed(() => [
    ...new Set(groups.value.ready.map((i) => i.batch)),
]);
const statusTone = {
    pending: 'accent',
    sent: 'ochre',
    ready: 'ok',
    served: 'muted',
};

const served = (batch) =>
    router.post(
        route('waiter.served', [props.order.id, batch]),
        {},
        { preserveScroll: true },
    );
const requestBill = () => router.post(route('waiter.bill', props.order.id));

// Удаление после кухни — с причиной.
const removing = ref(null);
const reason = ref('');
function askRemove(item) {
    if (item.status === 'draft' || item.status === 'pending') {
        router.delete(route('waiter.items.destroy', item.id), {
            preserveScroll: true,
        });
        return;
    }
    removing.value = item;
    reason.value = '';
}
const confirmRemove = () =>
    router.delete(route('waiter.items.destroy', removing.value.id), {
        data: { reason: reason.value },
        preserveScroll: true,
        onSuccess: () => {
            removing.value = null;
        },
    });

const more = ref(false);
const transferTo = ref(null);
const transfer = () =>
    router.post(route('waiter.transfer', props.order.id), {
        waiter_id: transferTo.value,
    });

// Перенос за другой стол: гости пересели (ТЗ 2.4).
const moveTo = ref(null);
const move = () =>
    router.post(
        route('waiter.move', props.order.id),
        { table_id: moveTo.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                more.value = false;
                moveTo.value = null;
            },
        },
    );

useRealtime('waiters', 'waiter.alert', (payload) => {
    if (!payload || payload.order_id === props.order.id)
        router.reload({ only: ['order'], preserveScroll: true });
});
</script>

<template>
    <WaiterLayout :title="`${t('tables.table')} ${order.table?.number}`">
        <template #header>
            <Link
                :href="route('waiter.index')"
                class="flex size-12 items-center justify-center rounded-md"
                :aria-label="t('common.back')"
                ><Icon name="back" :size="22"
            /></Link>
            <div class="flex flex-1 flex-col leading-tight">
                <span class="text-lg font-semibold"
                    >{{ t('tables.table') }} {{ order.table?.number }}</span
                >
                <span class="font-mono text-xs text-ink-2"
                    >{{ order.waiter?.name ?? t('waiter.no_waiter') }} ·
                    {{ money(order.total) }}</span
                >
            </div>
            <button
                type="button"
                class="flex size-12 items-center justify-center rounded-md"
                :aria-label="t('waiter.more')"
                @click="more = true"
            >
                <Icon name="more" :size="22" />
            </button>
        </template>

        <div class="flex flex-col gap-3">
            <!-- Уже в заказе -->
            <section
                v-if="order.items.length"
                class="flex flex-col gap-1 rounded-lg border border-line bg-surface px-3 py-2"
            >
                <template
                    v-for="key in [
                        'pending',
                        'draft',
                        'sent',
                        'ready',
                        'served',
                    ]"
                    :key="key"
                >
                    <div
                        v-for="item in groups[key]"
                        :key="item.id"
                        class="flex min-h-9 items-center gap-2"
                    >
                        <span class="w-7 font-mono text-[13px] font-semibold"
                            >{{ item.quantity }}×</span
                        >
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span
                                class="truncate text-sm"
                                :class="key === 'served' ? 'text-ink-3' : ''"
                                >{{ loc(item, 'name') }}</span
                            >
                            <span
                                v-if="item.comment"
                                class="truncate text-xs text-accent"
                                >{{ item.comment }}</span
                            >
                        </span>
                        <Badge
                            :tone="
                                key === 'draft' ? 'outline' : statusTone[key]
                            "
                            >{{ t(`item_status.${key}`) }}</Badge
                        >
                        <button
                            v-if="
                                ['draft', 'pending'].includes(key) ||
                                canRemoveSent
                            "
                            type="button"
                            class="flex size-9 items-center justify-center rounded-md text-ink-3"
                            :aria-label="t('common.delete')"
                            @click="askRemove(item)"
                        >
                            <Icon name="trash" :size="16" />
                        </button>
                    </div>
                </template>
                <div v-for="b in readyBatches" :key="b" class="pt-1">
                    <Btn
                        variant="secondary"
                        size="sm"
                        block
                        @click="served(b)"
                        >{{ t('waiter.mark_served') }}</Btn
                    >
                </div>
            </section>

            <!-- Новые позиции -->
            <section
                v-if="pickList.length"
                class="flex flex-col gap-2 rounded-lg border-2 border-brand bg-surface p-3"
            >
                <span
                    class="font-mono text-[11px] tracking-[0.08em] text-ink-3 uppercase"
                    >{{ t('waiter.new_items') }}</span
                >
                <div
                    v-for="p in pickList"
                    :key="p.dish.id"
                    class="flex flex-col gap-1.5"
                >
                    <div class="flex items-center gap-2">
                        <span class="flex-1 text-sm font-semibold">{{
                            loc(p.dish, 'name')
                        }}</span>
                        <button
                            type="button"
                            class="flex size-11 items-center justify-center rounded-md border border-line"
                            :aria-label="t('waiter.less')"
                            @click="dec(p.dish.id)"
                        >
                            <Icon name="minus" />
                        </button>
                        <span class="w-7 text-center font-mono font-semibold">{{
                            p.qty
                        }}</span>
                        <button
                            type="button"
                            class="flex size-11 items-center justify-center rounded-md border border-line"
                            :aria-label="t('waiter.more_qty')"
                            @click="add(p.dish)"
                        >
                            <Icon name="plus" />
                        </button>
                    </div>
                    <TextInput
                        v-model="picks[p.dish.id].comment"
                        maxlength="200"
                        :placeholder="t('waiter.comment_ph')"
                        :aria-label="t('waiter.comment_ph')"
                    />
                </div>
            </section>

            <!-- Меню -->
            <div
                role="tablist"
                :aria-label="t('nav.menu')"
                class="-mx-3 flex gap-1.5 overflow-x-auto px-3 pb-1"
            >
                <button
                    v-for="c in menu"
                    :key="c.id"
                    type="button"
                    role="tab"
                    :aria-selected="activeCat === c.id"
                    class="h-11 shrink-0 rounded-md border px-3.5 text-sm font-medium"
                    :class="
                        activeCat === c.id
                            ? 'border-ink bg-ink text-paper'
                            : 'border-line'
                    "
                    @click="activeCat = c.id"
                >
                    {{ loc(c, 'name') }}
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="d in dishes"
                    :key="d.id"
                    type="button"
                    class="relative flex min-h-[76px] flex-col justify-between gap-1.5 rounded-lg border p-3 text-left active:translate-y-px"
                    :class="[
                        picks[d.id]
                            ? 'border-2 border-brand bg-brand-soft'
                            : 'border-line bg-surface',
                        d.stopped ? 'cursor-not-allowed opacity-50' : '',
                    ]"
                    :disabled="d.stopped"
                    :aria-label="
                        d.stopped
                            ? `${loc(d, 'name')} — ${t('stop.in_stop')}`
                            : `${t('waiter.add')} ${loc(d, 'name')}`
                    "
                    @click="add(d)"
                >
                    <span class="pr-7 text-sm leading-[18px] font-medium">{{
                        loc(d, 'name')
                    }}</span>
                    <span class="font-mono text-[13px] text-ink-2"
                        ><span :class="d.old_price ? 'text-danger' : ''">{{
                            money(d.price)
                        }}</span
                        ><s v-if="d.old_price" class="ml-1.5 text-ink-3">{{
                            money(d.old_price)
                        }}</s></span
                    >
                    <span
                        v-if="picks[d.id]"
                        class="absolute top-2 right-2 flex size-[26px] items-center justify-center rounded-sm bg-brand font-mono text-sm font-semibold text-brand-ink"
                        >{{ picks[d.id].qty }}</span
                    >
                    <span v-if="d.stopped" class="absolute top-2 right-2"
                        ><Badge tone="muted">{{
                            t('stop.in_stop')
                        }}</Badge></span
                    >
                </button>
            </div>
            <div class="h-24" aria-hidden="true" />
        </div>

        <div
            class="fixed inset-x-0 bottom-[78px] z-10 grid grid-cols-[104px_1fr] gap-2 border-t border-line bg-surface px-3 py-2.5"
        >
            <Btn
                variant="secondary"
                size="xl"
                :disabled="order.status === 'bill_requested'"
                @click="requestBill"
                >{{ t('waiter.bill') }}</Btn
            >
            <Btn
                size="xl"
                :disabled="!pickCount && !groups.draft.length"
                :loading="sending"
                @click="sendToKitchen"
            >
                {{ t('waiter.to_kitchen')
                }}<span v-if="pickCount" class="font-mono font-medium"
                    >· {{ pickCount }}</span
                >
            </Btn>
        </div>

        <Modal
            :show="removing !== null"
            :title="t('waiter.remove_title')"
            @close="removing = null"
        >
            <p class="mb-3 text-sm text-ink-2">
                {{ removing ? loc(removing, 'name') : '' }} —
                {{ t('waiter.remove_hint') }}
            </p>
            <TextInput
                v-model="reason"
                maxlength="200"
                :placeholder="t('waiter.reason_ph')"
                :aria-label="t('waiter.reason_ph')"
                size="lg"
            />
            <p class="mt-1.5 text-xs text-ink-3">
                {{ t('waiter.reason_min') }}
            </p>
            <template #footer>
                <Btn variant="ghost" @click="removing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    variant="danger"
                    :disabled="reason.trim().length < 3"
                    @click="confirmRemove"
                    >{{ t('common.delete') }}</Btn
                >
            </template>
        </Modal>

        <Modal
            :show="more"
            :title="`${t('tables.table')} ${order.table?.number}`"
            @close="more = false"
        >
            <div class="flex flex-col gap-3">
                <p
                    v-if="order.guest_comment"
                    class="rounded-md bg-accent-soft p-3 text-sm"
                >
                    «{{ order.guest_comment }}»
                </p>
                <a
                    :href="route('waiter.print', order.id)"
                    target="_blank"
                    rel="noopener"
                    class="flex h-11 items-center justify-center gap-2 rounded-md border border-line text-sm font-medium"
                    ><Icon name="print" :size="16" />{{
                        t('cashier.print_bill')
                    }}</a
                >

                <label for="move" class="text-[13px] font-medium">{{
                    t('waiter.move')
                }}</label>
                <template v-if="freeTables.length">
                    <Select
                        id="move"
                        v-model="moveTo"
                        size="lg"
                        :placeholder="t('waiter.pick_table')"
                    >
                        <option
                            v-for="tb in freeTables"
                            :key="tb.id"
                            :value="tb.id"
                        >
                            {{ t('tables.table') }} {{ tb.number }} ·
                            {{ tb.seats }} {{ t('tables.seats_short') }}
                        </option>
                    </Select>
                    <Btn
                        variant="secondary"
                        :disabled="!moveTo"
                        @click="move"
                        >{{ t('waiter.move_do') }}</Btn
                    >
                </template>
                <p v-else class="text-sm text-ink-3">
                    {{ t('waiter.no_free_tables') }}
                </p>

                <template v-if="waiters.length">
                    <label for="transfer" class="text-[13px] font-medium">{{
                        t('waiter.transfer')
                    }}</label>
                    <Select
                        id="transfer"
                        v-model="transferTo"
                        size="lg"
                        :placeholder="t('waiter.pick_waiter')"
                    >
                        <option v-for="w in waiters" :key="w.id" :value="w.id">
                            {{ w.name }}
                        </option>
                    </Select>
                    <Btn :disabled="!transferTo" @click="transfer">{{
                        t('waiter.transfer_do')
                    }}</Btn>
                </template>
            </div>
        </Modal>
    </WaiterLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { money, phone, time } from '@/utils/format';
import { useRealtime } from '@/composables/useRealtime';
import Select from '@/Components/Select.vue';

const props = defineProps({
    date: { type: String, required: true },
    bookings: { type: Array, default: () => [] },
    menu: { type: Array, default: null },
});

const { t, loc } = useI18n();
const page = usePage();
const canWaiter = computed(() =>
    page.props.auth.user?.permissions?.includes('area_waiter'),
);

useRealtime('bookings', 'bookings.changed', () =>
    router.reload({ only: ['bookings'], preserveScroll: true }),
);

// --- День ----------------------------------------------------------------
const iso = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const today = iso(new Date());
function shift(days) {
    const [y, m, d] = props.date.split('-').map(Number);
    goDay(iso(new Date(y, m - 1, d + days)));
}
const goDay = (d) =>
    router.get(route('bookings.index'), { date: d }, { preserveScroll: true });
const dayLabel = computed(() => {
    const [y, m, d] = props.date.split('-');
    return `${d}.${m}.${y}`;
});

const counts = computed(() => {
    const active = props.bookings.filter((b) =>
        ['new', 'confirmed', 'arrived'].includes(b.status),
    );
    return {
        total: active.length,
        guests: active.reduce((s, b) => s + b.guests_count, 0),
        new: props.bookings.filter((b) => b.status === 'new').length,
        noTable: active.filter((b) => !b.table && b.status !== 'arrived')
            .length,
    };
});

const tone = (s) =>
    ({
        new: 'ochre',
        confirmed: 'brand',
        arrived: 'ok',
        cancelled: 'muted',
        no_show: 'danger',
    })[s] ?? 'neutral';
const preorderTotal = (b) =>
    b.items.reduce((s, i) => s + i.price * i.quantity, 0);

// --- Статусы ---------------------------------------------------------------
function setStatus(b, status) {
    let reason = null;
    if (status === 'cancelled') {
        reason = window.prompt(t('bookings.cancel_reason'));
        if (reason === null) return;
    }
    router.post(
        route('bookings.status', b.id),
        { status, reason },
        { preserveScroll: true },
    );
}

// --- Форма -----------------------------------------------------------------
const editing = ref(null); // 'new' | booking
const form = useForm({
    date: props.date,
    time: '19:00',
    guests_count: 2,
    name: '',
    phone: '',
    comment: '',
    dining_table_id: null,
    confirm: true,
    items: [],
});
const freeTables = ref([]);
const loadingTables = ref(false);

function open(b = null) {
    editing.value = b ?? 'new';
    form.clearErrors();
    const start = b ? new Date(b.starts_at) : null;
    form.date = b ? iso(start) : props.date;
    form.time = b
        ? `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`
        : '19:00';
    form.guests_count = b?.guests_count ?? 2;
    form.name = b?.guest?.name ?? '';
    form.phone = b ? phone(b.guest?.phone) : '';
    form.comment = b?.comment ?? '';
    form.dining_table_id = b?.table?.id ?? null;
    form.confirm = true;
    form.items = (b?.items ?? []).map((i) => ({
        dish_id: i.dish_id,
        quantity: i.quantity,
    }));
    if (!props.menu) router.reload({ only: ['menu'], preserveScroll: true });
    loadTables();
}

// Свободные на это время столы: стол текущей брони сервер тоже вернёт (исключение по id).
async function loadTables() {
    if (!form.date || !form.time) return;
    loadingTables.value = true;
    try {
        const { data } = await axios.get(route('bookings.free-tables'), {
            params: {
                date: form.date,
                time: form.time,
                booking: editing.value?.id,
            },
        });
        freeTables.value = data;
        if (
            form.dining_table_id &&
            !data.some((tb) => tb.id === form.dining_table_id)
        )
            form.dining_table_id = null;
    } finally {
        loadingTables.value = false;
    }
}
watch(() => [form.date, form.time], loadTables);

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

function save() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value === 'new') form.post(route('bookings.store'), opts);
    else form.put(route('bookings.update', editing.value.id), opts);
}
</script>

<template>
    <AdminLayout :title="t('nav.bookings')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('nav.bookings') }}
                </h1>
                <Btn @click="open()"
                    ><Icon name="plus" />{{ t('bookings.add') }}</Btn
                >
            </div>

            <!-- День -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-md border border-line bg-surface hover:bg-surface-2"
                    :aria-label="t('bookings.prev_day')"
                    @click="shift(-1)"
                >
                    ‹
                </button>
                <div class="w-44">
                    <TextInput
                        type="date"
                        :model-value="date"
                        mono
                        :aria-label="t('bookings.day')"
                        @change="goDay($event.target.value)"
                    />
                </div>
                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-md border border-line bg-surface hover:bg-surface-2"
                    :aria-label="t('bookings.next_day')"
                    @click="shift(1)"
                >
                    ›
                </button>
                <button
                    v-if="date !== today"
                    type="button"
                    class="h-9 rounded-md border border-line bg-surface px-3 text-sm hover:bg-surface-2"
                    @click="goDay(today)"
                >
                    {{ t('bookings.today') }}
                </button>
                <span class="ml-2 text-sm text-ink-2"
                    >{{ dayLabel }} ·
                    {{
                        t('bookings.summary', {
                            n: counts.total,
                            g: counts.guests,
                        })
                    }}</span
                >
                <Badge v-if="counts.new" tone="ochre">{{
                    t('bookings.need_confirm', { n: counts.new })
                }}</Badge>
                <Badge v-if="counts.noTable" tone="danger">{{
                    t('bookings.need_table', { n: counts.noTable })
                }}</Badge>
            </div>

            <!-- Список на день -->
            <div class="flex flex-col gap-2.5">
                <article
                    v-for="b in bookings"
                    :key="b.id"
                    class="grid gap-x-4 gap-y-2 rounded-lg border bg-surface p-4 sm:grid-cols-[92px_1fr_auto]"
                    :class="[
                        ['cancelled', 'no_show'].includes(b.status)
                            ? 'border-line opacity-60'
                            : 'border-line',
                        b.status === 'new' ? 'border-ochre' : '',
                    ]"
                >
                    <div class="flex flex-col">
                        <span class="font-mono text-xl font-semibold">{{
                            time(b.starts_at)
                        }}</span>
                        <span class="font-mono text-xs text-ink-3"
                            >–{{ time(b.ends_at) }}</span
                        >
                    </div>

                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="b.guest"
                                :href="route('guests.show', b.guest.id)"
                                class="font-semibold hover:underline"
                                >{{ b.guest.name }}</Link
                            >
                            <a
                                v-if="b.guest"
                                :href="`tel:${b.guest.phone}`"
                                class="font-mono text-sm text-ink-2"
                                >{{ phone(b.guest.phone) }}</a
                            >
                            <Badge :tone="tone(b.status)">{{
                                t(`bookings.status_${b.status}`)
                            }}</Badge>
                            <Badge v-if="b.source === 'guest'" tone="outline">{{
                                t('bookings.from_guest')
                            }}</Badge>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-2"
                        >
                            <span>{{
                                t('bookings.guests_n', { n: b.guests_count })
                            }}</span>
                            <span v-if="b.table" class="font-medium text-ink"
                                >{{ t('tables.table') }} {{ b.table.number }}
                                <span
                                    v-if="b.guests_count > b.table.seats"
                                    class="text-danger"
                                    >({{
                                        t('bookings.seats_warn', {
                                            n: b.table.seats,
                                        })
                                    }})</span
                                ></span
                            >
                            <span
                                v-else-if="
                                    ['new', 'confirmed'].includes(b.status)
                                "
                                class="text-danger"
                                >{{ t('bookings.no_table') }}</span
                            >
                            <span v-if="b.items.length"
                                >{{ t('bookings.preorder') }}:
                                {{ b.items.length }} ·
                                <span class="font-mono">{{
                                    money(preorderTotal(b))
                                }}</span></span
                            >
                        </div>
                        <p v-if="b.comment" class="text-sm">
                            «{{ b.comment }}»
                        </p>
                        <p
                            v-if="b.guest?.comment"
                            class="text-xs text-ochre-ink"
                        >
                            {{ t('guests.comment') }}: {{ b.guest.comment }}
                        </p>
                        <p v-if="b.cancel_reason" class="text-xs text-ink-3">
                            {{ b.cancel_reason }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-start gap-1.5 sm:justify-end"
                    >
                        <Btn
                            v-if="b.next.includes('arrived')"
                            size="sm"
                            :disabled="!b.table"
                            :title="!b.table ? t('bookings.no_table') : ''"
                            @click="setStatus(b, 'arrived')"
                            >{{ t('bookings.do_arrived') }}</Btn
                        >
                        <Btn
                            v-if="b.next.includes('confirmed')"
                            size="sm"
                            variant="secondary"
                            @click="setStatus(b, 'confirmed')"
                            >{{ t('bookings.do_confirm') }}</Btn
                        >
                        <Btn
                            v-if="['new', 'confirmed'].includes(b.status)"
                            size="sm"
                            variant="ghost"
                            @click="open(b)"
                            ><Icon name="edit" :size="14"
                        /></Btn>
                        <Btn
                            v-if="b.next.includes('no_show')"
                            size="sm"
                            variant="ghost"
                            @click="setStatus(b, 'no_show')"
                            >{{ t('bookings.do_no_show') }}</Btn
                        >
                        <Btn
                            v-if="b.next.includes('cancelled')"
                            size="sm"
                            variant="ghost"
                            @click="setStatus(b, 'cancelled')"
                            >{{ t('bookings.do_cancel') }}</Btn
                        >
                        <Link
                            v-if="b.order_id && canWaiter"
                            :href="route('waiter.order', b.order_id)"
                            class="inline-flex h-8 items-center rounded-md border border-line px-3 text-[13px]"
                            >{{ t('hall.open_order') }}</Link
                        >
                    </div>
                </article>

                <p
                    v-if="!bookings.length"
                    class="rounded-lg border border-dashed border-ink-3 bg-surface p-10 text-center text-sm text-ink-2"
                >
                    {{ t('bookings.empty') }}
                </p>
            </div>
        </div>

        <!-- Создать / изменить бронь -->
        <Modal
            :show="editing !== null"
            :title="editing === 'new' ? t('bookings.add') : t('bookings.edit')"
            @close="editing = null"
        >
            <form
                id="booking-form"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                @submit.prevent="save"
            >
                <Field
                    :label="t('bookings.date')"
                    for="b-date"
                    :error="form.errors.date"
                    ><TextInput
                        id="b-date"
                        v-model="form.date"
                        type="date"
                        mono
                /></Field>
                <Field
                    :label="t('bookings.time')"
                    for="b-time"
                    :error="form.errors.time"
                    ><TextInput
                        id="b-time"
                        v-model="form.time"
                        type="time"
                        step="900"
                        mono
                /></Field>
                <Field
                    :label="t('guests.phone')"
                    for="b-phone"
                    :error="form.errors.phone || form.errors.guest_phone"
                    ><TextInput
                        id="b-phone"
                        v-model="form.phone"
                        mono
                        inputmode="tel"
                        placeholder="+993 6X XXXXXX"
                        type="tel"
                        autocomplete="off"
                        maxlength="32"
                /></Field>
                <Field
                    :label="t('guests.name')"
                    for="b-name"
                    :error="form.errors.name"
                    ><TextInput
                        id="b-name"
                        v-model="form.name"
                        :placeholder="t('guests.name_ph')"
                        maxlength="120"
                        autocomplete="off"
                /></Field>
                <Field
                    :label="t('bookings.guests_count')"
                    for="b-gc"
                    :error="form.errors.guests_count"
                    ><TextInput
                        id="b-gc"
                        v-model.number="form.guests_count"
                        type="number"
                        min="1"
                        mono
                        :placeholder="t('bookings.guests_count_ph')"
                        max="100"
                        inputmode="numeric"
                /></Field>
                <Field
                    :label="t('tables.table')"
                    for="b-table"
                    :error="form.errors.dining_table_id"
                >
                    <Select
                        id="b-table"
                        v-model="form.dining_table_id"
                        :disabled="loadingTables"
                    >
                        <option :value="null">
                            {{ t('bookings.table_later') }}
                        </option>
                        <option
                            v-for="tb in freeTables"
                            :key="tb.id"
                            :value="tb.id"
                        >
                            {{ t('tables.table') }} {{ tb.number }} ·
                            {{ tb.seats }} {{ t('tables.seats_short') }} ·
                            {{ tb.hall }}
                        </option>
                    </Select>
                </Field>
                <div class="sm:col-span-2">
                    <Field
                        :label="t('guests.comment')"
                        for="b-comment"
                        :error="form.errors.comment"
                        ><TextInput
                            id="b-comment"
                            v-model="form.comment"
                            :placeholder="t('bookings.comment_ph')"
                            maxlength="500"
                    /></Field>
                </div>

                <!-- Предзаказ -->
                <div
                    class="sm:col-span-2 flex flex-col gap-2 rounded-md border border-line p-3"
                >
                    <span class="text-[13px] font-medium">{{
                        t('bookings.preorder')
                    }}</span>
                    <div class="flex gap-2">
                        <Select
                            v-model="pick"
                            :aria-label="t('bookings.add_dish')"
                            class="flex-1"
                            :placeholder="menu ? t('bookings.add_dish') : '…'"
                        >
                            <option
                                v-for="d in dishes"
                                :key="d.id"
                                :value="d.id"
                                :disabled="d.stopped"
                            >
                                {{ d.category }} · {{ loc(d, 'name') }} ·
                                {{ money(d.price) }}
                            </option>
                        </Select>
                        <Btn
                            variant="secondary"
                            :disabled="!pick"
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
                    <span
                        v-if="form.items.length"
                        class="text-right font-mono text-sm"
                        >{{ money(itemsTotal, true) }}</span
                    >
                    <span
                        v-if="form.errors.items"
                        class="text-xs text-danger"
                        >{{ form.errors.items }}</span
                    >
                </div>

                <label
                    v-if="editing === 'new'"
                    class="sm:col-span-2 flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="form.confirm"
                        type="checkbox"
                        class="size-4 accent-[var(--color-brand)]"
                    />
                    {{ t('bookings.confirm_now') }}
                </label>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="editing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="booking-form"
                    :loading="form.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

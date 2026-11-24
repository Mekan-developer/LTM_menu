<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import HallCanvas from '@/Components/Hall/HallCanvas.vue';
import HallEditor from '@/Components/Hall/HallEditor.vue';
import {
    ELEMENT_PRESETS,
    GRID,
    TABLE_PRESETS,
    box,
    clamp,
    overlaps,
    sinceLabel,
} from '@/Components/Hall/hall';
import { useI18n } from '@/i18n';
import { money, time } from '@/utils/format';
import { useRealtime } from '@/composables/useRealtime';

const props = defineProps({
    halls: { type: Array, default: () => [] },
    hallId: { type: Number, default: null },
    board: { type: Object, required: true },
    detail: { type: Object, default: null },
    waiters: { type: Array, default: () => [] },
    can: { type: Object, required: true },
});

const { t, loc } = useI18n();
const hall = computed(() => props.halls.find((h) => h.id === props.hallId));
const query = new URLSearchParams(window.location.search);
const mode = ref(
    props.can.edit && query.get('mode') === 'edit' ? 'edit' : 'work',
);

useRealtime('hall', 'hall.changed', (payload) => {
    if (payload && payload.hall_id !== props.hallId) {
        router.reload({ only: ['halls'] });
        return;
    }
    router.reload({ only: ['board', 'halls', 'detail'] });
});

function goHall(id) {
    router.get(
        route('hall.index'),
        { hall: id, ...(mode.value === 'edit' ? { mode: 'edit' } : {}) },
        { preserveState: true, preserveScroll: true },
    );
}

// ===================== Режим «Работа» =====================
const selectedId = ref(Number(query.get('table')) || null);
const picking = ref(false);

const counts = computed(() => {
    const c = { free: 0, occupied: 0, bill: 0, qr: 0 };
    for (const tb of props.board.tables) {
        c[tb.status] = (c[tb.status] ?? 0) + 1;
        if (tb.qr_pending) c.qr++;
    }
    return c;
});
const selected = computed(() =>
    props.board.tables.find((tb) => tb.id === selectedId.value),
);
const freeTables = computed(() =>
    props.board.tables.filter((tb) => tb.status === 'free'),
);

function loadDetail(id) {
    selectedId.value = id;
    router.get(
        route('hall.index'),
        { hall: props.hallId, ...(id ? { table: id } : {}) },
        {
            only: ['detail'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onSelect(tb) {
    if (picking.value) {
        if (tb.status === 'free') moveTo(tb.id);
        return;
    }
    loadDetail(selectedId.value === tb.id ? null : tb.id);
}
const close = () => {
    picking.value = false;
    loadDetail(null);
};

const order = computed(() => props.detail?.order ?? null);
const items = computed(() =>
    (order.value?.items ?? []).filter((i) => i.status !== 'removed'),
);
const itemTone = (s) =>
    ({
        pending: 'accent',
        sent: 'ochre',
        ready: 'ok',
        served: 'muted',
        draft: 'outline',
    })[s] ?? 'neutral';

const openTable = () => router.post(route('waiter.open', selectedId.value));
const requestBill = () =>
    router.post(
        route('waiter.bill', order.value.id),
        { stay: 1 },
        { preserveScroll: true },
    );

const moveTarget = ref(null);
function moveTo(tableId) {
    router.post(
        route('waiter.move', order.value.id),
        { table_id: tableId },
        {
            preserveScroll: true,
            onSuccess: () => {
                picking.value = false;
                moveTarget.value = null;
                loadDetail(tableId);
            },
        },
    );
}

const transferTo = ref(null);
const transfer = () =>
    router.post(
        route('waiter.transfer', order.value.id),
        { waiter_id: transferTo.value, stay: 1 },
        { preserveScroll: true, onSuccess: () => (transferTo.value = null) },
    );

const qrImage = ref(null);
async function toggleQr() {
    if (qrImage.value) {
        qrImage.value = null;
        return;
    }
    qrImage.value = await QRCode.toDataURL(props.detail.qr_url, {
        margin: 1,
        width: 240,
    });
}
watch(selectedId, () => (qrImage.value = null));

// ===================== Режим «Редактор» =====================
let seq = 0;
const toDraft = () => ({
    tables: props.board.tables.map((tb) => ({
        key: `t${tb.id}`,
        id: tb.id,
        number: tb.number,
        seats: tb.seats,
        shape: tb.shape,
        pos_x: tb.pos_x,
        pos_y: tb.pos_y,
        width: tb.width,
        height: tb.height,
        rotation: tb.rotation,
        busy: tb.status !== 'free',
        qr_url: tb.qr_url,
    })),
    elements: props.board.elements.map((e) => ({ ...e, key: `e${e.id}` })),
});
const draft = reactive(toDraft());
const snapshot = ref('');
const sel = ref(null);
const editError = ref('');
const serverErrors = ref({});

const payload = () => ({
    tables: draft.tables.map(({ key, busy, qr_url, ...rest }) => rest),
    elements: draft.elements.map(({ key, ...rest }) => rest),
});
function resetDraft() {
    const d = toDraft();
    draft.tables = d.tables;
    draft.elements = d.elements;
    snapshot.value = JSON.stringify(payload());
    sel.value = null;
    editError.value = '';
    serverErrors.value = {};
}
resetDraft();
watch(() => props.hallId, resetDraft);
// После сохранения сервер присылает новую доску — снимок обновляем.
watch(
    () => props.board,
    () => {
        if (!dirty.value) resetDraft();
    },
);

const dirty = computed(() => JSON.stringify(payload()) !== snapshot.value);

// Пересекающиеся столы подсвечиваются красным, сохранить нельзя.
const conflicts = computed(() => {
    const set = new Set();
    const list = draft.tables;
    for (let i = 0; i < list.length; i++)
        for (let j = i + 1; j < list.length; j++)
            if (overlaps(box(list[i]), box(list[j]))) {
                set.add(list[i].key);
                set.add(list[j].key);
            }
    return set;
});

const find = (key) =>
    draft.tables.find((x) => x.key === key) ??
    draft.elements.find((x) => x.key === key);
const selTable = computed(() => draft.tables.find((x) => x.key === sel.value));
const selElement = computed(() =>
    draft.elements.find((x) => x.key === sel.value),
);

function patch(key, changes) {
    const o = find(key);
    if (o) Object.assign(o, changes);
}

// Первое свободное место на сетке для нового объекта.
function freeSpot(w, h) {
    const H = hall.value;
    const taken = [...draft.tables, ...draft.elements].map(box);
    for (let y = GRID * 2; y + h <= H.height; y += GRID)
        for (let x = GRID * 2; x + w <= H.width; x += GRID) {
            const b = {
                x: x - GRID,
                y: y - GRID,
                w: w + 2 * GRID,
                h: h + 2 * GRID,
            };
            if (!taken.some((o) => overlaps(b, o))) return [x, y];
        }
    return [0, 0];
}
function nextNumber() {
    const nums = draft.tables
        .map((x) => parseInt(x.number, 10))
        .filter((n) => !Number.isNaN(n));
    return String((nums.length ? Math.max(...nums) : 0) + 1);
}
function addTable(kind) {
    const p = TABLE_PRESETS[kind];
    const [x, y] = freeSpot(p.width, p.height);
    const key = `n${++seq}`;
    draft.tables.push({
        key,
        id: null,
        number: nextNumber(),
        rotation: 0,
        pos_x: x,
        pos_y: y,
        busy: false,
        qr_url: null,
        ...p,
    });
    sel.value = key;
}
function addElement(type) {
    const p = ELEMENT_PRESETS[type];
    const [x, y] = freeSpot(p.width, p.height);
    const key = `m${++seq}`;
    draft.elements.push({
        key,
        id: null,
        type,
        label_tm: null,
        label_ru: null,
        pos_x: x,
        pos_y: y,
        ...p,
    });
    sel.value = key;
}

function rotate() {
    const o = selTable.value;
    if (!o) return;
    o.rotation = o.rotation === 90 ? 0 : 90;
    const b = box(o);
    o.pos_x = clamp(o.pos_x, 0, hall.value.width - b.w);
    o.pos_y = clamp(o.pos_y, 0, hall.value.height - b.h);
}

function removeSelected() {
    editError.value = '';
    if (selTable.value) {
        if (selTable.value.busy) {
            editError.value = t('hall.busy_delete', {
                n: selTable.value.number,
            });
            return;
        }
        draft.tables = draft.tables.filter((x) => x.key !== sel.value);
    } else if (selElement.value) {
        draft.elements = draft.elements.filter((x) => x.key !== sel.value);
    }
    sel.value = null;
}

// Стрелки — на 20 px (Shift — на 1 px), Delete — удалить.
function onKey(e) {
    if (mode.value !== 'edit' || !sel.value) return;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
    const o = find(sel.value);
    if (!o) return;
    const step = e.shiftKey ? 1 : GRID;
    const b = box(o);
    const moves = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };
    if (moves[e.key]) {
        e.preventDefault();
        const [dx, dy] = moves[e.key];
        o.pos_x = clamp(o.pos_x + dx, 0, hall.value.width - b.w);
        o.pos_y = clamp(o.pos_y + dy, 0, hall.value.height - b.h);
    } else if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        removeSelected();
    } else if (e.key === 'Escape') sel.value = null;
}

// Предупреждение о несохранённых изменениях.
let saving = false;
const beforeUnload = (e) => {
    if (mode.value === 'edit' && dirty.value) {
        e.preventDefault();
        e.returnValue = '';
    }
};
let offBefore = null;
onMounted(() => {
    window.addEventListener('keydown', onKey);
    window.addEventListener('beforeunload', beforeUnload);
    offBefore = router.on('before', (event) => {
        const visit = event.detail.visit;
        const partial = visit.only?.length > 0;
        if (saving || partial || mode.value !== 'edit' || !dirty.value) return;
        if (!window.confirm(t('hall.unsaved'))) event.preventDefault();
    });
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', beforeUnload);
    offBefore?.();
});

const savingNow = ref(false);
function save() {
    saving = true;
    savingNow.value = true;
    serverErrors.value = {};
    router.put(route('halls.layout', props.hallId), payload(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            snapshot.value = '';
            resetDraft();
        },
        onError: (errors) => (serverErrors.value = errors),
        onFinish: () => {
            saving = false;
            savingNow.value = false;
        },
    });
}
const serverErrorList = computed(() => [
    ...new Set(Object.values(serverErrors.value)),
]);

function setMode(m) {
    if (m === mode.value) return;
    if (
        mode.value === 'edit' &&
        dirty.value &&
        !window.confirm(t('hall.unsaved'))
    )
        return;
    if (m === 'edit') resetDraft();
    picking.value = false;
    mode.value = m;
    const url = new URL(window.location.href);
    if (m === 'edit') url.searchParams.set('mode', 'edit');
    else url.searchParams.delete('mode');
    window.history.replaceState(window.history.state, '', url);
}

// QR выбранного стола в редакторе
const editQr = ref(null);
watch(sel, async () => {
    editQr.value = null;
    if (selTable.value?.qr_url)
        editQr.value = await QRCode.toDataURL(selTable.value.qr_url, {
            margin: 1,
            width: 600,
        });
});
function downloadQr() {
    const a = document.createElement('a');
    a.href = editQr.value;
    a.download = `stol-${selTable.value.number}.png`;
    a.click();
}
function regenerateQr() {
    if (
        !window.confirm(
            t('tables.confirm_regenerate', { n: selTable.value.number }),
        )
    )
        return;
    saving = true;
    router.post(
        route('tables.regenerate', selTable.value.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (saving = false),
        },
    );
}

// ===================== Залы =====================
const hallModal = ref(null); // 'new' | 'edit' | null
const hallForm = useForm({
    name_tm: '',
    name_ru: '',
    width: 1000,
    height: 640,
});
function openHall(kind) {
    hallModal.value = kind;
    hallForm.clearErrors();
    const h = kind === 'edit' ? hall.value : null;
    hallForm.name_tm = h?.name_tm ?? '';
    hallForm.name_ru = h?.name_ru ?? '';
    hallForm.width = h?.width ?? 1000;
    hallForm.height = h?.height ?? 640;
}
function saveHall() {
    saving = true;
    const opts = {
        preserveScroll: true,
        onSuccess: () => (hallModal.value = null),
        onFinish: () => (saving = false),
    };
    if (hallModal.value === 'new') hallForm.post(route('halls.store'), opts);
    else hallForm.put(route('halls.update', props.hallId), opts);
}
function deleteHall() {
    if (
        !window.confirm(
            t('hall.confirm_delete', { name: loc(hall.value, 'name') }),
        )
    )
        return;
    saving = true;
    router.delete(route('halls.destroy', props.hallId), {
        onFinish: () => (saving = false),
    });
}
</script>

<template>
    <AdminLayout :title="t('nav.hall')" compact>
        <div class="flex flex-col gap-4">
            <!-- Шапка: заголовок и переключатель режимов -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1
                    class="text-[28px] leading-[34px] font-semibold tracking-tight"
                >
                    {{ t('nav.hall') }}
                </h1>
                <div
                    v-if="can.edit"
                    class="flex overflow-hidden rounded-md border border-line text-sm"
                    role="tablist"
                >
                    <button
                        v-for="m in ['work', 'edit']"
                        :key="m"
                        type="button"
                        role="tab"
                        :aria-selected="mode === m"
                        class="h-9 px-4"
                        :class="
                            mode === m
                                ? 'bg-ink font-semibold text-paper'
                                : 'bg-surface text-ink-2 hover:bg-surface-2'
                        "
                        @click="setMode(m)"
                    >
                        {{ t(`hall.mode_${m}`) }}
                    </button>
                </div>
            </div>

            <!-- Вкладки залов -->
            <div class="flex flex-wrap items-center gap-2 border-b border-line">
                <button
                    v-for="h in halls"
                    :key="h.id"
                    type="button"
                    class="-mb-px flex h-10 items-center gap-2 border-b-2 px-1 text-sm"
                    :class="
                        h.id === hallId
                            ? 'border-brand font-semibold'
                            : 'border-transparent text-ink-2 hover:text-ink'
                    "
                    @click="goHall(h.id)"
                >
                    {{ loc(h, 'name') }}
                    <span
                        class="rounded-sm bg-surface-2 px-1.5 font-mono text-[11px] text-ink-2"
                        >{{ h.tables_count }}</span
                    >
                </button>
                <template v-if="mode === 'edit'">
                    <button
                        type="button"
                        class="mb-1 ml-2 inline-flex h-8 items-center gap-1 rounded-sm border border-line px-2.5 text-[13px] hover:bg-surface-2"
                        @click="openHall('new')"
                    >
                        <Icon name="plus" :size="14" />{{ t('hall.add_hall') }}
                    </button>
                    <button
                        v-if="hall"
                        type="button"
                        class="mb-1 inline-flex h-8 items-center gap-1 rounded-sm border border-line px-2.5 text-[13px] hover:bg-surface-2"
                        @click="openHall('edit')"
                    >
                        <Icon name="edit" :size="14" />{{
                            t('hall.hall_settings')
                        }}
                    </button>
                </template>
            </div>

            <p
                v-if="!hall"
                class="rounded-lg border border-dashed border-ink-3 bg-surface p-10 text-center text-sm text-ink-2"
            >
                {{ t('hall.no_halls') }}
            </p>

            <!-- ============ РАБОТА ============ -->
            <template v-else-if="mode === 'work'">
                <div
                    class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] text-ink-2"
                >
                    <span class="flex items-center gap-2"
                        ><span
                            class="size-4 rounded-sm border-2 border-dashed border-free bg-surface"
                        />{{ t('hall.status_free') }}
                        <b class="font-mono text-ink">{{
                            counts.free
                        }}</b></span
                    >
                    <span class="flex items-center gap-2"
                        ><span class="size-4 rounded-sm bg-brand" />{{
                            t('hall.status_occupied')
                        }}
                        <b class="font-mono text-ink">{{
                            counts.occupied
                        }}</b></span
                    >
                    <span class="flex items-center gap-2"
                        ><span class="size-4 rounded-sm bg-accent" />{{
                            t('hall.status_bill')
                        }}
                        <b class="font-mono text-ink">{{
                            counts.bill
                        }}</b></span
                    >
                    <span class="flex items-center gap-2"
                        ><span
                            class="rounded-sm bg-accent px-1 font-mono text-[10px] font-bold text-accent-ink"
                            >QR</span
                        >{{ t('hall.qr') }}
                        <b class="font-mono text-ink">{{ counts.qr }}</b></span
                    >
                    <span class="flex items-center gap-2"
                        ><span class="size-3 rounded-full bg-ok" />{{
                            t('hall.ready')
                        }}</span
                    >
                </div>

                <p
                    v-if="picking"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-brand bg-brand-soft px-3 py-2 text-sm"
                    role="status"
                >
                    {{ t('hall.pick_free', { n: selected?.number }) }}
                    <Btn variant="ghost" size="sm" @click="picking = false">{{
                        t('common.cancel')
                    }}</Btn>
                </p>

                <div class="flex items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <HallCanvas
                            :hall="hall"
                            :tables="board.tables"
                            :elements="board.elements"
                            :selected-id="selectedId"
                            :picking="picking"
                            @select="onSelect"
                        />
                    </div>

                    <!-- Панель стола: справа на компьютере, нижняя шторка на телефоне -->
                    <aside
                        v-if="selected"
                        class="fixed inset-x-0 bottom-0 z-30 max-h-[72vh] overflow-y-auto rounded-t-2xl border-t border-line bg-surface p-4 shadow-[0_-12px_32px_-12px_rgb(0_0_0/0.3)] lg:sticky lg:top-4 lg:z-auto lg:max-h-[calc(100vh-120px)] lg:w-80 lg:shrink-0 lg:rounded-lg lg:border lg:shadow-none"
                        :aria-label="`${t('tables.table')} ${selected.number}`"
                    >
                        <div
                            class="mb-3 flex items-start justify-between gap-2"
                        >
                            <div class="flex flex-col">
                                <span class="font-mono text-xs text-ink-3"
                                    >{{ loc(hall, 'name') }} ·
                                    {{ selected.seats }}
                                    {{ t('tables.seats_short') }}</span
                                >
                                <span class="text-xl font-semibold"
                                    >{{ t('tables.table') }}
                                    {{ selected.number }}</span
                                >
                            </div>
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-md border border-line"
                                :aria-label="t('common.close')"
                                @click="close"
                            >
                                ✕
                            </button>
                        </div>

                        <div
                            v-if="!detail || detail.id !== selected.id"
                            class="flex flex-col gap-2"
                            aria-busy="true"
                        >
                            <span
                                class="h-4 w-2/3 animate-pulse rounded-sm bg-surface-2"
                            />
                            <span
                                class="h-4 w-1/2 animate-pulse rounded-sm bg-surface-2"
                            />
                        </div>

                        <!-- Свободный стол -->
                        <div
                            v-else-if="selected.status === 'free'"
                            class="flex flex-col gap-3"
                        >
                            <Badge tone="outline">{{
                                t('hall.status_free')
                            }}</Badge>
                            <Btn
                                v-if="can.work"
                                size="lg"
                                block
                                @click="openTable"
                                >{{ t('waiter.open_table') }}</Btn
                            >
                        </div>

                        <!-- Чужой стол без права «Передача столов» -->
                        <p
                            v-else-if="!detail.details"
                            class="text-sm text-ink-2"
                        >
                            {{ t('hall.not_yours') }}
                        </p>

                        <!-- Занятый стол -->
                        <div v-else-if="order" class="flex flex-col gap-3">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <span class="text-ink-2">{{
                                    t('hall.opened')
                                }}</span>
                                <span class="text-right font-mono"
                                    >{{ time(order.opened_at) }} ·
                                    {{ sinceLabel(order.opened_at) }}</span
                                >
                                <span class="text-ink-2">{{
                                    t('hall.total')
                                }}</span>
                                <span
                                    class="text-right font-mono font-semibold num"
                                    >{{ money(order.total, true) }}</span
                                >
                                <span class="text-ink-2">{{
                                    t('reports.waiter')
                                }}</span>
                                <span class="text-right">{{
                                    order.waiter?.name ?? t('waiter.no_waiter')
                                }}</span>
                            </div>
                            <Badge
                                v-if="order.status === 'bill_requested'"
                                tone="accent"
                                >{{ t('hall.status_bill') }}</Badge
                            >

                            <ul class="flex flex-col border-y border-line py-1">
                                <li
                                    v-for="i in items"
                                    :key="i.id"
                                    class="flex items-center justify-between gap-2 py-1.5 text-sm"
                                >
                                    <span class="min-w-0 truncate"
                                        ><span class="font-mono font-semibold"
                                            >{{ i.quantity }}×</span
                                        >
                                        {{ loc(i, 'name') }}</span
                                    >
                                    <Badge :tone="itemTone(i.status)">{{
                                        t(`item_status.${i.status}`)
                                    }}</Badge>
                                </li>
                                <li
                                    v-if="!items.length"
                                    class="py-2 text-sm text-ink-3"
                                >
                                    {{ t('hall.no_items') }}
                                </li>
                            </ul>

                            <template v-if="can.work">
                                <Link
                                    :href="route('waiter.order', order.id)"
                                    class="inline-flex h-11 items-center justify-center rounded-md bg-brand text-sm font-semibold text-brand-ink"
                                    >{{ t('hall.open_order') }}</Link
                                >
                                <div class="grid grid-cols-2 gap-2">
                                    <Btn
                                        variant="secondary"
                                        :disabled="
                                            order.status === 'bill_requested'
                                        "
                                        @click="requestBill"
                                        >{{ t('waiter.bill') }}</Btn
                                    >
                                    <Btn
                                        variant="secondary"
                                        :disabled="!freeTables.length"
                                        @click="picking = !picking"
                                        >{{ t('hall.move') }}</Btn
                                    >
                                </div>
                                <div
                                    v-if="picking && freeTables.length"
                                    class="flex gap-2"
                                >
                                    <select
                                        v-model="moveTarget"
                                        class="h-10 min-w-0 flex-1 rounded-md border border-line bg-paper px-2 text-sm"
                                        :aria-label="t('waiter.move')"
                                    >
                                        <option :value="null" disabled>
                                            {{ t('waiter.pick_table') }}
                                        </option>
                                        <option
                                            v-for="f in freeTables"
                                            :key="f.id"
                                            :value="f.id"
                                        >
                                            {{ t('tables.table') }}
                                            {{ f.number }} · {{ f.seats }}
                                            {{ t('tables.seats_short') }}
                                        </option>
                                    </select>
                                    <Btn
                                        :disabled="!moveTarget"
                                        @click="moveTo(moveTarget)"
                                        >{{ t('waiter.move_do') }}</Btn
                                    >
                                </div>
                                <div
                                    v-if="can.transfer && waiters.length"
                                    class="flex gap-2"
                                >
                                    <select
                                        v-model="transferTo"
                                        class="h-10 min-w-0 flex-1 rounded-md border border-line bg-paper px-2 text-sm"
                                        :aria-label="t('waiter.transfer')"
                                    >
                                        <option :value="null" disabled>
                                            {{ t('waiter.transfer') }}
                                        </option>
                                        <option
                                            v-for="w in waiters"
                                            :key="w.id"
                                            :value="w.id"
                                        >
                                            {{ w.name }}
                                        </option>
                                    </select>
                                    <Btn
                                        variant="secondary"
                                        :disabled="!transferTo"
                                        @click="transfer"
                                        >{{ t('waiter.transfer_do') }}</Btn
                                    >
                                </div>
                            </template>
                        </div>

                        <!-- QR стола -->
                        <div
                            v-if="detail && detail.id === selected.id"
                            class="mt-3 border-t border-line pt-3"
                        >
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-2 text-sm text-ink-2 hover:text-ink"
                                @click="toggleQr"
                            >
                                <Icon name="qr" :size="16" />{{
                                    t('hall.table_qr')
                                }}
                            </button>
                            <img
                                v-if="qrImage"
                                :src="qrImage"
                                :alt="`QR ${selected.number}`"
                                class="mx-auto mt-2 size-40 rounded-md border border-line bg-white p-1"
                            />
                        </div>
                    </aside>
                </div>
            </template>

            <!-- ============ РЕДАКТОР ============ -->
            <template v-else>
                <div
                    class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface p-2.5"
                >
                    <span
                        class="px-1 font-mono text-[10px] tracking-[0.08em] text-ink-3 uppercase"
                        >{{ t('hall.add') }}</span
                    >
                    <button
                        v-for="k in ['round', 'square', 'rect']"
                        :key="k"
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-md border border-line bg-paper px-3 text-[13px] hover:bg-surface-2"
                        @click="addTable(k)"
                    >
                        <span
                            class="inline-block border-2 border-ink-3"
                            :class="
                                k === 'round'
                                    ? 'size-4 rounded-full'
                                    : k === 'square'
                                      ? 'size-4 rounded-[3px]'
                                      : 'h-3 w-6 rounded-[3px]'
                            "
                        />{{ t(`hall.add_${k}`) }}
                    </button>
                    <span class="mx-1 h-6 w-px bg-line" />
                    <button
                        v-for="k in ['bar', 'entrance', 'window', 'label']"
                        :key="k"
                        type="button"
                        class="inline-flex h-9 items-center rounded-md border border-line bg-paper px-3 text-[13px] hover:bg-surface-2"
                        @click="addElement(k)"
                    >
                        {{ t(`hall.el_${k}`) }}
                    </button>
                </div>

                <div class="flex flex-col items-start gap-4 lg:flex-row">
                    <div class="w-full min-w-0 flex-1">
                        <HallEditor
                            :hall="hall"
                            :tables="draft.tables"
                            :elements="draft.elements"
                            :selected="sel"
                            :conflicts="conflicts"
                            @select="sel = $event"
                            @patch="patch"
                        />
                        <p class="mt-2 text-xs text-ink-3">
                            {{ t('hall.editor_hint') }}
                        </p>
                    </div>

                    <aside
                        class="flex w-full flex-col gap-4 rounded-lg border border-line bg-surface p-4 lg:sticky lg:top-4 lg:w-80 lg:shrink-0"
                    >
                        <!-- Свойства стола -->
                        <template v-if="selTable">
                            <h2 class="text-base font-semibold">
                                {{ t('tables.table') }} {{ selTable.number }}
                            </h2>
                            <div class="grid grid-cols-2 gap-3">
                                <Field :label="t('tables.number')" for="h-num"
                                    ><TextInput
                                        id="h-num"
                                        v-model="selTable.number"
                                        mono
                                /></Field>
                                <Field :label="t('tables.seats')" for="h-seats"
                                    ><TextInput
                                        id="h-seats"
                                        v-model.number="selTable.seats"
                                        type="number"
                                        min="1"
                                        mono
                                /></Field>
                            </div>
                            <Field :label="t('hall.shape')" for="h-shape">
                                <select
                                    id="h-shape"
                                    v-model="selTable.shape"
                                    class="h-11 w-full rounded-md border border-line bg-paper px-3 text-sm"
                                >
                                    <option
                                        v-for="s in ['round', 'square', 'rect']"
                                        :key="s"
                                        :value="s"
                                    >
                                        {{ t(`hall.shape_${s}`) }}
                                    </option>
                                </select>
                            </Field>
                            <div class="grid grid-cols-2 gap-2">
                                <Btn variant="secondary" @click="rotate"
                                    >↻ 90°</Btn
                                >
                                <Btn variant="danger" @click="removeSelected">{{
                                    t('common.delete')
                                }}</Btn>
                            </div>
                            <p
                                v-if="conflicts.has(selTable.key)"
                                class="text-sm text-danger"
                            >
                                {{ t('hall.overlap_hint') }}
                            </p>

                            <div
                                v-if="selTable.id"
                                class="flex flex-col gap-2 border-t border-line pt-3"
                            >
                                <span class="text-[13px] font-medium">{{
                                    t('hall.table_qr')
                                }}</span>
                                <img
                                    v-if="editQr"
                                    :src="editQr"
                                    :alt="`QR ${selTable.number}`"
                                    class="size-32 rounded-md border border-line bg-white p-1"
                                />
                                <div class="flex flex-wrap gap-2">
                                    <Btn
                                        variant="secondary"
                                        size="sm"
                                        :disabled="!editQr"
                                        @click="downloadQr"
                                        >PNG</Btn
                                    >
                                    <a
                                        :href="
                                            route('tables.print', {
                                                table: selTable.id,
                                            })
                                        "
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex h-8 items-center rounded-md border border-line px-3 text-[13px] hover:bg-surface-2"
                                        >PDF</a
                                    >
                                    <Btn
                                        variant="ghost"
                                        size="sm"
                                        @click="regenerateQr"
                                        >{{ t('tables.regenerate') }}</Btn
                                    >
                                </div>
                            </div>
                            <p
                                v-else
                                class="border-t border-line pt-3 text-xs text-ink-3"
                            >
                                {{ t('hall.qr_after_save') }}
                            </p>
                        </template>

                        <!-- Свойства объекта -->
                        <template v-else-if="selElement">
                            <h2 class="text-base font-semibold">
                                {{ t(`hall.el_${selElement.type}`) }}
                            </h2>
                            <Field :label="t('hall.label_tm')" for="h-ltm"
                                ><TextInput
                                    id="h-ltm"
                                    v-model="selElement.label_tm"
                            /></Field>
                            <Field :label="t('hall.label_ru')" for="h-lru"
                                ><TextInput
                                    id="h-lru"
                                    v-model="selElement.label_ru"
                            /></Field>
                            <Btn variant="danger" @click="removeSelected">{{
                                t('common.delete')
                            }}</Btn>
                        </template>

                        <p v-else class="text-sm text-ink-2">
                            {{ t('hall.select_hint') }}
                        </p>

                        <p
                            v-if="editError"
                            class="text-sm text-danger"
                            role="alert"
                        >
                            {{ editError }}
                        </p>
                        <ul
                            v-if="serverErrorList.length"
                            class="flex flex-col gap-1 rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger"
                            role="alert"
                        >
                            <li v-for="m in serverErrorList" :key="m">
                                {{ m }}
                            </li>
                        </ul>

                        <div
                            class="flex flex-col gap-2 border-t border-line pt-3"
                        >
                            <Btn
                                size="lg"
                                block
                                :disabled="!dirty || conflicts.size > 0"
                                :loading="savingNow"
                                @click="save"
                                >{{ t('hall.save') }}</Btn
                            >
                            <Btn
                                variant="ghost"
                                block
                                :disabled="!dirty"
                                @click="resetDraft"
                                >{{ t('hall.discard') }}</Btn
                            >
                            <span
                                v-if="conflicts.size"
                                class="text-xs text-danger"
                                >{{ t('hall.overlap_block') }}</span
                            >
                        </div>
                    </aside>
                </div>
            </template>
        </div>

        <!-- Зал: создать / переименовать / размер / удалить -->
        <Modal
            :show="hallModal !== null"
            :title="
                hallModal === 'new'
                    ? t('hall.add_hall')
                    : t('hall.hall_settings')
            "
            @close="hallModal = null"
        >
            <form
                id="hall-form"
                class="grid grid-cols-2 gap-3"
                @submit.prevent="saveHall"
            >
                <Field
                    :label="t('hall.name_tm')"
                    for="hf-tm"
                    :error="hallForm.errors.name_tm"
                    ><TextInput id="hf-tm" v-model="hallForm.name_tm"
                /></Field>
                <Field
                    :label="t('hall.name_ru')"
                    for="hf-ru"
                    :error="hallForm.errors.name_ru"
                    ><TextInput id="hf-ru" v-model="hallForm.name_ru"
                /></Field>
                <Field
                    :label="t('hall.width')"
                    for="hf-w"
                    :error="hallForm.errors.width"
                    ><TextInput
                        id="hf-w"
                        v-model.number="hallForm.width"
                        type="number"
                        mono
                /></Field>
                <Field
                    :label="t('hall.height')"
                    for="hf-h"
                    :error="hallForm.errors.height"
                    ><TextInput
                        id="hf-h"
                        v-model.number="hallForm.height"
                        type="number"
                        mono
                /></Field>
                <p
                    v-if="hallForm.errors.hall || $page.props.errors?.hall"
                    class="col-span-2 text-sm text-danger"
                    role="alert"
                >
                    {{ hallForm.errors.hall || $page.props.errors?.hall }}
                </p>
            </form>
            <template #footer>
                <Btn
                    v-if="hallModal === 'edit'"
                    variant="danger"
                    class="mr-auto"
                    @click="deleteHall"
                    >{{ t('common.delete') }}</Btn
                >
                <Btn variant="ghost" @click="hallModal = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="hall-form"
                    :loading="hallForm.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

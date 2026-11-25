<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import Icon from '@/Components/Icon.vue';
import Modal from '@/Components/Modal.vue';
import Select from '@/Components/Select.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { date, time } from '@/utils/format';

const props = defineProps({
    week: { type: String, required: true }, // понедельник, YYYY-MM-DD
    staff: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    unplanned: { type: Array, default: () => [] },
    timesheet: { type: Object, default: null },
});

const { t, loc } = useI18n();
const tab = ref('schedule');

// --- Даты (локально, без часовых поясов браузера) --------------------------
const TZ = 'Asia/Ashgabat';
const ymd = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parse = (s) => {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const addDays = (s, n) => {
    const d = parse(s);
    d.setDate(d.getDate() + n);
    return ymd(d);
};
const today = new Date().toLocaleDateString('sv-SE', { timeZone: TZ });
const days = computed(() =>
    Array.from({ length: 7 }, (_, i) => addDays(props.week, i)),
);
const dayLabel = (s) =>
    parse(s).toLocaleDateString('ru-RU', { weekday: 'short' }).toLowerCase();
const dm = (s) => s.slice(8, 10) + '.' + s.slice(5, 7);
// ISO → значение для <input type="datetime-local"> по времени Ашхабада
const toInput = (iso) =>
    iso
        ? new Date(iso)
              .toLocaleString('sv-SE', { timeZone: TZ })
              .slice(0, 16)
              .replace(' ', 'T')
        : '';
const hm = (min) =>
    `${Math.floor(min / 60)}:${String(min % 60).padStart(2, '0')}`;

const goWeek = (w) =>
    router.get(route('shifts.index'), { week: w }, { preserveScroll: true });

// --- Сетка: смены по сотруднику и дню --------------------------------------
const cell = computed(() => {
    const map = {};
    props.shifts.forEach((s) => {
        (map[`${s.user_id}|${s.date}`] ??= []).push({ kind: 'shift', ...s });
    });
    props.unplanned.forEach((a) => {
        const d = new Date(a.clock_in).toLocaleDateString('sv-SE', {
            timeZone: TZ,
        });
        (map[`${a.user_id}|${d}`] ??= []).push({ kind: 'unplanned', ...a });
    });
    return map;
});
function chipClass(s) {
    if (s.kind === 'unplanned') return 'border-dashed border-ink-3 bg-surface';
    if (s.missed) return 'border-danger bg-danger-soft text-danger';
    if (s.late || s.early) return 'border-ochre bg-ochre-soft text-ochre-ink';
    if (s.attendance && !s.attendance.clock_out)
        return 'border-brand bg-brand-soft';
    if (s.attendance) return 'border-ok bg-ok-soft text-ok';
    return 'border-line bg-surface-2';
}

// --- Смена: добавить / изменить / удалить ----------------------------------
const editing = ref(null); // { shift?, user_id, date }
const shiftForm = useForm({
    user_id: null,
    date: '',
    start: '09:00',
    end: '21:00',
    note: '',
});
const lastTimes = ref({ start: '09:00', end: '21:00' });
function openShift(userId, day, shift = null) {
    editing.value = { shift };
    shiftForm.clearErrors();
    shiftForm.user_id = userId;
    shiftForm.date = day;
    shiftForm.start = shift?.start ?? lastTimes.value.start;
    shiftForm.end = shift?.end ?? lastTimes.value.end;
    shiftForm.note = shift?.note ?? '';
}
function saveShift() {
    lastTimes.value = { start: shiftForm.start, end: shiftForm.end };
    const opts = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value.shift)
        shiftForm.put(route('shifts.update', editing.value.shift.id), opts);
    else shiftForm.post(route('shifts.store'), opts);
}
function deleteShift() {
    if (!confirm(t('shifts.confirm_delete'))) return;
    router.delete(route('shifts.destroy', editing.value.shift.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}
const editingName = computed(
    () => props.staff.find((s) => s.id === shiftForm.user_id)?.name ?? '',
);

// --- Отметка: исправить или внести забытую (с причиной) --------------------
const correcting = ref(null);
const attForm = useForm({
    user_id: null,
    clock_in: '',
    clock_out: '',
    reason: '',
});
function openAttendance(userId, attendance, shift = null) {
    correcting.value = { attendance };
    attForm.clearErrors();
    attForm.user_id = userId;
    attForm.clock_in = toInput(attendance?.clock_in ?? shift?.starts_at);
    attForm.clock_out = toInput(attendance?.clock_out ?? shift?.ends_at);
    attForm.reason = '';
    editing.value = null;
}
function saveAttendance() {
    attForm.post(
        route(
            'shifts.attendance',
            correcting.value.attendance?.id ?? undefined,
        ),
        {
            preserveScroll: true,
            onSuccess: () => (correcting.value = null),
        },
    );
}

// --- Шаблон 2/2, 5/2… --------------------------------------------------------
const patterning = ref(false);
const presets = [
    [2, 2],
    [5, 2],
    [1, 1],
    [1, 3],
];
const patternForm = useForm({
    user_id: null,
    from: props.week,
    until: addDays(props.week, 27),
    work: 2,
    off: 2,
    start: '09:00',
    end: '21:00',
});
function openPattern() {
    patternForm.clearErrors();
    patternForm.from = props.week;
    patternForm.until = addDays(props.week, 27);
    patterning.value = true;
}
const savePattern = () =>
    patternForm.post(route('shifts.pattern'), {
        preserveScroll: true,
        onSuccess: () => (patterning.value = false),
    });

function copyWeek() {
    if (!confirm(t('shifts.confirm_copy'))) return;
    router.post(
        route('shifts.copy-week'),
        { week: props.week },
        { preserveScroll: true },
    );
}

// --- Табель ------------------------------------------------------------------
const tsFrom = ref(props.timesheet?.from ?? '');
const tsTo = ref(props.timesheet?.to ?? '');
watch(
    () => props.timesheet,
    (v) => {
        tsFrom.value = v?.from ?? '';
        tsTo.value = v?.to ?? '';
    },
);
const showTimesheet = () =>
    router.get(
        route('shifts.index'),
        { week: props.week, from: tsFrom.value, to: tsTo.value },
        { preserveState: true, preserveScroll: true, only: ['timesheet'] },
    );
</script>

<template>
    <AdminLayout :title="t('nav.shifts')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.shifts') }}
                    </h1>
                    <p class="mt-1 text-sm text-ink-2">
                        {{ t('shifts.hint') }}
                    </p>
                </div>
                <div
                    class="flex overflow-hidden rounded-md border border-line bg-surface"
                    role="tablist"
                >
                    <button
                        v-for="tb in ['schedule', 'timesheet']"
                        :key="tb"
                        type="button"
                        role="tab"
                        :aria-selected="tab === tb"
                        class="h-9 border-l border-line px-4 text-sm first:border-l-0"
                        :class="
                            tab === tb
                                ? 'bg-ink text-paper'
                                : 'hover:bg-surface-2'
                        "
                        @click="tab = tb"
                    >
                        {{ t(`shifts.tab_${tb}`) }}
                    </button>
                </div>
            </div>

            <!-- График -->
            <template v-if="tab === 'schedule'">
                <div class="flex flex-wrap items-center gap-2">
                    <Btn
                        variant="secondary"
                        size="sm"
                        :aria-label="t('shifts.prev_week')"
                        @click="goWeek(addDays(week, -7))"
                        ><Icon name="back" :size="16"
                    /></Btn>
                    <span class="min-w-40 text-center font-mono text-sm"
                        >{{ dm(days[0]) }} – {{ dm(days[6]) }}</span
                    >
                    <Btn
                        variant="secondary"
                        size="sm"
                        :aria-label="t('shifts.next_week')"
                        @click="goWeek(addDays(week, 7))"
                        ><Icon name="back" :size="16" class="rotate-180"
                    /></Btn>
                    <Btn variant="ghost" size="sm" @click="goWeek(today)">{{
                        t('shifts.this_week')
                    }}</Btn>
                    <span class="flex-1" />
                    <Btn variant="secondary" size="sm" @click="copyWeek"
                        ><Icon name="log" :size="16" />{{
                            t('shifts.copy_week')
                        }}</Btn
                    >
                    <Btn size="sm" @click="openPattern"
                        ><Icon name="calendar" :size="16" />{{
                            t('shifts.pattern')
                        }}</Btn
                    >
                </div>

                <div
                    class="overflow-x-auto rounded-lg border border-line bg-surface"
                >
                    <table class="w-full min-w-[980px] table-fixed text-sm">
                        <thead>
                            <tr class="bg-surface-2 text-xs text-ink-2">
                                <th
                                    class="w-48 px-3 py-2 text-left font-medium"
                                >
                                    {{ t('shifts.employee') }}
                                </th>
                                <th
                                    v-for="d in days"
                                    :key="d"
                                    class="px-2 py-2 text-left font-medium"
                                    :class="
                                        d === today
                                            ? 'bg-brand-soft text-ink'
                                            : ''
                                    "
                                >
                                    <span class="capitalize">{{
                                        dayLabel(d)
                                    }}</span>
                                    <span class="ml-1 font-mono">{{
                                        dm(d)
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in staff"
                                :key="p.id"
                                class="border-t border-line align-top"
                            >
                                <td class="px-3 py-2">
                                    <span
                                        class="flex items-center gap-1.5 font-medium"
                                        ><span
                                            v-if="p.on_shift"
                                            class="size-2 shrink-0 rounded-full bg-ok"
                                            :title="t('shifts.on_shift')"
                                        />{{ p.name }}</span
                                    >
                                    <span class="text-xs text-ink-3">{{
                                        p.role ? loc(p.role, 'name') : ''
                                    }}</span>
                                </td>
                                <td
                                    v-for="d in days"
                                    :key="d"
                                    class="group h-16 px-1.5 py-1.5"
                                    :class="
                                        d === today ? 'bg-brand-soft/30' : ''
                                    "
                                >
                                    <div class="flex flex-col gap-1">
                                        <button
                                            v-for="s in cell[`${p.id}|${d}`] ??
                                            []"
                                            :key="s.kind + s.id"
                                            type="button"
                                            class="flex flex-col rounded-sm border px-1.5 py-1 text-left text-xs leading-tight"
                                            :class="chipClass(s)"
                                            @click="
                                                s.kind === 'shift'
                                                    ? openShift(p.id, d, s)
                                                    : openAttendance(p.id, s)
                                            "
                                        >
                                            <span
                                                v-if="s.kind === 'shift'"
                                                class="font-mono font-semibold"
                                                >{{ s.start }}–{{ s.end }}</span
                                            >
                                            <span v-else class="font-mono"
                                                >{{ time(s.clock_in) }}–{{
                                                    s.clock_out
                                                        ? time(s.clock_out)
                                                        : '…'
                                                }}</span
                                            >
                                            <span
                                                v-if="s.kind === 'unplanned'"
                                                class="text-[10px] text-ink-3"
                                                >{{
                                                    t('shifts.unplanned')
                                                }}</span
                                            >
                                            <span
                                                v-else-if="s.missed"
                                                class="text-[10px]"
                                                >{{ t('shifts.missed') }}</span
                                            >
                                            <span
                                                v-else-if="s.late || s.early"
                                                class="text-[10px]"
                                                >{{
                                                    [
                                                        s.late
                                                            ? t(
                                                                  'shifts.late_n',
                                                                  { n: s.late },
                                                              )
                                                            : '',
                                                        s.early
                                                            ? t(
                                                                  'shifts.early_n',
                                                                  {
                                                                      n: s.early,
                                                                  },
                                                              )
                                                            : '',
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')
                                                }}</span
                                            >
                                            <span
                                                v-else-if="s.attendance"
                                                class="text-[10px]"
                                                >{{
                                                    s.attendance.clock_out
                                                        ? `✓ ${hm(s.attendance.minutes)}`
                                                        : t('shifts.working')
                                                }}</span
                                            >
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-7 items-center justify-center rounded-sm border border-dashed border-line text-ink-3 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 pointer-coarse:opacity-60"
                                            :aria-label="`${t('shifts.add')}: ${p.name}, ${dm(d)}`"
                                            @click="openShift(p.id, d)"
                                        >
                                            <Icon name="plus" :size="14" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!staff.length">
                                <td
                                    colspan="8"
                                    class="px-4 py-12 text-center text-ink-3"
                                >
                                    {{ t('shifts.no_staff') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-ink-2">
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="size-3 rounded-xs border border-line bg-surface-2"
                        />{{ t('shifts.legend_planned') }}</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="size-3 rounded-xs border border-brand bg-brand-soft"
                        />{{ t('shifts.working') }}</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="size-3 rounded-xs border border-ok bg-ok-soft"
                        />{{ t('shifts.legend_done') }}</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="size-3 rounded-xs border border-ochre bg-ochre-soft"
                        />{{ t('shifts.legend_late') }}</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span
                            class="size-3 rounded-xs border border-danger bg-danger-soft"
                        />{{ t('shifts.missed') }}</span
                    >
                </div>
            </template>

            <!-- Табель -->
            <template v-else>
                <div
                    class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface p-3"
                >
                    <label
                        class="flex items-center gap-1.5 text-[13px] text-ink-2"
                        >{{ t('reports.from')
                        }}<span class="block w-36"
                            ><TextInput
                                v-model="tsFrom"
                                type="date"
                                size="sm"
                                mono /></span
                    ></label>
                    <label
                        class="flex items-center gap-1.5 text-[13px] text-ink-2"
                        >{{ t('reports.to')
                        }}<span class="block w-36"
                            ><TextInput
                                v-model="tsTo"
                                type="date"
                                size="sm"
                                mono /></span
                    ></label>
                    <button
                        type="button"
                        class="h-8 rounded-sm bg-brand px-3 text-[13px] font-semibold text-brand-ink"
                        @click="showTimesheet"
                    >
                        {{ t('reports.show') }}
                    </button>
                </div>
                <div
                    class="overflow-x-auto rounded-lg border border-line bg-surface"
                >
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                        >
                            <tr>
                                <th class="px-4 py-2.5">
                                    {{ t('shifts.employee') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('shifts.count') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('shifts.planned_h') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('shifts.worked_h') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('shifts.late') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('shifts.early') }}
                                </th>
                                <th class="px-4 py-2.5 text-right">
                                    {{ t('shifts.missed') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in timesheet?.rows ?? []"
                                :key="r.user_id"
                                class="h-11 border-t border-line"
                            >
                                <td class="px-4 font-medium">{{ r.name }}</td>
                                <td class="px-4 text-right font-mono">
                                    {{ r.shifts }}
                                </td>
                                <td class="px-4 text-right font-mono">
                                    {{ hm(r.planned_minutes) }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono font-semibold"
                                >
                                    {{ hm(r.worked_minutes) }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono"
                                    :class="
                                        r.late ? 'text-ochre-ink' : 'text-ink-3'
                                    "
                                >
                                    {{ r.late }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono"
                                    :class="
                                        r.early
                                            ? 'text-ochre-ink'
                                            : 'text-ink-3'
                                    "
                                >
                                    {{ r.early }}
                                </td>
                                <td
                                    class="px-4 text-right font-mono"
                                    :class="
                                        r.missed
                                            ? 'font-semibold text-danger'
                                            : 'text-ink-3'
                                    "
                                >
                                    {{ r.missed }}
                                </td>
                            </tr>
                            <tr v-if="!(timesheet?.rows ?? []).length">
                                <td
                                    colspan="7"
                                    class="px-4 py-12 text-center text-ink-3"
                                >
                                    {{ t('shifts.no_data') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Смена -->
        <Modal
            :show="editing !== null"
            :title="`${editing?.shift ? t('shifts.edit') : t('shifts.add')}: ${editingName}, ${shiftForm.date ? date(shiftForm.date + 'T12:00:00') : ''}`"
            @close="editing = null"
        >
            <form
                id="shift-form"
                class="flex flex-col gap-4"
                @submit.prevent="saveShift"
            >
                <div class="grid grid-cols-2 gap-3">
                    <Field
                        :label="t('shifts.start')"
                        for="sh-s"
                        :error="shiftForm.errors.start"
                    >
                        <TextInput
                            id="sh-s"
                            v-model="shiftForm.start"
                            type="time"
                            mono
                        />
                    </Field>
                    <Field
                        :label="t('shifts.end')"
                        for="sh-e"
                        :error="shiftForm.errors.end"
                    >
                        <TextInput
                            id="sh-e"
                            v-model="shiftForm.end"
                            type="time"
                            mono
                        />
                    </Field>
                </div>
                <p
                    v-if="shiftForm.end && shiftForm.end <= shiftForm.start"
                    class="text-xs text-ink-3"
                >
                    {{ t('shifts.overnight') }}
                </p>
                <Field
                    :label="t('shifts.note')"
                    for="sh-n"
                    :error="shiftForm.errors.note"
                >
                    <TextInput
                        id="sh-n"
                        v-model="shiftForm.note"
                        maxlength="120"
                        :placeholder="t('shifts.note_ph')"
                    />
                </Field>

                <div
                    v-if="editing?.shift"
                    class="flex flex-col gap-2 rounded-md bg-surface-2 p-3 text-sm"
                >
                    <span class="font-medium">{{
                        t('shifts.attendance')
                    }}</span>
                    <span v-if="editing.shift.attendance" class="font-mono"
                        >{{ time(editing.shift.attendance.clock_in) }} –
                        {{
                            editing.shift.attendance.clock_out
                                ? time(editing.shift.attendance.clock_out)
                                : t('shifts.working')
                        }}
                        <span
                            v-if="editing.shift.attendance.corrected"
                            class="text-xs text-ink-3"
                            >· {{ t('shifts.corrected') }}:
                            {{
                                editing.shift.attendance.correction_reason
                            }}</span
                        ></span
                    >
                    <span v-else class="text-ink-3">{{
                        t('shifts.no_marks')
                    }}</span>
                    <Btn
                        variant="secondary"
                        size="sm"
                        class="self-start"
                        @click="
                            openAttendance(
                                shiftForm.user_id,
                                editing.shift.attendance,
                                editing.shift,
                            )
                        "
                        >{{
                            editing.shift.attendance
                                ? t('shifts.correct')
                                : t('shifts.add_mark')
                        }}</Btn
                    >
                </div>
            </form>
            <template #footer>
                <Btn
                    v-if="editing?.shift"
                    variant="danger"
                    class="mr-auto"
                    @click="deleteShift"
                    >{{ t('common.delete') }}</Btn
                >
                <Btn variant="ghost" @click="editing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="shift-form"
                    :loading="shiftForm.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>

        <!-- Исправить отметку / внести забытую -->
        <Modal
            :show="correcting !== null"
            :title="
                correcting?.attendance
                    ? t('shifts.correct')
                    : t('shifts.add_mark')
            "
            @close="correcting = null"
        >
            <form
                id="att-form"
                class="flex flex-col gap-4"
                @submit.prevent="saveAttendance"
            >
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Field
                        :label="t('shifts.clock_in')"
                        for="at-in"
                        :error="attForm.errors.clock_in"
                    >
                        <TextInput
                            id="at-in"
                            v-model="attForm.clock_in"
                            type="datetime-local"
                            mono
                        />
                    </Field>
                    <Field
                        :label="t('shifts.clock_out')"
                        for="at-out"
                        :error="attForm.errors.clock_out"
                    >
                        <TextInput
                            id="at-out"
                            v-model="attForm.clock_out"
                            type="datetime-local"
                            mono
                        />
                    </Field>
                </div>
                <Field
                    :label="t('shifts.reason')"
                    for="at-r"
                    :error="attForm.errors.reason"
                    :hint="t('shifts.reason_hint')"
                >
                    <TextInput
                        id="at-r"
                        v-model="attForm.reason"
                        maxlength="255"
                        :placeholder="t('shifts.reason_ph')"
                    />
                </Field>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="correcting = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="att-form"
                    :loading="attForm.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>

        <!-- Шаблон -->
        <Modal
            :show="patterning"
            :title="t('shifts.pattern')"
            @close="patterning = false"
        >
            <form
                id="pat-form"
                class="flex flex-col gap-4"
                @submit.prevent="savePattern"
            >
                <Field
                    :label="t('shifts.employee')"
                    for="pt-u"
                    :error="patternForm.errors.user_id"
                >
                    <Select
                        id="pt-u"
                        v-model="patternForm.user_id"
                        :placeholder="t('shifts.pick_employee')"
                    >
                        <option v-for="p in staff" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </Select>
                </Field>
                <div class="flex flex-col gap-2">
                    <span class="text-[13px] font-medium">{{
                        t('shifts.cycle')
                    }}</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="[w, o] in presets"
                            :key="`${w}/${o}`"
                            type="button"
                            class="h-9 rounded-md border px-3 font-mono text-sm"
                            :class="
                                patternForm.work === w && patternForm.off === o
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line hover:bg-surface-2'
                            "
                            @click="
                                patternForm.work = w;
                                patternForm.off = o;
                            "
                        >
                            {{ w }}/{{ o }}
                        </button>
                        <span class="text-xs text-ink-3">{{
                            t('shifts.cycle_hint', {
                                w: patternForm.work,
                                o: patternForm.off,
                            })
                        }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <Field
                        :label="t('shifts.from_date')"
                        for="pt-f"
                        :error="patternForm.errors.from"
                    >
                        <TextInput
                            id="pt-f"
                            v-model="patternForm.from"
                            type="date"
                            mono
                        />
                    </Field>
                    <Field
                        :label="t('shifts.until_date')"
                        for="pt-t"
                        :error="patternForm.errors.until"
                    >
                        <TextInput
                            id="pt-t"
                            v-model="patternForm.until"
                            type="date"
                            mono
                        />
                    </Field>
                    <Field
                        :label="t('shifts.start')"
                        for="pt-s"
                        :error="patternForm.errors.start"
                    >
                        <TextInput
                            id="pt-s"
                            v-model="patternForm.start"
                            type="time"
                            mono
                        />
                    </Field>
                    <Field
                        :label="t('shifts.end')"
                        for="pt-e"
                        :error="patternForm.errors.end"
                    >
                        <TextInput
                            id="pt-e"
                            v-model="patternForm.end"
                            type="time"
                            mono
                        />
                    </Field>
                </div>
                <p class="text-xs text-ink-3">{{ t('shifts.pattern_hint') }}</p>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="patterning = false">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="pat-form"
                    :disabled="!patternForm.user_id"
                    :loading="patternForm.processing"
                    >{{ t('shifts.fill') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

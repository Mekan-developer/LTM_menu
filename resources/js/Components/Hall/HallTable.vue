<script setup>
// Стол в режиме «Работа». Статус кодируется заливкой и светлотой, а не только оттенком:
// свободен — пунктир на фоне поверхности, занят — тёмная заливка brand, счёт — accent.
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { time } from '@/utils/format';
import { box, sinceLabel } from './hall';

const props = defineProps({
    table: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    picking: { type: Boolean, default: false }, // режим «выберите стол» для переноса
    now: { type: Number, default: () => Date.now() },
});
defineEmits(['select']);

const { t } = useI18n();
const b = computed(() => box(props.table));
// «Забронирован» — стол пока свободен: его можно выбрать для переноса и открыть.
const free = computed(() => ['free', 'reserved'].includes(props.table.status));
const pickable = computed(() => props.picking && free.value);

const cls = computed(() => {
    const s = props.table.status;
    const out = [props.table.shape === 'round' ? 'rounded-full' : 'rounded-md'];
    if (s === 'free')
        out.push('border-2 border-dashed border-free bg-surface text-ink-2');
    else if (s === 'reserved')
        out.push('border-2 border-ochre bg-ochre-soft text-ochre-ink');
    else if (s === 'bill') out.push('bg-accent text-accent-ink');
    else out.push('bg-brand text-brand-ink');
    if (props.table.qr_pending)
        out.push(
            'pulse-ring ring-2 ring-accent ring-offset-2 ring-offset-paper',
        );
    if (props.selected) out.push('outline-2 outline-offset-4 outline-ink');
    if (pickable.value)
        out.push('border-brand border-solid bg-brand-soft text-ink');
    if (props.picking && !free.value) out.push('opacity-40');
    return out;
});

const line = computed(() => {
    const tb = props.table;
    if (tb.status === 'free') return `${tb.seats} ${t('tables.seats_short')}`;
    if (tb.status === 'reserved' && tb.booking)
        return `${time(tb.booking.starts_at)} · ${tb.booking.guests_count}`;
    if (!tb.details) return t('hall.busy');
    if (tb.status === 'bill') return t('hall.bill');
    return [sinceLabel(tb.opened_at, props.now), tb.waiter?.initials]
        .filter(Boolean)
        .join(' · ');
});

const label = computed(
    () =>
        `${t('tables.table')} ${props.table.number}: ${t(`hall.status_${props.table.status}`)}` +
        (props.table.qr_pending ? `, ${t('hall.qr')}` : '') +
        (props.table.ready ? `, ${t('hall.ready')}` : ''),
);
</script>

<template>
    <button
        type="button"
        class="absolute flex flex-col items-center justify-center gap-0.5 overflow-visible p-1 text-center leading-tight transition-[outline,opacity]"
        :class="cls"
        :style="{
            left: `${b.x}px`,
            top: `${b.y}px`,
            width: `${b.w}px`,
            height: `${b.h}px`,
        }"
        :aria-label="label"
        :aria-pressed="selected"
        @click="$emit('select', table)"
    >
        <span class="font-mono text-lg leading-5 font-semibold">{{
            table.number
        }}</span>
        <span class="max-w-full truncate font-mono text-[11px] opacity-85">{{
            line
        }}</span>

        <span
            v-if="table.qr_pending"
            class="absolute -top-2.5 -left-2 rounded-sm bg-accent px-1 font-mono text-[10px] font-bold text-accent-ink"
            >QR</span
        >
        <span
            v-if="table.ready"
            class="absolute -top-1 -right-1 size-3 rounded-full border-2 border-paper bg-ok"
            aria-hidden="true"
        />
    </button>
</template>

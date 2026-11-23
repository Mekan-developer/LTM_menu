<script setup>
import { onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';

const props = defineProps({ tables: { type: Array, default: () => [] } });

const { t } = useI18n();
const editing = ref(null);
const form = useForm({ number: '', hall: '', seats: 4, is_active: true });
const qr = ref({});

async function renderQr() {
    const entries = await Promise.all(
        props.tables.map(async (tb) => [
            tb.id,
            await QRCode.toDataURL(tb.qr_url, {
                margin: 1,
                width: 240,
                color: { dark: '#1D2420', light: '#FFFFFF' },
            }),
        ]),
    );
    qr.value = Object.fromEntries(entries);
}
onMounted(renderQr);
watch(() => props.tables, renderQr);

function open(table = null) {
    editing.value = table ?? 'new';
    form.clearErrors();
    form.number = table?.number ?? '';
    form.hall = table?.hall ?? '';
    form.seats = table?.seats ?? 4;
    form.is_active = table?.is_active ?? true;
}

function save() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    };
    if (editing.value === 'new') form.post(route('tables.store'), opts);
    else form.put(route('tables.update', editing.value.id), opts);
}

function download(table) {
    const a = document.createElement('a');
    a.href = qr.value[table.id];
    a.download = `stol-${table.number}.png`;
    a.click();
}

function regenerate(table) {
    if (confirm(t('tables.confirm_regenerate', { n: table.number }))) {
        router.post(
            route('tables.regenerate', table.id),
            {},
            { preserveScroll: true },
        );
    }
}

function remove(table) {
    if (confirm(t('tables.confirm_delete', { n: table.number }))) {
        router.delete(route('tables.destroy', table.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AdminLayout :title="t('nav.tables')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.tables') }}
                    </h1>
                    <p class="mt-1 text-sm text-ink-2">
                        {{ t('tables.hint') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Btn variant="secondary" :href="route('tables.print')"
                        ><Icon name="print" />{{ t('tables.print_all') }}</Btn
                    >
                    <Btn @click="open()"
                        ><Icon name="plus" />{{ t('tables.add') }}</Btn
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="table in tables"
                    :key="table.id"
                    class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
                    :class="!table.is_active ? 'opacity-60' : ''"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex flex-col">
                            <span class="font-mono text-xs text-ink-3"
                                >{{ table.hall || '—' }} · {{ table.seats }}
                                {{ t('tables.seats_short') }}</span
                            >
                            <span class="text-2xl font-semibold"
                                >{{ t('tables.table') }}
                                {{ table.number }}</span
                            >
                        </div>
                        <Badge v-if="table.busy" tone="brand">{{
                            t('tables.busy')
                        }}</Badge>
                        <Badge v-else-if="!table.is_active" tone="muted">{{
                            t('common.hidden')
                        }}</Badge>
                    </div>
                    <img
                        v-if="qr[table.id]"
                        :src="qr[table.id]"
                        :alt="`QR ${table.number}`"
                        class="mx-auto size-36 rounded-xs border border-line"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <Btn
                            variant="secondary"
                            size="sm"
                            @click="download(table)"
                            >PNG</Btn
                        >
                        <Btn
                            variant="secondary"
                            size="sm"
                            :href="route('tables.print', { table: table.id })"
                            >PDF</Btn
                        >
                        <Btn variant="ghost" size="sm" @click="open(table)"
                            ><Icon name="edit" :size="15" />{{
                                t('common.edit')
                            }}</Btn
                        >
                        <Btn
                            variant="ghost"
                            size="sm"
                            @click="regenerate(table)"
                            ><Icon name="qr" :size="15" />{{
                                t('tables.regenerate')
                            }}</Btn
                        >
                    </div>
                    <button
                        type="button"
                        class="self-start text-xs text-danger underline-offset-4 hover:underline"
                        @click="remove(table)"
                    >
                        {{ t('common.delete') }}
                    </button>
                </article>
            </div>
            <p
                v-if="!tables.length"
                class="rounded-lg border border-dashed border-ink-3 bg-surface p-8 text-center text-sm text-ink-2"
            >
                {{ t('tables.empty') }}
            </p>
        </div>

        <Modal
            :show="editing !== null"
            :title="editing === 'new' ? t('tables.add') : t('tables.edit')"
            width="max-w-md"
            @close="editing = null"
        >
            <form
                id="table-form"
                class="grid grid-cols-2 gap-4"
                @submit.prevent="save"
            >
                <Field
                    :label="t('tables.number')"
                    for="t-n"
                    :error="form.errors.number"
                    ><TextInput id="t-n" v-model="form.number" mono
                /></Field>
                <Field
                    :label="t('tables.seats')"
                    for="t-s"
                    :error="form.errors.seats"
                    ><TextInput
                        id="t-s"
                        v-model="form.seats"
                        type="number"
                        min="1"
                        mono
                /></Field>
                <div class="col-span-2">
                    <Field
                        :label="t('tables.hall')"
                        for="t-h"
                        :error="form.errors.hall"
                        ><TextInput id="t-h" v-model="form.hall"
                    /></Field>
                </div>
                <label class="col-span-2 flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-[var(--color-brand)]"
                    />{{ t('tables.active') }}</label
                >
            </form>
            <template #footer>
                <Btn variant="ghost" @click="editing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="table-form"
                    :loading="form.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

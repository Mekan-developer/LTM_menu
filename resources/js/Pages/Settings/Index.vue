<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { dateTime, money } from '@/utils/format';

const props = defineProps({
    settings: { type: Object, required: true },
    backup: { type: Object, default: null },
});
const { t } = useI18n();

const form = useForm({
    ...props.settings,
    qr_confirmation: Boolean(props.settings.qr_confirmation),
    reviews_enabled: Boolean(props.settings.reviews_enabled),
    online_booking: Boolean(props.settings.online_booking),
    online_delivery: Boolean(props.settings.online_delivery),
    // Стоимость доставки хранится в тенге, в форме — TMT.
    delivery_fee: money(props.settings.delivery_fee ?? 0),
});
const submit = () =>
    form.put(route('settings.update'), { preserveScroll: true });
</script>

<template>
    <AdminLayout :title="t('nav.settings')">
        <form
            class="mx-auto flex max-w-2xl flex-col gap-5"
            @submit.prevent="submit"
        >
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ t('nav.settings') }}
            </h1>

            <section
                class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('settings.general') }}
                </h2>
                <Field
                    :label="t('settings.restaurant_name')"
                    for="s-name"
                    :error="form.errors.restaurant_name"
                    ><TextInput id="s-name" v-model="form.restaurant_name"
                /></Field>
            </section>

            <section
                class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('settings.orders') }}
                </h2>
                <label class="flex items-start gap-3">
                    <input
                        v-model="form.qr_confirmation"
                        type="checkbox"
                        class="mt-0.5 size-[18px] accent-[var(--color-brand)]"
                    />
                    <span class="flex flex-col"
                        ><span class="text-sm font-medium">{{
                            t('settings.qr_confirmation')
                        }}</span
                        ><span class="text-xs text-ink-3">{{
                            t('settings.qr_confirmation_hint')
                        }}</span></span
                    >
                </label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field
                        :label="t('settings.discount_k')"
                        for="s-k"
                        :error="form.errors.discount_limit_k"
                        ><TextInput
                            id="s-k"
                            v-model="form.discount_limit_k"
                            type="number"
                            mono
                    /></Field>
                    <Field
                        :label="t('settings.kitchen_warn')"
                        for="s-w"
                        :error="form.errors.kitchen_warn_minutes"
                        ><TextInput
                            id="s-w"
                            v-model="form.kitchen_warn_minutes"
                            type="number"
                            mono
                    /></Field>
                    <Field
                        :label="t('settings.kitchen_late')"
                        for="s-l"
                        :error="form.errors.kitchen_late_minutes"
                        ><TextInput
                            id="s-l"
                            v-model="form.kitchen_late_minutes"
                            type="number"
                            mono
                    /></Field>
                </div>
            </section>

            <section
                class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('settings.bookings') }}
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field
                        :label="t('settings.booking_minutes')"
                        for="s-bm"
                        :error="form.errors.booking_minutes"
                        ><TextInput
                            id="s-bm"
                            v-model="form.booking_minutes"
                            type="number"
                            mono
                    /></Field>
                    <Field
                        :label="t('settings.booking_reserve_before')"
                        for="s-brb"
                        :error="form.errors.booking_reserve_before"
                        ><TextInput
                            id="s-brb"
                            v-model="form.booking_reserve_before"
                            type="number"
                            mono
                    /></Field>
                </div>
                <label class="flex items-start gap-3">
                    <input
                        v-model="form.online_booking"
                        type="checkbox"
                        class="mt-0.5 size-[18px] accent-[var(--color-brand)]"
                    />
                    <span class="flex flex-col"
                        ><span class="text-sm font-medium">{{
                            t('settings.online_booking')
                        }}</span
                        ><span class="text-xs text-ink-3">{{
                            t('settings.online_booking_hint')
                        }}</span></span
                    >
                </label>
            </section>

            <section
                class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('settings.delivery') }}
                </h2>
                <Field
                    :label="t('settings.delivery_fee')"
                    for="s-dfee"
                    :hint="t('settings.delivery_fee_hint')"
                    :error="form.errors.delivery_fee"
                    ><TextInput
                        id="s-dfee"
                        v-model="form.delivery_fee"
                        inputmode="decimal"
                        mono
                        class="sm:max-w-[200px]"
                /></Field>
                <label class="flex items-start gap-3">
                    <input
                        v-model="form.online_delivery"
                        type="checkbox"
                        class="mt-0.5 size-[18px] accent-[var(--color-brand)]"
                    />
                    <span class="flex flex-col"
                        ><span class="text-sm font-medium">{{
                            t('settings.online_delivery')
                        }}</span
                        ><span class="text-xs text-ink-3">{{
                            t('settings.online_delivery_hint')
                        }}</span></span
                    >
                </label>
            </section>

            <section
                class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('settings.reviews') }}
                </h2>
                <label class="flex items-start gap-3">
                    <input
                        v-model="form.reviews_enabled"
                        type="checkbox"
                        class="mt-0.5 size-[18px] accent-[var(--color-brand)]"
                    />
                    <span class="flex flex-col"
                        ><span class="text-sm font-medium">{{
                            t('settings.reviews_enabled')
                        }}</span
                        ><span class="text-xs text-ink-3">{{
                            t('settings.reviews_hint')
                        }}</span></span
                    >
                </label>
            </section>

            <section
                class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('settings.security') }}
                </h2>
                <Field
                    :label="t('settings.idle')"
                    for="s-idle"
                    :error="form.errors.shared_idle_seconds"
                    :hint="t('settings.idle_hint')"
                >
                    <TextInput
                        id="s-idle"
                        v-model="form.shared_idle_seconds"
                        type="number"
                        mono
                    />
                </Field>
            </section>

            <!-- Резервные копии (ТЗ 3.1): только статус, делает их планировщик -->
            <section
                v-if="backup"
                class="flex flex-col gap-3 rounded-lg border bg-surface p-5"
                :class="backup.stale ? 'border-danger' : 'border-line'"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">
                        {{ t('settings.backup') }}
                    </h2>
                    <span
                        class="rounded-sm px-2 py-0.5 font-mono text-xs"
                        :class="
                            backup.stale
                                ? 'bg-danger-soft text-danger'
                                : 'bg-ok-soft text-ok'
                        "
                        >{{
                            backup.stale
                                ? t('settings.backup_stale')
                                : t('settings.backup_ok')
                        }}</span
                    >
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <dt class="text-ink-2">{{ t('settings.backup_last') }}</dt>
                    <dd class="font-mono">
                        {{
                            backup.last_at
                                ? `${dateTime(backup.last_at)} · ${(backup.last_size / 1048576).toFixed(2).replace('.', ',')} MB`
                                : t('settings.backup_none')
                        }}
                    </dd>
                    <dt class="text-ink-2">{{ t('settings.backup_count') }}</dt>
                    <dd class="font-mono">
                        {{ backup.count }} / {{ backup.keep }}
                    </dd>
                    <dt class="text-ink-2">
                        {{ t('settings.backup_schedule') }}
                    </dt>
                    <dd class="font-mono">{{ backup.at }}</dd>
                    <dt class="text-ink-2">{{ t('settings.backup_path') }}</dt>
                    <dd class="font-mono break-all">{{ backup.path }}</dd>
                </dl>
                <p class="text-xs text-ink-3">
                    {{ t('settings.backup_hint') }}
                </p>
            </section>

            <div class="flex justify-end">
                <Btn type="submit" size="lg" :loading="form.processing">{{
                    t('common.save')
                }}</Btn>
            </div>
        </form>
    </AdminLayout>
</template>

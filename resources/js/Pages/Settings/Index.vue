<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';

const props = defineProps({ settings: { type: Object, required: true } });
const { t } = useI18n();

const form = useForm({
    ...props.settings,
    qr_confirmation: Boolean(props.settings.qr_confirmation),
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

            <div class="flex justify-end">
                <Btn type="submit" size="lg" :loading="form.processing">{{
                    t('common.save')
                }}</Btn>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useFirstError } from '@/composables/useFirstError';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const editing = computed(() => props.user !== null);

const form = useForm({
    name: props.user?.name ?? '',
    login: props.user?.login ?? '',
    password: '',
    pin: '',
    clear_pin: false,
    role_id:
        props.user?.role_id ??
        props.roles.find((r) => r.slug === 'waiter')?.id ??
        null,
});

// PIN — только цифры.
function onPin(e) {
    form.pin = e.target.value.replace(/\D/g, '');
    e.target.value = form.pin;
}

const formElement = ref(null);
const showFirstError = useFirstError(formElement);

function submit() {
    const options = { onError: showFirstError };
    if (editing.value) form.put(route('staff.update', props.user.id), options);
    else form.post(route('staff.store'), options);
}
</script>

<template>
    <AdminLayout :title="editing ? t('staff.edit') : t('staff.add')">
        <form
            ref="formElement"
            class="mx-auto flex max-w-2xl flex-col gap-5"
            @submit.prevent="submit"
        >
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ editing ? t('staff.edit') : t('staff.add') }}
            </h1>

            <section
                class="grid grid-cols-1 gap-4 rounded-lg border border-line bg-surface p-5 sm:grid-cols-2"
            >
                <Field
                    :label="t('staff.name')"
                    for="u-name"
                    :error="form.errors.name"
                    ><TextInput
                        id="u-name"
                        v-model="form.name"
                        :placeholder="t('staff.name_ph')"
                        maxlength="120"
                        autocomplete="off"
                /></Field>
                <Field
                    :label="t('staff.login')"
                    for="u-login"
                    :error="form.errors.login"
                    ><TextInput
                        id="u-login"
                        v-model="form.login"
                        mono
                        autocomplete="off"
                        :placeholder="t('staff.login_ph')"
                        maxlength="64"
                        autocapitalize="none"
                        spellcheck="false"
                /></Field>
                <Field
                    :label="t('staff.password')"
                    for="u-pass"
                    :error="form.errors.password"
                    :hint="
                        editing
                            ? t('staff.password_keep')
                            : t('staff.password_hint')
                    "
                >
                    <TextInput
                        id="u-pass"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            t(
                                editing
                                    ? 'staff.password_new_ph'
                                    : 'staff.password_ph',
                            )
                        "
                    />
                </Field>
                <Field
                    :label="t('staff.pin')"
                    for="u-pin"
                    :error="form.errors.pin"
                    :hint="t('staff.pin_hint')"
                >
                    <TextInput
                        id="u-pin"
                        v-model="form.pin"
                        mono
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="off"
                        :placeholder="t('staff.pin_ph')"
                        @input="onPin"
                    />
                </Field>
                <label
                    v-if="editing && user.has_pin"
                    class="flex items-center gap-2 text-sm sm:col-span-2"
                >
                    <input
                        v-model="form.clear_pin"
                        type="checkbox"
                        class="size-4 accent-[var(--color-brand)]"
                    />{{ t('staff.clear_pin') }}
                </label>
            </section>

            <section
                class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
            >
                <span class="text-[13px] font-medium">{{
                    t('staff.role')
                }}</span>
                <div
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                    role="radiogroup"
                >
                    <button
                        v-for="role in roles"
                        :key="role.id"
                        type="button"
                        role="radio"
                        :aria-checked="form.role_id === role.id"
                        class="flex h-12 items-center justify-between rounded-md border px-3 text-left text-sm"
                        :class="
                            form.role_id === role.id
                                ? 'border-2 border-brand bg-brand-soft font-semibold'
                                : 'border-line hover:bg-surface-2'
                        "
                        @click="form.role_id = role.id"
                    >
                        {{ loc(role, 'name') }}
                        <span class="font-mono text-[11px] text-ink-3">{{
                            role.is_owner
                                ? t('roles.all')
                                : role.permissions.length
                        }}</span>
                    </button>
                </div>
                <p v-if="form.errors.role_id" class="text-xs text-danger">
                    {{ form.errors.role_id }}
                </p>
                <p class="text-xs text-ink-3">{{ t('staff.one_role') }}</p>
            </section>

            <div class="flex justify-end gap-2">
                <Btn variant="ghost" size="lg" :href="route('staff.index')">{{
                    t('common.cancel')
                }}</Btn>
                <Btn type="submit" size="lg" :loading="form.processing">{{
                    t('common.save')
                }}</Btn>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PinPad from '@/Components/PinPad.vue';
import { useI18n } from '@/i18n';

defineProps({ staff: { type: Array, default: () => [] } });

const { t } = useI18n();
const selected = ref(null);
const form = useForm({ user_id: null, pin: '' });

function choose(person) {
    selected.value = person;
    form.clearErrors();
    form.user_id = person.id;
    form.pin = '';
}

const submit = () =>
    form.post(route('pin.store'), {
        onError: () => {
            form.pin = '';
        },
    });
</script>

<template>
    <AuthLayout :title="t('auth.pin_title')">
        <div class="flex w-full max-w-4xl flex-col items-center gap-8">
            <div class="text-center">
                <h1 class="text-[32px] leading-9 font-semibold tracking-tight">
                    {{ selected ? selected.name : t('auth.who') }}
                </h1>
                <p class="mt-1 text-sm text-ink-2">
                    {{
                        selected ? t('auth.enter_pin') : t('auth.pick_yourself')
                    }}
                </p>
            </div>

            <div
                v-if="!selected"
                class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
            >
                <button
                    v-for="person in staff"
                    :key="person.id"
                    type="button"
                    class="flex min-h-24 flex-col items-start justify-between gap-3 rounded-lg border border-line bg-surface p-4 text-left hover:border-ink-2 active:translate-y-px"
                    @click="choose(person)"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-sm bg-brand text-base font-semibold text-brand-ink"
                        >{{ person.name.slice(0, 2).toUpperCase() }}</span
                    >
                    <span class="flex flex-col">
                        <span class="text-[15px] font-semibold">{{
                            person.name
                        }}</span>
                        <span class="text-xs text-ink-3">{{
                            person.role
                        }}</span>
                    </span>
                </button>
                <p
                    v-if="!staff.length"
                    class="col-span-full text-center text-sm text-ink-2"
                >
                    {{ t('auth.no_pin_staff') }}
                </p>
            </div>

            <template v-else>
                <PinPad
                    v-model="form.pin"
                    :error="form.errors.pin"
                    @complete="submit"
                />
                <button
                    type="button"
                    class="h-11 px-4 text-sm text-ink-2 underline-offset-4 hover:underline"
                    @click="selected = null"
                >
                    {{ t('auth.not_me') }}
                </button>
            </template>

            <Link
                :href="route('login')"
                class="text-sm text-ink-3 underline-offset-4 hover:underline"
                >{{ t('auth.to_password') }}</Link
            >
        </div>
    </AuthLayout>
</template>

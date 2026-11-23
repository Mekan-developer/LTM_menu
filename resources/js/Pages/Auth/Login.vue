<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';

const { t } = useI18n();
const form = useForm({ login: '', password: '', remember: false });
const submit = () =>
    form.post(route('login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <AuthLayout :title="t('auth.title')">
        <form
            class="flex w-full max-w-[380px] flex-col gap-5 rounded-lg border border-line bg-surface p-7"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-1">
                <h1 class="text-[28px] leading-8 font-semibold tracking-tight">
                    {{ t('auth.title') }}
                </h1>
                <p class="text-sm text-ink-2">{{ t('auth.subtitle') }}</p>
            </div>
            <Field
                :label="t('auth.login')"
                for="login"
                :error="form.errors.login"
            >
                <TextInput
                    id="login"
                    v-model="form.login"
                    size="lg"
                    autocomplete="username"
                    autofocus
                />
            </Field>
            <Field
                :label="t('auth.password')"
                for="password"
                :error="form.errors.password"
            >
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    size="lg"
                    autocomplete="current-password"
                />
            </Field>
            <label class="flex items-center gap-2 text-sm text-ink-2">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="size-4 accent-[var(--color-brand)]"
                />
                {{ t('auth.remember') }}
            </label>
            <Btn type="submit" size="lg" block :loading="form.processing">{{
                t('auth.submit')
            }}</Btn>
            <Link
                :href="route('pin')"
                class="text-center text-sm text-ink-2 underline-offset-4 hover:underline"
                >{{ t('auth.to_pin') }}</Link
            >
        </form>
    </AuthLayout>
</template>

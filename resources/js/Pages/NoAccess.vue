<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useI18n } from '@/i18n';

const { t } = useI18n();
</script>

<template>
    <AdminLayout :title="t('no_access.title')">
        <div
            class="mx-auto mt-16 max-w-md rounded-lg border border-line bg-surface p-8 text-center"
        >
            <h1 class="text-2xl font-semibold">{{ t('no_access.title') }}</h1>
            <p class="mt-2 text-sm text-ink-2">{{ t('no_access.text') }}</p>
        </div>
    </AdminLayout>
</template>

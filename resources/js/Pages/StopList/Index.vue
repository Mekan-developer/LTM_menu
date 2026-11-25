<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StopListPanel from '@/Components/StopListPanel.vue';
import { useI18n } from '@/i18n';

defineProps({
    entries: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    modes: { type: Array, default: () => [] },
});

const { t } = useI18n();
</script>

<template>
    <AdminLayout :title="t('nav.stop_list')">
        <div class="flex flex-col gap-5">
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ t('nav.stop_list') }}
            </h1>

            <StopListPanel
                :entries="entries"
                :categories="categories"
                :modes="modes"
            />
        </div>
    </AdminLayout>
</template>

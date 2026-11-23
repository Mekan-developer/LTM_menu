<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { useI18n } from '@/i18n';

const props = defineProps({ tables: { type: Array, default: () => [] } });
const { t } = useI18n();
const qr = ref({});
const print = () => window.print();

onMounted(async () => {
    const entries = await Promise.all(
        props.tables.map(async (tb) => [
            tb.id,
            await QRCode.toDataURL(tb.qr_url, { margin: 1, width: 600 }),
        ]),
    );
    qr.value = Object.fromEntries(entries);
});
</script>

<template>
    <Head :title="t('tables.print_all')" />
    <div class="min-h-screen bg-white p-6 text-[#1D2420]">
        <div class="no-print mb-6 flex items-center gap-3">
            <Link :href="route('tables.index')" class="text-sm underline"
                >← {{ t('nav.tables') }}</Link
            >
            <button
                type="button"
                class="h-9 rounded-md bg-brand px-4 text-sm font-semibold text-brand-ink"
                @click="print"
            >
                {{ t('tables.print') }}
            </button>
            <span class="text-sm text-ink-2">{{ t('tables.print_hint') }}</span>
        </div>
        <div class="grid grid-cols-2 gap-6 print:gap-4">
            <div
                v-for="table in tables"
                :key="table.id"
                class="flex break-inside-avoid flex-col items-center gap-3 rounded-lg border border-[#DCD2C0] p-6"
            >
                <span class="font-serif text-xl italic">{{
                    $page.props.app.restaurant_name
                }}</span>
                <img
                    v-if="qr[table.id]"
                    :src="qr[table.id]"
                    alt=""
                    class="size-56"
                />
                <span class="text-3xl font-semibold"
                    >{{ t('tables.table') }} {{ table.number }}</span
                >
                <span class="text-center text-sm">{{
                    t('tables.scan_hint')
                }}</span>
            </div>
        </div>
    </div>
</template>

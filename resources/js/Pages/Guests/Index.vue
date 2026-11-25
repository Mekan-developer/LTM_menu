<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Icon from '@/Components/Icon.vue';
import GuestFormModal from '@/Components/GuestFormModal.vue';
import { useI18n } from '@/i18n';
import { date, money, phone } from '@/utils/format';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    q: { type: String, default: '' },
    guests: { type: Object, required: true },
    canExport: { type: Boolean, default: false },
});
const { t } = useI18n();

// Поиск по имени и телефону с задержкой, без перезагрузки страницы.
const search = ref(props.q);
let timer = null;
watch(search, (v) => {
    clearTimeout(timer);
    timer = setTimeout(
        () =>
            router.get(route('guests.index'), v ? { q: v } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        300,
    );
});
const pageUrl = (n) =>
    route('guests.index', { ...(props.q ? { q: props.q } : {}), page: n });

const creating = ref(false);
</script>

<template>
    <AdminLayout :title="t('nav.guests')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.guests') }}
                    </h1>
                    <span class="text-sm text-ink-3">{{
                        t('guests.total', { n: guests.total })
                    }}</span>
                </div>
                <div class="flex gap-2">
                    <a
                        v-if="canExport"
                        :href="route('guests.export')"
                        class="inline-flex h-10 items-center gap-1.5 rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-surface-2"
                        ><Icon name="excel" :size="16" />Excel</a
                    >
                    <Btn @click="creating = true"
                        ><Icon name="plus" />{{ t('guests.add') }}</Btn
                    >
                </div>
            </div>

            <div class="w-full max-w-md">
                <TextInput
                    v-model="search"
                    type="search"
                    :placeholder="t('guests.search')"
                    :aria-label="t('guests.search')"
                    icon="search"
                    size="lg"
                />
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[760px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">{{ t('guests.name') }}</th>
                            <th class="px-4 py-2.5">{{ t('guests.phone') }}</th>
                            <th class="px-4 py-2.5">
                                {{ t('guests.birthday') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('guests.visits') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('guests.spent') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('guests.last_visit') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="g in guests.data"
                            :key="g.id"
                            class="h-11 border-t border-line hover:bg-surface-2"
                        >
                            <td class="px-4">
                                <Link
                                    :href="route('guests.show', g.id)"
                                    class="font-medium hover:underline"
                                    >{{ g.name }}</Link
                                >
                            </td>
                            <td class="px-4 font-mono text-ink-2">
                                {{ phone(g.phone) }}
                            </td>
                            <td class="px-4 font-mono text-ink-2">
                                {{ g.birthday ? date(g.birthday) : '—' }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ g.visits }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ money(g.spent) }}
                            </td>
                            <td class="px-4 text-right font-mono text-ink-2">
                                {{ g.last_visit ? date(g.last_visit) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="!guests.data.length">
                            <td
                                colspan="6"
                                class="px-4 py-10 text-center text-ink-3"
                            >
                                {{
                                    q
                                        ? t('guests.not_found')
                                        : t('guests.empty')
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="guests.last_page > 1" class="flex justify-end gap-2">
                <Link
                    v-if="guests.current_page > 1"
                    :href="pageUrl(guests.current_page - 1)"
                    class="inline-flex h-9 items-center rounded-md border border-line px-3 text-sm"
                    >←</Link
                >
                <span
                    class="inline-flex h-9 items-center px-2 font-mono text-sm text-ink-3"
                    >{{ guests.current_page }} / {{ guests.last_page }}</span
                >
                <Link
                    v-if="guests.current_page < guests.last_page"
                    :href="pageUrl(guests.current_page + 1)"
                    class="inline-flex h-9 items-center rounded-md border border-line px-3 text-sm"
                    >→</Link
                >
            </div>
        </div>

        <GuestFormModal :show="creating" @close="creating = false" />
    </AdminLayout>
</template>

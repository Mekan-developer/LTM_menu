<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import TextInput from '@/Components/TextInput.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';

const props = defineProps({ users: { type: Array, default: () => [] } });
const { t } = useI18n();
const q = ref('');

const rows = computed(() => {
    const s = q.value.trim().toLowerCase();
    return props.users.filter(
        (u) =>
            !s ||
            u.name.toLowerCase().includes(s) ||
            u.login.toLowerCase().includes(s),
    );
});

const toggle = (u) =>
    router.post(route('staff.toggle', u.id), {}, { preserveScroll: true });
</script>

<template>
    <AdminLayout :title="t('nav.staff')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('nav.staff') }}
                </h1>
                <div class="flex flex-wrap gap-2">
                    <div class="w-full sm:w-64">
                        <TextInput
                            v-model="q"
                            :placeholder="t('staff.search')"
                            :aria-label="t('staff.search')"
                            type="search"
                            icon="search"
                        />
                    </div>
                    <Btn :href="route('staff.create')"
                        ><Icon name="plus" />{{ t('staff.add') }}</Btn
                    >
                </div>
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[720px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">{{ t('staff.name') }}</th>
                            <th class="px-4 py-2.5">{{ t('staff.login') }}</th>
                            <th class="px-4 py-2.5">{{ t('staff.role') }}</th>
                            <th class="px-4 py-2.5">PIN</th>
                            <th class="px-4 py-2.5">{{ t('staff.status') }}</th>
                            <th class="w-48 px-4 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in rows"
                            :key="u.id"
                            class="h-12 border-t border-line hover:bg-surface-2"
                        >
                            <td class="px-4 font-medium">{{ u.name }}</td>
                            <td class="px-4 font-mono text-[13px] text-ink-2">
                                {{ u.login }}
                            </td>
                            <td class="px-4">{{ u.role }}</td>
                            <td class="px-4">
                                <Badge v-if="u.pin_locked" tone="danger">{{
                                    t('staff.pin_locked')
                                }}</Badge>
                                <Badge v-else-if="u.has_pin" tone="ok">{{
                                    t('staff.pin_set')
                                }}</Badge>
                                <span v-else class="text-ink-3">—</span>
                            </td>
                            <td class="px-4">
                                <Badge :tone="u.is_active ? 'ok' : 'danger'">{{
                                    u.is_active
                                        ? t('staff.active')
                                        : t('staff.blocked')
                                }}</Badge>
                            </td>
                            <td class="px-4">
                                <div
                                    v-if="
                                        !u.is_owner ||
                                        u.id === $page.props.auth.user.id
                                    "
                                    class="flex justify-end gap-1"
                                >
                                    <Link
                                        :href="route('staff.edit', u.id)"
                                        class="inline-flex h-8 items-center rounded-md border border-line px-3 text-[13px] hover:bg-paper"
                                        >{{ t('common.edit') }}</Link
                                    >
                                    <Btn
                                        v-if="u.id !== $page.props.auth.user.id"
                                        variant="ghost"
                                        size="sm"
                                        @click="toggle(u)"
                                        >{{
                                            u.is_active
                                                ? t('staff.block')
                                                : t('staff.unblock')
                                        }}</Btn
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

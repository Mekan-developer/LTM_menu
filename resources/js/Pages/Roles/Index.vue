<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();

// Локальная копия матрицы: галочки сохраняются по кнопке у каждой роли.
const matrix = reactive(
    Object.fromEntries(props.roles.map((r) => [r.id, new Set(r.permissions)])),
);
const dirty = ref(new Set());

const groups = computed(() =>
    ['areas', 'operations', 'management', 'control']
        .map((g) => ({
            key: g,
            items: props.permissions.filter((p) => p.group === g),
        }))
        .filter((g) => g.items.length),
);

function toggle(role, key) {
    if (role.is_owner) return;
    const set = matrix[role.id];
    if (set.has(key)) set.delete(key);
    else set.add(key);
    dirty.value = new Set(dirty.value).add(role.id);
}

function save(role) {
    router.put(
        route('roles.update', role.id),
        {
            name_tm: role.name_tm,
            name_ru: role.name_ru,
            permissions: [...matrix[role.id]],
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                const d = new Set(dirty.value);
                d.delete(role.id);
                dirty.value = d;
            },
        },
    );
}

function remove(role) {
    if (confirm(t('roles.confirm_delete', { name: loc(role, 'name') }))) {
        router.delete(route('roles.destroy', role.id), {
            preserveScroll: true,
        });
    }
}

const creating = ref(false);
const form = useForm({ name_tm: '', name_ru: '', permissions: [] });
const create = () =>
    form.post(route('roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
</script>

<template>
    <AdminLayout :title="t('nav.roles')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.roles') }}
                    </h1>
                    <p class="mt-1 text-sm text-ink-2">{{ t('roles.hint') }}</p>
                </div>
                <Btn @click="creating = true"
                    ><Icon name="plus" />{{ t('roles.add') }}</Btn
                >
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line">
                            <th
                                class="sticky left-0 z-10 min-w-56 bg-surface px-4 py-3 text-left text-xs font-medium text-ink-2"
                            >
                                {{ t('roles.permission') }}
                            </th>
                            <th
                                v-for="role in roles"
                                :key="role.id"
                                class="min-w-32 px-3 py-3 text-center align-bottom"
                            >
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[13px] font-semibold">{{
                                        loc(role, 'name')
                                    }}</span>
                                    <span
                                        class="font-mono text-[11px] text-ink-3"
                                        >{{ role.users_count }}
                                        {{ t('roles.people') }}</span
                                    >
                                    <Btn
                                        v-if="dirty.has(role.id)"
                                        size="sm"
                                        @click="save(role)"
                                        >{{ t('common.save') }}</Btn
                                    >
                                    <button
                                        v-else-if="
                                            !role.is_system && !role.users_count
                                        "
                                        type="button"
                                        class="text-[11px] text-danger hover:underline"
                                        @click="remove(role)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in groups" :key="group.key">
                            <tr>
                                <td
                                    :colspan="roles.length + 1"
                                    class="bg-surface-2 px-4 py-1.5 font-mono text-[10px] tracking-[0.08em] text-ink-3 uppercase"
                                >
                                    {{ t(`perm_group.${group.key}`) }}
                                </td>
                            </tr>
                            <tr
                                v-for="p in group.items"
                                :key="p.key"
                                class="h-11 border-t border-line"
                            >
                                <td class="sticky left-0 bg-surface px-4">
                                    {{ t(`perm.${p.key}`) }}
                                </td>
                                <td
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="text-center"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-[18px] accent-[var(--color-brand)]"
                                        :checked="matrix[role.id].has(p.key)"
                                        :disabled="role.is_owner"
                                        :aria-label="`${loc(role, 'name')}: ${t(`perm.${p.key}`)}`"
                                        @change="toggle(role, p.key)"
                                    />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-ink-3">{{ t('roles.owner_note') }}</p>
        </div>

        <Modal
            :show="creating"
            :title="t('roles.add')"
            @close="creating = false"
        >
            <form
                id="role-form"
                class="flex flex-col gap-4"
                @submit.prevent="create"
            >
                <Field
                    :label="t('menu.name_tm')"
                    for="r-tm"
                    :error="form.errors.name_tm"
                    ><TextInput
                        id="r-tm"
                        v-model="form.name_tm"
                        :placeholder="t('roles.name_ph')"
                        maxlength="80"
                        autocomplete="off"
                /></Field>
                <Field
                    :label="t('menu.name_ru')"
                    for="r-ru"
                    :error="form.errors.name_ru"
                    ><TextInput
                        id="r-ru"
                        v-model="form.name_ru"
                        :placeholder="t('roles.name_ph')"
                        maxlength="80"
                        autocomplete="off"
                /></Field>
                <div class="flex flex-col gap-2">
                    <label
                        v-for="p in permissions"
                        :key="p.key"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            v-model="form.permissions"
                            type="checkbox"
                            :value="p.key"
                            class="size-4 accent-[var(--color-brand)]"
                        />{{ t(`perm.${p.key}`) }}
                    </label>
                </div>
            </form>
            <template #footer>
                <Btn variant="ghost" @click="creating = false">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="role-form"
                    :loading="form.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

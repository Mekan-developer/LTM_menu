<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import Icon from '@/Components/Icon.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({ categories: { type: Array, default: () => [] } });

const { t, loc } = useI18n();

// Фильтр: одна категория или все + поиск по названию блюда (обе локали).
const activeCat = ref(null);
const search = ref('');
const shown = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.categories
        .filter((c) => activeCat.value === null || c.id === activeCat.value)
        .map((c) => ({
            ...c,
            dishes: q
                ? c.dishes.filter((d) =>
                      `${d.name_tm} ${d.name_ru}`.toLowerCase().includes(q),
                  )
                : c.dishes,
        }))
        .filter((c) => !q || c.dishes.length);
});

const editing = ref(null); // null | 'new' | category
const form = useForm({ name_tm: '', name_ru: '', is_active: true });

function openCategory(category = null) {
    editing.value = category ?? 'new';
    form.clearErrors();
    form.name_tm = category?.name_tm ?? '';
    form.name_ru = category?.name_ru ?? '';
    form.is_active = category?.is_active ?? true;
}

function saveCategory() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    };
    if (editing.value === 'new')
        form.post(route('menu.categories.store'), opts);
    else form.put(route('menu.categories.update', editing.value.id), opts);
}

function removeCategory(category) {
    if (
        confirm(
            t('menu.confirm_delete_category', { name: loc(category, 'name') }),
        )
    ) {
        router.delete(route('menu.categories.destroy', category.id), {
            preserveScroll: true,
        });
    }
}

function removeDish(dish) {
    if (confirm(t('menu.confirm_delete_dish', { name: loc(dish, 'name') }))) {
        router.delete(route('menu.dishes.destroy', dish.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AdminLayout :title="t('nav.menu')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1
                    class="text-[32px] leading-[38px] font-semibold tracking-tight"
                >
                    {{ t('nav.menu') }}
                </h1>
                <div class="flex gap-2">
                    <Btn variant="secondary" @click="openCategory()"
                        ><Icon name="plus" />{{ t('menu.add_category') }}</Btn
                    >
                    <Btn :href="route('menu.dishes.create')"
                        ><Icon name="plus" />{{ t('menu.add_dish') }}</Btn
                    >
                </div>
            </div>

            <div
                v-if="!categories.length"
                class="rounded-lg border border-dashed border-ink-3 bg-surface p-8 text-center text-sm text-ink-2"
            >
                {{ t('menu.empty') }}
            </div>

            <div v-else class="flex flex-col gap-3">
                <div class="w-full max-w-sm">
                    <TextInput
                        v-model="search"
                        :placeholder="t('menu.search_dish')"
                        :aria-label="t('menu.search_dish')"
                        type="search"
                        icon="search"
                    />
                </div>
                <div
                    role="tablist"
                    :aria-label="t('menu.categories')"
                    class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1"
                >
                    <button
                        v-for="c in [{ id: null }, ...categories]"
                        :key="c.id ?? 'all'"
                        type="button"
                        role="tab"
                        :aria-selected="activeCat === c.id"
                        class="flex h-10 shrink-0 items-center gap-2 rounded-md border px-3.5 text-sm font-medium"
                        :class="
                            activeCat === c.id
                                ? 'border-ink bg-ink text-paper'
                                : 'border-line bg-surface hover:bg-surface-2'
                        "
                        @click="activeCat = c.id"
                    >
                        {{ c.id === null ? t('common.all') : loc(c, 'name') }}
                        <span class="font-mono text-xs opacity-70">{{
                            c.id === null
                                ? categories.reduce(
                                      (n, x) => n + x.dishes.length,
                                      0,
                                  )
                                : c.dishes.length
                        }}</span>
                    </button>
                </div>
            </div>

            <p
                v-if="categories.length && !shown.length"
                class="py-8 text-center text-sm text-ink-3"
            >
                {{ t('menu.nothing_found') }}
            </p>

            <section
                v-for="category in shown"
                :key="category.id"
                class="overflow-hidden rounded-lg border border-line bg-surface"
            >
                <div
                    class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3"
                >
                    <h2 class="text-lg font-semibold">
                        {{ loc(category, 'name') }}
                    </h2>
                    <Badge v-if="!category.is_active" tone="muted">{{
                        t('common.hidden')
                    }}</Badge>
                    <Badge v-if="category.stopped" tone="ochre">{{
                        t('stop.in_stop')
                    }}</Badge>
                    <span class="font-mono text-xs text-ink-3">{{
                        category.dishes.length
                    }}</span>
                    <div class="ml-auto flex gap-1">
                        <Btn
                            variant="ghost"
                            size="sm"
                            @click="openCategory(category)"
                            ><Icon name="edit" :size="16" />{{
                                t('common.edit')
                            }}</Btn
                        >
                        <Btn
                            variant="ghost"
                            size="sm"
                            @click="removeCategory(category)"
                            ><Icon name="trash" :size="16"
                        /></Btn>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead
                            class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                        >
                            <tr>
                                <th class="w-16 px-4 py-2">
                                    {{ t('menu.photo') }}
                                </th>
                                <th class="px-4 py-2">{{ t('menu.name') }}</th>
                                <th class="px-4 py-2">
                                    {{ t('menu.station') }}
                                </th>
                                <th class="px-4 py-2 text-right">
                                    {{ t('menu.price') }}
                                </th>
                                <th class="w-28 px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="dish in category.dishes"
                                :key="dish.id"
                                class="group h-14 border-t border-line hover:bg-surface-2"
                                :class="!dish.is_active ? 'opacity-60' : ''"
                            >
                                <td class="px-4 py-2">
                                    <img
                                        v-if="dish.photo_url"
                                        :src="dish.photo_url"
                                        alt=""
                                        class="h-9 w-12 rounded-xs object-cover"
                                        loading="lazy"
                                    />
                                    <span
                                        v-else
                                        class="block h-9 w-12 rounded-xs bg-surface-2"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{
                                            loc(dish, 'name')
                                        }}</span>
                                        <Badge
                                            v-if="dish.stopped"
                                            tone="ochre"
                                            >{{ t('stop.in_stop') }}</Badge
                                        >
                                        <Badge
                                            v-if="!dish.is_active"
                                            tone="muted"
                                            >{{ t('common.hidden') }}</Badge
                                        >
                                    </div>
                                    <span
                                        v-if="dish.weight"
                                        class="text-xs text-ink-3"
                                        >{{ dish.weight }}</span
                                    >
                                </td>
                                <td class="px-4 py-2 text-ink-2">
                                    {{ t(`station.${dish.station}`) }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono num">
                                    {{ money(dish.price) }}
                                </td>
                                <td class="px-4 py-2">
                                    <div
                                        class="flex justify-end gap-1 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'menu.dishes.edit',
                                                    dish.id,
                                                )
                                            "
                                            class="flex size-8 items-center justify-center rounded-md border border-line bg-surface hover:bg-paper"
                                            :aria-label="t('common.edit')"
                                            ><Icon name="edit" :size="15"
                                        /></Link>
                                        <button
                                            type="button"
                                            class="flex size-8 items-center justify-center rounded-md border border-line bg-surface hover:bg-paper"
                                            :aria-label="t('common.delete')"
                                            @click="removeDish(dish)"
                                        >
                                            <Icon name="trash" :size="15" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!category.dishes.length">
                                <td
                                    colspan="5"
                                    class="px-4 py-4 text-sm text-ink-3"
                                >
                                    {{ t('menu.no_dishes') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Modal
            :show="editing !== null"
            :title="
                editing === 'new'
                    ? t('menu.add_category')
                    : t('menu.edit_category')
            "
            @close="editing = null"
        >
            <form
                id="category-form"
                class="flex flex-col gap-4"
                @submit.prevent="saveCategory"
            >
                <Field
                    :label="t('menu.name_tm')"
                    for="c-tm"
                    :error="form.errors.name_tm"
                    ><TextInput
                        id="c-tm"
                        v-model="form.name_tm"
                        :placeholder="t('menu.category_name_ph')"
                        maxlength="120"
                        autocomplete="off"
                /></Field>
                <Field
                    :label="t('menu.name_ru')"
                    for="c-ru"
                    :error="form.errors.name_ru"
                    ><TextInput
                        id="c-ru"
                        v-model="form.name_ru"
                        :placeholder="t('menu.category_name_ph')"
                        maxlength="120"
                        autocomplete="off"
                /></Field>
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-[var(--color-brand)]"
                    />{{ t('menu.visible') }}</label
                >
            </form>
            <template #footer>
                <Btn variant="ghost" @click="editing = null">{{
                    t('common.cancel')
                }}</Btn>
                <Btn
                    type="submit"
                    form="category-form"
                    :loading="form.processing"
                    >{{ t('common.save') }}</Btn
                >
            </template>
        </Modal>
    </AdminLayout>
</template>

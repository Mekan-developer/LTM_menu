<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import Btn from '@/Components/Btn.vue';
import Icon from '@/Components/Icon.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    dishes: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();

// Фильтры: категория, тип, «без калькуляции», поиск.
const categories = computed(() => {
    const seen = new Map();
    props.dishes.forEach((d) => {
        if (d.category && !seen.has(d.category.id))
            seen.set(d.category.id, d.category);
    });
    return [...seen.values()];
});
const activeCat = ref(null);
const onlyMissing = ref(false);
const search = ref('');
const shown = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.dishes.filter(
        (d) =>
            (activeCat.value === null || d.category?.id === activeCat.value) &&
            (!onlyMissing.value || !d.has_recipe) &&
            (!q || `${d.name_tm} ${d.name_ru}`.toLowerCase().includes(q)),
    );
});
const missing = computed(() => props.dishes.filter((d) => !d.has_recipe));

const percent = (v) => (v === null ? '—' : `${String(v).replace('.', ',')} %`);
const open = (d) => router.visit(route('costing.edit', d.id));
</script>

<template>
    <AdminLayout :title="t('nav.costing')">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ t('nav.costing') }}
                    </h1>
                    <p class="mt-1 text-sm text-ink-2">
                        {{ t('costing.hint') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Btn
                        variant="secondary"
                        :href="route('costing.products.index')"
                        ><Icon name="menu" />{{ t('nav.products') }}</Btn
                    >
                    <a
                        :href="route('costing.export')"
                        class="inline-flex h-9 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-surface-2"
                        ><Icon name="excel" :size="16" />Excel</a
                    >
                </div>
            </div>

            <button
                v-if="missing.length"
                type="button"
                class="flex items-center gap-2 self-start rounded-md bg-ochre-soft px-3 py-2 text-left text-sm text-ochre-ink"
                @click="onlyMissing = !onlyMissing"
            >
                <Icon name="bell" :size="16" />
                {{ t('costing.missing', { n: missing.length }) }}
                <span class="underline">{{
                    onlyMissing ? t('costing.show_all') : t('costing.show_them')
                }}</span>
            </button>

            <div class="flex flex-col gap-3">
                <div class="w-full max-w-sm">
                    <TextInput
                        v-model="search"
                        type="search"
                        icon="search"
                        :placeholder="t('menu.search_dish')"
                        :aria-label="t('menu.search_dish')"
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
                        class="h-10 shrink-0 rounded-md border px-3.5 text-sm font-medium"
                        :class="
                            activeCat === c.id
                                ? 'border-ink bg-ink text-paper'
                                : 'border-line bg-surface hover:bg-surface-2'
                        "
                        @click="activeCat = c.id"
                    >
                        {{ c.id === null ? t('common.all') : loc(c, 'name') }}
                    </button>
                </div>
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-line bg-surface"
            >
                <table class="w-full min-w-[760px] text-sm">
                    <thead
                        class="bg-surface-2 text-left text-xs font-medium text-ink-2"
                    >
                        <tr>
                            <th class="px-4 py-2.5">{{ t('costing.dish') }}</th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.price') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.cost') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.markup') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                {{ t('costing.share') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in shown"
                            :key="d.id"
                            tabindex="0"
                            class="h-12 cursor-pointer border-t border-line hover:bg-surface-2 focus-visible:bg-surface-2 focus-visible:outline-none"
                            :class="!d.is_active ? 'opacity-60' : ''"
                            @click="open(d)"
                            @keydown.enter="open(d)"
                        >
                            <td class="px-4">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{
                                        loc(d, 'name')
                                    }}</span>
                                    <Badge
                                        v-if="d.kind === 'resale'"
                                        tone="outline"
                                        >{{ t('menu.kind_resale') }}</Badge
                                    >
                                    <Badge v-if="!d.has_recipe" tone="ochre">{{
                                        t('costing.no_recipe')
                                    }}</Badge>
                                </span>
                                <span class="text-xs text-ink-3">{{
                                    d.category ? loc(d.category, 'name') : ''
                                }}</span>
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ money(d.price) }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ d.has_recipe ? money(d.cost) : '—' }}
                            </td>
                            <td
                                class="px-4 text-right font-mono num"
                                :class="
                                    d.markup !== null && d.markup < 0
                                        ? 'font-semibold text-danger'
                                        : ''
                                "
                            >
                                {{ percent(d.markup) }}
                            </td>
                            <td class="px-4 text-right font-mono num">
                                {{ d.has_recipe ? percent(d.share) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="!shown.length">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-ink-3"
                            >
                                {{ t('menu.nothing_found') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

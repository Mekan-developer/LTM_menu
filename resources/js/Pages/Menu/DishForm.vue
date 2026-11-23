<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    dish: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    stations: { type: Array, default: () => ['kitchen', 'bar'] },
});

const { t, loc } = useI18n();
const editing = computed(() => props.dish !== null);

const form = useForm({
    category_id: props.dish?.category_id ?? props.categories[0]?.id ?? null,
    name_tm: props.dish?.name_tm ?? '',
    name_ru: props.dish?.name_ru ?? '',
    description_tm: props.dish?.description_tm ?? '',
    description_ru: props.dish?.description_ru ?? '',
    weight: props.dish?.weight ?? '',
    price: props.dish ? money(props.dish.price).replace(/ /g, '') : '',
    station: props.dish?.station ?? 'kitchen',
    is_active: props.dish?.is_active ?? true,
    photo: null,
    remove_photo: false,
});

const preview = ref(props.dish?.photo_url ?? null);

function onPhoto(e) {
    const file = e.target.files?.[0] ?? null;
    form.photo = file;
    form.remove_photo = false;
    preview.value = file
        ? URL.createObjectURL(file)
        : (props.dish?.photo_url ?? null);
}

function clearPhoto() {
    form.photo = null;
    form.remove_photo = true;
    preview.value = null;
}

function submit() {
    if (editing.value) {
        form.transform((d) => ({ ...d, _method: 'put' })).post(
            route('menu.dishes.update', props.dish.id),
            { forceFormData: true },
        );
    } else {
        form.post(route('menu.dishes.store'), { forceFormData: true });
    }
}
</script>

<template>
    <AdminLayout :title="editing ? t('menu.edit_dish') : t('menu.add_dish')">
        <form
            class="mx-auto flex max-w-4xl flex-col gap-5"
            @submit.prevent="submit"
        >
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ editing ? t('menu.edit_dish') : t('menu.add_dish') }}
            </h1>

            <div class="flex flex-wrap gap-5">
                <section
                    class="flex min-w-0 flex-[999_1_420px] flex-col gap-4 rounded-lg border border-line bg-surface p-5"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field
                            :label="t('menu.name_tm')"
                            for="d-ntm"
                            :error="form.errors.name_tm"
                            ><TextInput id="d-ntm" v-model="form.name_tm"
                        /></Field>
                        <Field
                            :label="t('menu.name_ru')"
                            for="d-nru"
                            :error="form.errors.name_ru"
                            ><TextInput id="d-nru" v-model="form.name_ru"
                        /></Field>
                        <Field
                            :label="t('menu.description_tm')"
                            for="d-dtm"
                            :error="form.errors.description_tm"
                        >
                            <textarea
                                id="d-dtm"
                                v-model="form.description_tm"
                                rows="3"
                                class="rounded-md border border-line bg-surface px-3 py-2 text-sm outline-none focus:border-2 focus:border-brand"
                            />
                        </Field>
                        <Field
                            :label="t('menu.description_ru')"
                            for="d-dru"
                            :error="form.errors.description_ru"
                        >
                            <textarea
                                id="d-dru"
                                v-model="form.description_ru"
                                rows="3"
                                class="rounded-md border border-line bg-surface px-3 py-2 text-sm outline-none focus:border-2 focus:border-brand"
                            />
                        </Field>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field
                            :label="t('menu.category')"
                            for="d-cat"
                            :error="form.errors.category_id"
                        >
                            <select
                                id="d-cat"
                                v-model="form.category_id"
                                class="h-9 rounded-md border border-line bg-surface px-2 text-sm"
                            >
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ loc(c, 'name') }}
                                </option>
                            </select>
                        </Field>
                        <Field
                            :label="t('menu.price_tmt')"
                            for="d-price"
                            :error="form.errors.price"
                        >
                            <TextInput
                                id="d-price"
                                v-model="form.price"
                                mono
                                inputmode="decimal"
                                placeholder="0,00"
                            />
                        </Field>
                        <Field
                            :label="t('menu.weight')"
                            for="d-w"
                            :error="form.errors.weight"
                            ><TextInput
                                id="d-w"
                                v-model="form.weight"
                                placeholder="350 g"
                        /></Field>
                    </div>
                    <Field
                        :label="t('menu.station')"
                        :error="form.errors.station"
                    >
                        <div class="flex gap-2" role="radiogroup">
                            <button
                                v-for="s in stations"
                                :key="s"
                                type="button"
                                role="radio"
                                :aria-checked="form.station === s"
                                class="h-9 rounded-md border px-4 text-sm"
                                :class="
                                    form.station === s
                                        ? 'border-ink bg-ink text-paper'
                                        : 'border-line hover:bg-surface-2'
                                "
                                @click="form.station = s"
                            >
                                {{ t(`station.${s}`) }}
                            </button>
                        </div>
                    </Field>
                    <label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 accent-[var(--color-brand)]"
                        />{{ t('menu.visible') }}</label
                    >
                </section>

                <section
                    class="flex min-w-0 flex-[1_1_260px] flex-col gap-3 rounded-lg border border-line bg-surface p-5"
                >
                    <span class="text-[13px] font-medium">{{
                        t('menu.photo')
                    }}</span>
                    <img
                        v-if="preview"
                        :src="preview"
                        alt=""
                        class="aspect-[4/3] w-full rounded-sm object-cover"
                    />
                    <div
                        v-else
                        class="flex aspect-[4/3] w-full items-center justify-center rounded-sm bg-surface-2 text-xs text-ink-3"
                    >
                        4:3 · WebP
                    </div>
                    <label
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-ink-2 text-sm hover:bg-surface-2"
                    >
                        {{ t('menu.choose_photo') }}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="onPhoto"
                        />
                    </label>
                    <Btn
                        v-if="preview"
                        variant="ghost"
                        size="sm"
                        @click="clearPhoto"
                        >{{ t('menu.remove_photo') }}</Btn
                    >
                    <p class="text-xs text-ink-3">{{ t('menu.photo_hint') }}</p>
                    <p v-if="form.errors.photo" class="text-xs text-danger">
                        {{ form.errors.photo }}
                    </p>
                </section>
            </div>

            <div class="flex justify-end gap-2">
                <Btn variant="ghost" :href="route('menu.index')">{{
                    t('common.cancel')
                }}</Btn>
                <Btn type="submit" size="lg" :loading="form.processing">{{
                    t('common.save')
                }}</Btn>
            </div>
        </form>
    </AdminLayout>
</template>

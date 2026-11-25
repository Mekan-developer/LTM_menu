<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { useFirstError } from '@/composables/useFirstError';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import Icon from '@/Components/Icon.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';
import Select from '@/Components/Select.vue';
import Textarea from '@/Components/Textarea.vue';
import MoneyInput from '@/Components/MoneyInput.vue';

const props = defineProps({
    dish: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    stations: { type: Array, default: () => ['kitchen', 'bar'] },
});

const { t, loc } = useI18n();
const editing = computed(() => props.dish !== null);

// Те же ограничения, что в DishRequest.
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const PHOTO_MAX_BYTES = 8192 * 1024;

const plainPrice = (minor) => money(minor).replace(/\s/g, '');

const form = useForm({
    // Новое блюдо: категорию выбирают явно, если вариантов больше одного.
    category_id:
        props.dish?.category_id ??
        (props.categories.length === 1 ? props.categories[0].id : null),
    name_tm: props.dish?.name_tm ?? '',
    name_ru: props.dish?.name_ru ?? '',
    description_tm: props.dish?.description_tm ?? '',
    description_ru: props.dish?.description_ru ?? '',
    weight: props.dish?.weight ?? '',
    price: props.dish ? plainPrice(props.dish.price) : '',
    station: props.dish?.station ?? 'kitchen',
    is_active: props.dish?.is_active ?? true,
    photo: null,
    remove_photo: false,
});

// Фото: preview — то, что увидит пользователь (новый файл или сохранённое фото).
const fileInput = ref(null);
const preview = ref(props.dish?.photo_url ?? null);
const dragging = ref(false);
const photoRejected = ref(null); // ключ перевода
const photoError = computed(() =>
    photoRejected.value ? t(photoRejected.value) : form.errors.photo,
);
let objectUrl = null;

function releaseObjectUrl() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
}
onUnmounted(releaseObjectUrl);

function selectPhoto(file) {
    if (!file || form.processing) return;

    form.clearErrors('photo');

    if (!PHOTO_TYPES.includes(file.type)) {
        photoRejected.value = 'menu.photo_type_error';
        return;
    }
    if (file.size > PHOTO_MAX_BYTES) {
        photoRejected.value = 'menu.photo_size_error';
        return;
    }

    photoRejected.value = null;
    releaseObjectUrl();
    objectUrl = URL.createObjectURL(file);
    form.photo = file;
    form.remove_photo = false;
    preview.value = objectUrl;
}

function onPhoto(e) {
    selectPhoto(e.target.files?.[0]);
    // Иначе повторный выбор того же файла не вызовет change.
    e.target.value = '';
}

function onDrop(e) {
    dragging.value = false;
    selectPhoto(e.dataTransfer?.files?.[0]);
}

function onDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) dragging.value = false;
}

function clearPhoto() {
    releaseObjectUrl();
    photoRejected.value = null;
    form.clearErrors('photo');
    form.photo = null;
    form.remove_photo = true;
    preview.value = null;
}

// Флаг ставим сразу, чтобы второй клик или Enter не отправили форму ещё раз.
const saving = ref(false);
const formElement = ref(null);
const showFirstError = useFirstError(formElement);

function submit() {
    if (saving.value) return;
    saving.value = true;

    const options = {
        forceFormData: true,
        onError: showFirstError,
        onFinish: () => {
            saving.value = false;
        },
    };

    if (editing.value) {
        form.transform((d) => ({ ...d, _method: 'put' })).post(
            route('menu.dishes.update', props.dish.id),
            options,
        );
    } else {
        form.post(route('menu.dishes.store'), options);
    }
}

const heading = 'text-[15px] font-semibold';
const block = 'flex flex-col gap-4 border-t border-line pt-5';
</script>

<template>
    <AdminLayout :title="editing ? t('menu.edit_dish') : t('menu.add_dish')">
        <form
            ref="formElement"
            class="mx-auto flex max-w-4xl flex-col gap-5"
            @submit.prevent="submit"
        >
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ editing ? t('menu.edit_dish') : t('menu.add_dish') }}
            </h1>

            <div class="flex flex-wrap gap-5">
                <div
                    class="@container flex min-w-0 flex-[999_1_420px] flex-col gap-5 rounded-lg border border-line bg-surface p-5"
                >
                    <section
                        class="flex flex-col gap-4"
                        aria-labelledby="d-info"
                    >
                        <h2 id="d-info" :class="heading">
                            {{ t('menu.section_info') }}
                        </h2>
                        <div class="grid grid-cols-1 gap-4 @md:grid-cols-2">
                            <Field
                                v-for="lang in ['tm', 'ru']"
                                :key="`name-${lang}`"
                                :label="t(`menu.name_${lang}`)"
                                :for="`d-n${lang}`"
                                :error="form.errors[`name_${lang}`]"
                            >
                                <TextInput
                                    :id="`d-n${lang}`"
                                    v-model="form[`name_${lang}`]"
                                    maxlength="160"
                                    :placeholder="t('menu.name_ph')"
                                />
                            </Field>
                            <Field
                                v-for="lang in ['tm', 'ru']"
                                :key="`description-${lang}`"
                                :label="t(`menu.description_${lang}`)"
                                :for="`d-d${lang}`"
                                :error="form.errors[`description_${lang}`]"
                            >
                                <Textarea
                                    :id="`d-d${lang}`"
                                    v-model="form[`description_${lang}`]"
                                    maxlength="1000"
                                    :placeholder="t('menu.description_ph')"
                                />
                            </Field>
                        </div>
                    </section>

                    <section :class="block" aria-labelledby="d-pricing">
                        <h2 id="d-pricing" :class="heading">
                            {{ t('menu.section_pricing') }}
                        </h2>
                        <div class="grid grid-cols-1 gap-4 @sm:grid-cols-2">
                            <Field
                                :label="t('menu.category')"
                                for="d-cat"
                                :error="form.errors.category_id"
                            >
                                <Select
                                    id="d-cat"
                                    v-model="form.category_id"
                                    :placeholder="t('menu.category_ph')"
                                >
                                    <option
                                        v-for="c in categories"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ loc(c, 'name') }}
                                    </option>
                                </Select>
                            </Field>
                            <Field
                                :label="t('menu.price_tmt')"
                                for="d-price"
                                :error="form.errors.price"
                            >
                                <MoneyInput
                                    id="d-price"
                                    v-model="form.price"
                                    :placeholder="t('menu.price_ph')"
                                />
                            </Field>
                            <Field
                                class="@sm:col-span-2"
                                :label="t('menu.weight')"
                                for="d-w"
                                :error="form.errors.weight"
                            >
                                <!-- Свободный текст: «350 g», «5 sany», «1/4». -->
                                <TextInput
                                    id="d-w"
                                    v-model="form.weight"
                                    maxlength="40"
                                    :placeholder="t('menu.weight_ph')"
                                />
                            </Field>
                        </div>
                    </section>

                    <section :class="block" aria-labelledby="d-preparation">
                        <h2 id="d-preparation" :class="heading">
                            {{ t('menu.section_preparation') }}
                        </h2>
                        <Field
                            :label="t('menu.station')"
                            :error="form.errors.station"
                            :hint="t('menu.station_hint')"
                        >
                            <div
                                class="flex flex-wrap gap-2"
                                role="radiogroup"
                                :aria-label="t('menu.station')"
                            >
                                <label
                                    v-for="s in stations"
                                    :key="s"
                                    class="inline-flex h-9 min-w-28 cursor-pointer items-center justify-center gap-2 rounded-md border px-4 text-sm transition-colors duration-[120ms] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand"
                                    :class="
                                        form.station === s
                                            ? 'border-ink bg-ink text-paper'
                                            : 'border-line hover:border-ink-3 hover:bg-surface-2'
                                    "
                                >
                                    <input
                                        v-model="form.station"
                                        type="radio"
                                        name="station"
                                        :value="s"
                                        class="sr-only"
                                        :aria-invalid="
                                            form.errors.station
                                                ? 'true'
                                                : undefined
                                        "
                                    />
                                    <Icon
                                        v-if="form.station === s"
                                        name="check"
                                        :size="16"
                                    />
                                    {{ t(`station.${s}`) }}
                                </label>
                            </div>
                        </Field>
                    </section>

                    <section :class="block" aria-labelledby="d-visibility">
                        <h2 id="d-visibility" :class="heading">
                            {{ t('menu.section_visibility') }}
                        </h2>
                        <label class="flex cursor-pointer items-start gap-2.5">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="mt-0.5 size-4 shrink-0 accent-[var(--color-brand)]"
                                aria-describedby="d-visible-hint"
                            />
                            <span class="flex flex-col gap-1">
                                <span class="text-sm font-medium">{{
                                    t('menu.visible')
                                }}</span>
                                <span
                                    id="d-visible-hint"
                                    class="text-xs text-ink-3"
                                    >{{ t('menu.visible_hint') }}</span
                                >
                            </span>
                        </label>
                    </section>
                </div>

                <section
                    class="flex min-w-0 flex-[1_1_260px] flex-col rounded-lg border border-line bg-surface p-5"
                    aria-labelledby="d-photo"
                    @dragover.prevent="dragging = true"
                    @dragleave="onDragLeave"
                    @drop.prevent="onDrop"
                >
                    <!-- Когда колонка переносится под форму, фото не растягивается на всю ширину. -->
                    <div class="mx-auto flex w-full max-w-md flex-col gap-3">
                        <h2 id="d-photo" :class="heading">
                            {{ t('menu.photo') }}
                        </h2>
                        <input
                            ref="fileInput"
                            type="file"
                            :accept="PHOTO_TYPES.join(',')"
                            class="hidden"
                            @change="onPhoto"
                        />

                        <template v-if="preview">
                            <img
                                :src="preview"
                                :alt="t('menu.photo')"
                                class="aspect-[4/3] w-full rounded-md border object-cover"
                                :class="
                                    dragging
                                        ? 'border-brand ring-1 ring-brand'
                                        : 'border-line'
                                "
                            />
                            <p
                                v-if="form.photo"
                                class="truncate text-xs text-ink-2"
                                :title="form.photo.name"
                            >
                                {{ form.photo.name }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Btn
                                    variant="secondary"
                                    class="flex-1"
                                    :data-invalid="!!photoError"
                                    :disabled="form.processing"
                                    @click="fileInput.click()"
                                >
                                    <Icon name="upload" :size="16" />
                                    {{ t('menu.replace_photo') }}
                                </Btn>
                                <Btn
                                    variant="danger"
                                    class="flex-1"
                                    :disabled="form.processing"
                                    @click="clearPhoto"
                                >
                                    <Icon name="trash" :size="16" />
                                    {{ t('menu.remove_photo') }}
                                </Btn>
                            </div>
                        </template>

                        <button
                            v-else
                            type="button"
                            class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 rounded-md border border-dashed px-4 text-center transition-colors duration-[120ms]"
                            :class="[
                                dragging
                                    ? 'border-brand bg-brand-soft'
                                    : photoError
                                      ? 'border-danger bg-danger-soft'
                                      : 'border-ink-3 hover:border-brand hover:bg-surface-2',
                            ]"
                            :data-invalid="!!photoError"
                            :disabled="form.processing"
                            @click="fileInput.click()"
                        >
                            <span
                                class="flex size-11 items-center justify-center rounded-full bg-brand-soft text-brand"
                            >
                                <Icon name="upload" :size="22" />
                            </span>
                            <span class="text-sm font-semibold">{{
                                t('menu.choose_photo')
                            }}</span>
                            <span
                                class="text-xs text-ink-3 pointer-coarse:hidden"
                                >{{ t('menu.drop_photo') }}</span
                            >
                        </button>

                        <p
                            v-if="photoError"
                            class="text-xs text-danger"
                            role="alert"
                        >
                            {{ photoError }}
                        </p>
                        <p class="text-xs text-ink-3">
                            {{ t('menu.photo_hint') }}
                        </p>
                    </div>
                </section>
            </div>

            <div class="flex justify-end gap-2">
                <Btn variant="ghost" size="lg" :href="route('menu.index')">{{
                    t('common.cancel')
                }}</Btn>
                <Btn type="submit" size="lg" class="min-w-36" :loading="saving">
                    {{ t('common.save') }}
                </Btn>
            </div>
        </form>
    </AdminLayout>
</template>

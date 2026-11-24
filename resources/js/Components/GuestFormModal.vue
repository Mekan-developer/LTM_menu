<script setup>
// Карточка гостя: создать или изменить (ТЗ 2.1).
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import Btn from '@/Components/Btn.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { phone as fmtPhone } from '@/utils/format';

const props = defineProps({
    show: { type: Boolean, default: false },
    guest: { type: Object, default: null }, // null — новый гость
});
const emit = defineEmits(['close']);
const { t } = useI18n();

const form = useForm({
    name: '',
    phone: '',
    birthday: '',
    address: '',
    comment: '',
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.name = props.guest?.name ?? '';
        form.phone = props.guest ? fmtPhone(props.guest.phone) : '';
        form.birthday = props.guest?.birthday ?? '';
        form.address = props.guest?.address ?? '';
        form.comment = props.guest?.comment ?? '';
    },
);

function save() {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    if (props.guest) form.put(route('guests.update', props.guest.id), opts);
    else form.post(route('guests.store'), opts);
}
</script>

<template>
    <Modal
        :show="show"
        :title="guest ? t('guests.edit') : t('guests.add')"
        @close="emit('close')"
    >
        <form
            id="guest-form"
            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
            @submit.prevent="save"
        >
            <Field
                :label="t('guests.name')"
                for="g-name"
                :error="form.errors.name"
                ><TextInput id="g-name" v-model="form.name" autocomplete="off"
            /></Field>
            <Field
                :label="t('guests.phone')"
                for="g-phone"
                :error="form.errors.phone"
                :hint="t('guests.phone_hint')"
                ><TextInput
                    id="g-phone"
                    v-model="form.phone"
                    mono
                    inputmode="tel"
                    placeholder="+993 6X XXXXXX"
            /></Field>
            <Field
                :label="t('guests.birthday')"
                for="g-bd"
                :error="form.errors.birthday"
                ><TextInput id="g-bd" v-model="form.birthday" type="date" mono
            /></Field>
            <Field
                :label="t('guests.address')"
                for="g-addr"
                :error="form.errors.address"
                ><TextInput id="g-addr" v-model="form.address"
            /></Field>
            <div class="sm:col-span-2">
                <Field
                    :label="t('guests.comment')"
                    for="g-comment"
                    :error="form.errors.comment"
                    :hint="t('guests.comment_hint')"
                >
                    <textarea
                        id="g-comment"
                        v-model="form.comment"
                        rows="3"
                        maxlength="1000"
                        class="w-full resize-none rounded-md border border-line bg-paper px-3 py-2 text-sm outline-none focus:border-brand"
                    />
                </Field>
            </div>
        </form>
        <template #footer>
            <Btn variant="ghost" @click="emit('close')">{{
                t('common.cancel')
            }}</Btn>
            <Btn type="submit" form="guest-form" :loading="form.processing">{{
                t('common.save')
            }}</Btn>
        </template>
    </Modal>
</template>

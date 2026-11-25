<script setup>
// Сумма в TMT: цифры и один разделитель, до двух знаков. Недопустимый ввод откатываем,
// а не «чиним» — иначе вставка «1.234,50» молча превратилась бы в другую сумму.
import TextInput from '@/Components/TextInput.vue';
import { money, toMinor } from '@/utils/format';

const model = defineModel({ type: String, default: '' });

defineProps({ id: { type: String, default: null } });

const PATTERN = /^\d{0,6}([.,]\d{0,2})?$/;
const compact = (value) => String(value ?? '').replace(/\s/g, '');

// Последнее допустимое значение: запоминаем перед изменением, чтобы было куда откатиться.
let last = '';
const remember = () => (last = compact(model.value));

function onInput(e) {
    const input = e.target;
    const typed = input.value;

    if (PATTERN.test(compact(typed))) last = compact(typed);

    if (typed !== last) {
        const caret = Math.max(
            0,
            input.selectionStart - (typed.length - last.length),
        );
        input.value = last;
        input.setSelectionRange(caret, caret);
    }
    model.value = last;
}

function format() {
    model.value = /\d/.test(model.value ?? '')
        ? compact(money(toMinor(model.value)))
        : '';
}
</script>

<template>
    <TextInput
        :id="id"
        v-model="model"
        mono
        inputmode="decimal"
        autocomplete="off"
        placeholder="0,00"
        @beforeinput="remember"
        @input="onInput"
        @blur="format"
    />
</template>

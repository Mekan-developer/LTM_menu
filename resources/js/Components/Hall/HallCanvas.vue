<script setup>
// Полотно зала в режиме «Работа»: точечная сетка 20 px, абсолютное позиционирование.
// На узком экране полотно уменьшается под ширину (transform: scale), иначе — прокрутка.
import { computed, onMounted, onUnmounted, ref } from 'vue';
import HallTable from './HallTable.vue';
import HallElementView from './HallElementView.vue';
import { box, useFitScale } from './hall';

const props = defineProps({
    hall: { type: Object, required: true },
    tables: { type: Array, default: () => [] },
    elements: { type: Array, default: () => [] },
    selectedId: { type: Number, default: null },
    picking: { type: Boolean, default: false },
    // Обрезать пустое поле справа и снизу — на телефоне столы крупнее.
    crop: { type: Boolean, default: false },
});
defineEmits(['select']);

// Видимая область: всё полотно или только занятая часть (+ поле 40 px).
const area = computed(() => {
    if (!props.crop) return { w: props.hall.width, h: props.hall.height };
    const boxes = [...props.tables.map(box), ...props.elements.map(box)];
    const right = Math.max(200, ...boxes.map((b) => b.x + b.w)) + 40;
    const bottom = Math.max(160, ...boxes.map((b) => b.y + b.h)) + 40;
    return {
        w: Math.min(props.hall.width, right),
        h: Math.min(props.hall.height, bottom),
    };
});

const wrap = ref(null);
const { scale } = useFitScale(wrap, () => area.value.w);

// Время «занят N мин» обновляется раз в 30 секунд.
const now = ref(Date.now());
let timer = null;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 30000)));
onUnmounted(() => clearInterval(timer));

const outer = computed(() => ({
    width: `${area.value.w * scale.value}px`,
    height: `${area.value.h * scale.value}px`,
}));
</script>

<template>
    <div ref="wrap" class="w-full overflow-auto">
        <div :style="outer" class="relative">
            <div
                class="hall-grid absolute top-0 left-0 origin-top-left overflow-hidden rounded-lg border border-line"
                :style="{
                    width: `${area.w}px`,
                    height: `${area.h}px`,
                    transform: `scale(${scale})`,
                }"
            >
                <HallElementView
                    v-for="e in elements"
                    :key="`e${e.id}`"
                    :element="e"
                />
                <HallTable
                    v-for="tb in tables"
                    :key="tb.id"
                    :table="tb"
                    :selected="tb.id === selectedId"
                    :picking="picking"
                    :now="now"
                    @select="$emit('select', $event)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.hall-grid {
    background-color: var(--color-surface);
    background-image: radial-gradient(
        circle,
        var(--color-line) 1px,
        transparent 1.2px
    );
    background-size: 20px 20px;
}
</style>

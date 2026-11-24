// Геометрия схемы зала (ТЗ 2.2). Размер стола хранится без поворота;
// при rotation = 90 ширина и высота на полотне меняются местами.
import { onMounted, onUnmounted, ref } from 'vue';

export const GRID = 20;
export const MIN_TABLE = 60;
export const MIN_ELEMENT = 20;

export function box(o) {
    const r = Number(o.rotation) === 90;
    return {
        x: o.pos_x,
        y: o.pos_y,
        w: r ? o.height : o.width,
        h: r ? o.width : o.height,
    };
}

export const overlaps = (a, b) =>
    a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;

export const snap = (v, step = GRID) => Math.round(v / step) * step;

export const clamp = (v, min, max) => Math.min(Math.max(v, min), max);

// Заготовки для панели «Добавить».
export const TABLE_PRESETS = {
    round: { shape: 'round', seats: 2, width: 80, height: 80 },
    square: { shape: 'square', seats: 4, width: 80, height: 80 },
    rect: { shape: 'rect', seats: 6, width: 160, height: 80 },
};
export const ELEMENT_PRESETS = {
    bar: { width: 240, height: 60 },
    entrance: { width: 120, height: 40 },
    window: { width: 20, height: 200 },
    wall: { width: 200, height: 20 },
    label: { width: 160, height: 40 },
};

// Масштаб полотна под ширину контейнера (только уменьшение), пересчёт по ResizeObserver.
export function useFitScale(container, hallWidth) {
    const scale = ref(1);
    let ro = null;
    const update = () => {
        const w = container.value?.clientWidth ?? 0;
        scale.value = w && hallWidth() ? Math.min(1, w / hallWidth()) : 1;
    };
    onMounted(() => {
        update();
        ro = new ResizeObserver(update);
        if (container.value) ro.observe(container.value);
    });
    onUnmounted(() => ro?.disconnect());
    return { scale, update };
}

// «12 мин», «1 ч 05» — сколько стол занят.
export function sinceLabel(iso, now = Date.now()) {
    if (!iso) return '';
    const min = Math.max(
        0,
        Math.floor((now - new Date(iso).getTime()) / 60000),
    );
    if (min < 60) return `${min}′`;
    return `${Math.floor(min / 60)}:${String(min % 60).padStart(2, '0')}`;
}

// Оформление объектов зала — общее для «Работы» и «Редактора».
export const ELEMENT_CLASS = {
    bar: 'rounded-md bg-surface-2 border border-line text-ink-2',
    entrance: 'rounded-sm border-2 border-dashed border-ink-3 text-ink-3',
    window: 'rounded-sm bg-brand-soft border border-brand/30',
    wall: 'rounded-sm bg-ink-3',
    label: 'text-ink-3',
};

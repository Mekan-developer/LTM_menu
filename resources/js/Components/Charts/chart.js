import { onMounted, onUnmounted, ref } from 'vue';
// Общие помощники для графиков отчётов (ТЗ 2.10).
// Цвета серий проверены валидатором палитры на поверхности #fbf8f2:
// зелёный и терракота — те же оттенки «Тамдыр», но насыщеннее, чтобы различаться при дальтонизме.
export const SERIES = {
    primary: '#238a5e',
    secondary: '#c4572c',
    previous: '#8b8f88',
};

// «Круглые» деления оси: 0 и ещё 3–4 значения с шагом 1/2/5 × 10^n.
export function niceTicks(max, count = 4) {
    if (max <= 0) return [0, 1];
    const raw = max / count;
    const pow = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 5, 10].map((m) => m * pow).find((s) => s >= raw);
    const top = Math.ceil(max / step) * step;
    const ticks = [];
    for (let v = 0; v <= top + step / 2; v += step) ticks.push(v);
    return ticks;
}

// Подписи оси в TMT без копеек: 123450 тенге → «1 234».
export const axisMoney = (minor) =>
    Math.round(minor / 100)
        .toLocaleString('ru-RU')
        .replace(/ /g, ' ');

export const shortDate = (iso) => {
    const [, m, d] = iso.split('-');
    return `${d}.${m}`;
};

// Ширина элемента в пикселях; обновляется при ресайзе (ResizeObserver).
export function useWidth(el, fallback = 720) {
    const width = ref(fallback);
    let ro = null;
    onMounted(() => {
        if (!el.value) return;
        width.value = Math.max(280, el.value.clientWidth || fallback);
        ro = new ResizeObserver(([entry]) => {
            width.value = Math.max(280, Math.round(entry.contentRect.width));
        });
        ro.observe(el.value);
    });
    onUnmounted(() => ro?.disconnect());
    return width;
}

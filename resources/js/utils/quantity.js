/** Количество на складе: до 3 знаков после запятой, без лишних нулей, разряды пробелом. «1 234,5» */
export function qty(value) {
    const n = Number(value) || 0;
    const [int, frac = ''] = Math.abs(n).toFixed(3).split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const tail = frac.replace(/0+$/, '');
    return `${n < 0 ? '−' : ''}${grouped}${tail ? `,${tail}` : ''}`;
}

/** Ввод количества «1,5» | «1 000» → 1.5 | 1000; пусто или мусор → null. */
export function parseQty(input) {
    const s = String(input ?? '')
        .replace(/\s/g, '')
        .replace(',', '.');
    if (s === '' || !/^-?\d*\.?\d*$/.test(s)) return null;
    const n = Number(s);
    return Number.isFinite(n) ? n : null;
}

// Валюта TMT, формат «1 234,50 TMT». Деньги приходят с сервера в тенге (1 TMT = 100).
export function money(minor, withCurrency = false) {
    const value = (Number(minor) || 0) / 100;
    const [int, frac] = Math.abs(value).toFixed(2).split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const text = `${value < 0 ? '−' : ''}${grouped},${frac}`;

    return withCurrency ? `${text} TMT` : text;
}

/** «1 234,50» | «85» → 123450 | 8500 */
export function toMinor(input) {
    const normalized = String(input ?? '')
        .replace(/[\s ]/g, '')
        .replace(',', '.');
    const value = Number.parseFloat(normalized);

    return Number.isFinite(value) ? Math.round(value * 100) : 0;
}

/** Время Ашхабада: ДД.ММ.ГГГГ, ЧЧ:ММ */
const TZ = 'Asia/Ashgabat';

export function date(iso) {
    if (!iso) return '';
    return new Intl.DateTimeFormat('ru-RU', {
        timeZone: TZ,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(iso));
}

export function time(iso) {
    if (!iso) return '';
    return new Intl.DateTimeFormat('ru-RU', {
        timeZone: TZ,
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

export function dateTime(iso) {
    return iso ? `${date(iso)} ${time(iso)}` : '';
}

/** «mm:ss» с момента iso до now (мс). */
export function elapsed(iso, now = Date.now()) {
    if (!iso) return '00:00';
    const sec = Math.max(0, Math.floor((now - new Date(iso).getTime()) / 1000));
    const m = Math.floor(sec / 60);
    const s = sec % 60;

    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

export function minutesSince(iso, now = Date.now()) {
    return iso ? (now - new Date(iso).getTime()) / 60000 : 0;
}

// «+99361234567» → «+993 61 234567» (ТЗ 2.1).
export function phone(value) {
    const m = /^\+993(\d{2})(\d{6})$/.exec(value ?? '');
    return m ? `+993 ${m[1]} ${m[2]}` : (value ?? '');
}

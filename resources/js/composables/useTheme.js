import { onMounted, onUnmounted, ref } from 'vue';

// Светлая / тёмная тема экранов персонала: админка, официант, касса, вход.
// Выбор один на устройство (localStorage), без выбора — системная тема,
// у официанта — тёмная (ночная смена). Гостевые страницы и кухня тему не меняют:
// у них свои палитры, а класс снимается, когда layout персонала уходит со страницы.
// Тот же выбор до загрузки JS применяет скрипт в app.blade.php — без вспышки светлого фона.
const KEY = 'staff-theme';
const CLASS = 'theme-night';

export const dark = ref(false);

function saved() {
    try {
        const v = localStorage.getItem(KEY);
        if (v === 'dark' || v === 'light') return v;
        // Прежний ключ экрана официанта.
        const old = localStorage.getItem('waiter-theme');
        if (old) return old === 'day' ? 'light' : 'dark';
    } catch {
        /* приватный режим */
    }
    return null;
}

const apply = () =>
    document.documentElement.classList.toggle(CLASS, dark.value);

export function toggleTheme() {
    dark.value = !dark.value;
    apply();
    try {
        localStorage.setItem(KEY, dark.value ? 'dark' : 'light');
    } catch {
        /* */
    }
}

/** Подключает тему в layout. preferDark — тема по умолчанию вместо системной. */
export function useTheme({ preferDark = false } = {}) {
    // При переходе между страницами onUnmounted старого layout срабатывает после setup нового,
    // поэтому класс ставим в onMounted — уже после снятия класса старым экземпляром.
    onMounted(() => {
        const s = saved();
        dark.value = s
            ? s === 'dark'
            : preferDark ||
              window.matchMedia('(prefers-color-scheme: dark)').matches;
        apply();
    });
    onUnmounted(() => document.documentElement.classList.remove(CLASS));

    return { dark, toggleTheme };
}

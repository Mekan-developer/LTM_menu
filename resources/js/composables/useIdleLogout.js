import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Общий планшет: сеанс закрывается после N секунд бездействия (ТЗ 1.3).
 * За 10 секунд до выхода показываем полосу-предупреждение.
 */
export function useIdleLogout() {
    const page = usePage();
    const enabled = computed(() => Boolean(page.props.auth?.shared_device));
    const limit = computed(() =>
        Number(page.props.app?.shared_idle_seconds ?? 60),
    );
    const left = ref(limit.value);
    let last = Date.now();
    let timer = null;

    // Сеанс один на все вкладки, поэтому и активность общая: иначе вкладка, оставленная
    // в фоне, выкинула бы сотрудника, пока он работает в соседней.
    const KEY = 'staff-last-activity';
    let shared = 0;
    const share = () => {
        if (last - shared < 1000) return;
        shared = last;
        try {
            localStorage.setItem(KEY, String(last));
        } catch {
            /* приватный режим */
        }
    };
    const latest = () => {
        try {
            return Math.max(last, Number(localStorage.getItem(KEY)) || 0);
        } catch {
            return last;
        }
    };

    const touch = () => {
        last = Date.now();
        left.value = limit.value;
        share();
    };
    const events = ['pointerdown', 'keydown', 'touchstart', 'scroll'];

    onMounted(() => {
        if (!enabled.value) return;
        touch();
        events.forEach((e) =>
            window.addEventListener(e, touch, { passive: true }),
        );
        timer = setInterval(() => {
            left.value = Math.max(
                0,
                limit.value - Math.floor((Date.now() - latest()) / 1000),
            );
            if (left.value === 0) {
                clearInterval(timer);
                router.post(route('logout'));
            }
        }, 1000);
    });

    onUnmounted(() => {
        clearInterval(timer);
        events.forEach((e) => window.removeEventListener(e, touch));
    });

    const warning = computed(() => enabled.value && left.value <= 10);

    return { enabled, left, warning, touch };
}

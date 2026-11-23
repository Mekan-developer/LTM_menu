import { onMounted, onUnmounted, ref } from 'vue';

/** Реактивное «сейчас» для таймеров (раз в секунду). */
export function useNow(intervalMs = 1000) {
    const now = ref(Date.now());
    let timer = null;

    onMounted(() => {
        timer = setInterval(() => {
            now.value = Date.now();
        }, intervalMs);
    });
    onUnmounted(() => clearInterval(timer));

    return now;
}

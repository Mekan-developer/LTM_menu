import { onMounted, onUnmounted } from 'vue';

/**
 * Подписка на приватный канал Reverb + запасной опрос.
 * onEvent(payload|null): payload — событие из сокета, null — плановый опрос.
 */
export function useRealtime(channel, event, onEvent, pollMs = 15000) {
    let timer = null;

    onMounted(() => {
        if (window.Echo) {
            window.Echo.private(channel).listen(`.${event}`, (payload) =>
                onEvent(payload),
            );
        }

        timer = setInterval(
            () => {
                if (document.visibilityState === 'visible') onEvent(null);
            },
            window.Echo ? pollMs * 2 : pollMs,
        );
    });

    onUnmounted(() => {
        clearInterval(timer);
        window.Echo?.leave(channel);
    });
}

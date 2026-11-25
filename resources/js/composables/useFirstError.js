import { nextTick } from 'vue';

/** После ответа с ошибками прокручивает длинную форму к первому неверному полю. */
export function useFirstError(root) {
    return async () => {
        await nextTick();
        const target = root.value?.querySelector(
            '[aria-invalid="true"], [data-invalid="true"]',
        );
        target?.scrollIntoView({ block: 'center' });
        target?.focus({ preventScroll: true });
    };
}

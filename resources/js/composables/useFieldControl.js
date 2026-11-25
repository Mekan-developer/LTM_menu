import { computed, inject } from 'vue';

export const FIELD = Symbol('field');

/**
 * Контрол внутри <Field for="x"> с тем же id сам получает состояние ошибки и aria-связи —
 * на страницах не нужно повторять :invalid у каждого поля.
 */
export function useFieldControl(props) {
    const field = inject(FIELD, null);
    const own = computed(() =>
        field && props.id && field.for === props.id ? field : null,
    );
    const invalid = computed(
        () => Boolean(props.invalid) || Boolean(own.value?.error),
    );
    const aria = computed(() => {
        const note = own.value?.error
            ? 'error'
            : own.value?.hint
              ? 'hint'
              : null;

        return {
            'aria-invalid': invalid.value ? 'true' : undefined,
            'aria-describedby': note ? `${props.id}-${note}` : undefined,
        };
    });

    return { invalid, aria };
}

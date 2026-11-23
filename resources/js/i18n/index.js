import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import tk from './tk';
import ru from './ru';

const dictionaries = { tk, ru };

function lookup(dict, key) {
    return key
        .split('.')
        .reduce((node, part) => (node == null ? undefined : node[part]), dict);
}

export function translate(locale, key, params = {}) {
    const raw =
        lookup(dictionaries[locale] ?? tk, key) ?? lookup(ru, key) ?? key;

    return typeof raw === 'string'
        ? raw.replace(/\{(\w+)\}/g, (_, name) => params[name] ?? `{${name}}`)
        : raw;
}

/**
 * const { t, loc, locale } = useI18n();
 * t('menu.title') · loc(dish, 'name') → dish.name_tm | dish.name_ru
 */
export function useI18n() {
    const page = usePage();
    const locale = computed(() => page.props.locale ?? 'tk');

    const t = (key, params) => translate(locale.value, key, params);
    const loc = (obj, field) =>
        obj
            ? (obj[`${field}_${locale.value === 'ru' ? 'ru' : 'tm'}`] ??
              obj[`${field}_ru`])
            : '';

    return { t, loc, locale };
}

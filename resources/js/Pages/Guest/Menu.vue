<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import LangSwitch from '@/Components/LangSwitch.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    table: { type: Object, required: true },
    menu: { type: Array, default: () => [] },
    version: { type: String, default: '' },
    restaurant: { type: String, default: '' },
});

const { t, loc } = useI18n();

/*
 * Меню сохраняется на телефоне гостя: при слабой связи открывается сразу,
 * при появлении связи меню, цены и стоп-лист обновляются в фоне (ТЗ 2.3).
 */
const menuKey = 'guest-menu';
const cartKey = `guest-cart:${props.table.token}`;
const liveMenu = ref(props.menu);
const liveVersion = ref(props.version);

const read = (k, fallback) => {
    try {
        return JSON.parse(localStorage.getItem(k)) ?? fallback;
    } catch {
        return fallback;
    }
};
const write = (k, v) => {
    try {
        localStorage.setItem(k, JSON.stringify(v));
    } catch {
        /* приватный режим */
    }
};

const cart = ref(read(cartKey, {})); // { [dishId]: { qty, price } }
watch(cart, (v) => write(cartKey, v), { deep: true });

const dishes = computed(() =>
    Object.fromEntries(
        liveMenu.value.flatMap((c) => c.dishes).map((d) => [d.id, d]),
    ),
);
const lines = computed(() =>
    Object.entries(cart.value)
        .map(([id, l]) => ({ dish: dishes.value[id], ...l }))
        .filter((l) => l.dish),
);
const count = computed(() => lines.value.reduce((s, l) => s + l.qty, 0));
const total = computed(() =>
    lines.value.reduce((s, l) => s + l.qty * l.price, 0),
);

function add(d) {
    const cur = cart.value[d.id];
    cart.value = {
        ...cart.value,
        [d.id]: { qty: (cur?.qty ?? 0) + 1, price: cur?.price ?? d.price },
    };
}
function dec(id) {
    const cur = cart.value[id];
    if (!cur) return;
    const next = { ...cart.value };
    if (cur.qty <= 1) delete next[id];
    else next[id] = { ...cur, qty: cur.qty - 1 };
    cart.value = next;
}

async function refreshMenu() {
    try {
        const { data } = await axios.get(
            route('guest.menu.json', props.table.token),
        );
        if (data.version !== liveVersion.value) {
            liveMenu.value = data.menu;
            liveVersion.value = data.version;
            write(menuKey, { version: data.version, menu: data.menu });
        }
    } catch {
        /* нет связи — работаем с сохранённым меню */
    }
}

let timer = null;
onMounted(() => {
    write(menuKey, { version: props.version, menu: props.menu });
    timer = setInterval(refreshMenu, 60000);
    window.addEventListener('online', refreshMenu);
});
onUnmounted(() => {
    clearInterval(timer);
    window.removeEventListener('online', refreshMenu);
});

const activeCat = ref(props.menu[0]?.id ?? null);
const scrollTo = (id) => {
    activeCat.value = id;
    document
        .getElementById(`cat-${id}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

// Корзина и отправка
const cartOpen = ref(false);
const comment = ref('');
const sending = ref(false);
const problems = ref([]);
const error = ref('');

async function submit() {
    if (!lines.value.length || sending.value) return;
    sending.value = true;
    error.value = '';
    problems.value = [];
    try {
        const { data } = await axios.post(
            route('guest.orders.store', props.table.token),
            {
                items: lines.value.map((l) => ({
                    dish_id: l.dish.id,
                    quantity: l.qty,
                    price: l.price,
                })),
                comment: comment.value || null,
            },
        );
        cart.value = {};
        comment.value = '';
        router.visit(data.status_url);
    } catch (e) {
        if (e.response?.status === 409) {
            // Цена изменилась или блюдо закончилось — показываем до повторной отправки.
            await refreshMenu();
            const next = { ...cart.value };
            problems.value = e.response.data.problems.map((p) => {
                const name = loc(
                    dishes.value[p.dish_id] ??
                        props.menu
                            .flatMap((c) => c.dishes)
                            .find((d) => d.id === p.dish_id),
                    'name',
                );
                if (p.type === 'price_changed' && next[p.dish_id])
                    next[p.dish_id] = { ...next[p.dish_id], price: p.new };
                if (p.type === 'unavailable') delete next[p.dish_id];
                return { ...p, name };
            });
            cart.value = next;
        } else if (!navigator.onLine || !e.response) {
            error.value = t('guest.offline');
        } else {
            error.value = e.response?.data?.message ?? t('guest.error');
        }
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Head :title="restaurant" />
    <div class="min-h-[100dvh] bg-g-bg font-guest text-g-ink">
        <header class="sticky top-0 z-20 bg-g-bg">
            <div class="flex items-center gap-2.5 px-5 pt-4 pb-3">
                <div class="flex flex-1 flex-col">
                    <span class="font-serif text-[22px] leading-6 italic">{{
                        restaurant
                    }}</span>
                    <span class="text-xs text-g-muted">{{
                        t('guest.no_registration')
                    }}</span>
                </div>
                <span
                    class="flex h-8 items-center rounded-sm bg-g-ink px-2.5 text-[13px] font-semibold text-g-bg"
                    >{{ t('tables.table') }} {{ table.number }}</span
                >
                <LangSwitch />
            </div>
            <nav
                class="flex gap-5 overflow-x-auto border-b border-g-line px-5"
                :aria-label="t('nav.menu')"
            >
                <button
                    v-for="c in liveMenu"
                    :key="c.id"
                    type="button"
                    class="h-11 shrink-0 border-b-2 text-[15px]"
                    :class="
                        activeCat === c.id
                            ? 'border-g-accent font-semibold'
                            : 'border-transparent text-g-muted'
                    "
                    @click="scrollTo(c.id)"
                >
                    {{ loc(c, 'name') }}
                </button>
            </nav>
        </header>

        <main class="mx-auto flex max-w-xl flex-col gap-8 px-5 pt-5 pb-32">
            <section
                v-for="c in liveMenu"
                :id="`cat-${c.id}`"
                :key="c.id"
                class="flex scroll-mt-28 flex-col gap-4"
            >
                <h2 class="font-serif text-[30px] leading-8 font-semibold">
                    {{ loc(c, 'name') }}
                </h2>
                <article
                    v-for="d in c.dishes"
                    :key="d.id"
                    class="flex flex-col gap-2.5 border-t border-g-line pt-4 first-of-type:border-t-0 first-of-type:pt-0"
                >
                    <img
                        v-if="d.photo_url"
                        :src="d.photo_url"
                        :alt="loc(d, 'name')"
                        loading="lazy"
                        class="aspect-[4/3] w-full rounded-sm object-cover"
                    />
                    <div class="flex items-baseline justify-between gap-3">
                        <h3
                            class="font-serif text-[22px] leading-[26px] font-semibold"
                        >
                            {{ loc(d, 'name') }}
                        </h3>
                        <span
                            class="text-[17px] font-semibold whitespace-nowrap num"
                            >{{ money(d.price, true) }}</span
                        >
                    </div>
                    <p
                        v-if="loc(d, 'description') || d.weight"
                        class="text-sm leading-5 text-g-muted"
                    >
                        {{
                            [loc(d, 'description'), d.weight]
                                .filter(Boolean)
                                .join('. ')
                        }}
                    </p>
                    <div
                        v-if="cart[d.id]"
                        class="flex h-12 items-center justify-between rounded-md bg-g-ink pr-1 pl-4 text-g-bg"
                    >
                        <span class="text-[15px] font-medium">{{
                            t('guest.in_cart')
                        }}</span>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="h-10 w-11 rounded-sm bg-white/10 text-xl"
                                :aria-label="t('guest.less')"
                                @click="dec(d.id)"
                            >
                                −
                            </button>
                            <span class="w-8 text-center font-semibold num">{{
                                cart[d.id].qty
                            }}</span>
                            <button
                                type="button"
                                class="h-10 w-11 rounded-sm bg-white/10 text-xl"
                                :aria-label="t('guest.more')"
                                @click="add(d)"
                            >
                                +
                            </button>
                        </div>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="h-12 rounded-md border-[1.5px] border-g-ink text-base font-semibold"
                        @click="add(d)"
                    >
                        + {{ t('guest.add') }}
                    </button>
                </article>
            </section>
            <p v-if="!liveMenu.length" class="py-16 text-center text-g-muted">
                {{ t('guest.menu_empty') }}
            </p>
        </main>

        <div
            v-if="count"
            class="fixed inset-x-0 bottom-0 z-30 bg-gradient-to-t from-g-bg from-60% to-transparent px-4 pt-6 pb-[max(env(safe-area-inset-bottom),16px)]"
        >
            <button
                type="button"
                class="mx-auto flex h-14 w-full max-w-xl items-center justify-between rounded-md bg-g-accent px-5 text-base font-semibold text-white"
                @click="cartOpen = true"
            >
                <span>{{ t('guest.cart') }} · {{ count }}</span
                ><span class="num">{{ money(total, true) }} →</span>
            </button>
        </div>

        <!-- Корзина -->
        <div v-if="cartOpen" class="fixed inset-0 z-40 flex flex-col bg-g-bg">
            <div class="flex items-center border-b border-g-line px-3 py-2.5">
                <button
                    type="button"
                    class="h-11 px-2 text-[15px] font-medium"
                    @click="cartOpen = false"
                >
                    ← {{ t('nav.menu') }}
                </button>
                <span
                    class="ml-auto flex h-8 items-center rounded-sm bg-g-ink px-2.5 text-[13px] font-semibold text-g-bg"
                    >{{ t('tables.table') }} {{ table.number }}</span
                >
            </div>
            <div
                class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 overflow-y-auto px-5 py-5"
            >
                <h2 class="font-serif text-[30px] leading-8 font-semibold">
                    {{ t('guest.your_order') }}
                </h2>

                <section
                    v-if="problems.length"
                    role="alert"
                    class="flex flex-col gap-2 rounded-md border border-g-accent bg-[#F1DFD3] p-3.5"
                >
                    <span class="font-semibold text-g-accent-dark">{{
                        t('guest.menu_changed')
                    }}</span>
                    <div
                        v-for="p in problems"
                        :key="p.dish_id"
                        class="flex justify-between gap-3 text-sm"
                    >
                        <span
                            >{{ p.name }} —
                            {{
                                p.type === 'price_changed'
                                    ? t('guest.new_price')
                                    : t('guest.unavailable')
                            }}</span
                        >
                        <span v-if="p.type === 'price_changed'" class="num"
                            ><s class="text-g-muted">{{ money(p.old) }}</s> →
                            <b>{{ money(p.new) }}</b></span
                        >
                    </div>
                    <span class="text-xs text-g-muted">{{
                        t('guest.check_again')
                    }}</span>
                </section>

                <div
                    v-for="l in lines"
                    :key="l.dish.id"
                    class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 border-b border-g-line pb-3"
                >
                    <span
                        class="font-serif text-[19px] leading-[22px] font-semibold"
                        >{{ loc(l.dish, 'name') }}</span
                    >
                    <span class="text-right font-semibold num">{{
                        money(l.qty * l.price)
                    }}</span>
                    <span class="text-[13px] text-g-muted num"
                        >{{ money(l.price) }} × {{ l.qty }}</span
                    >
                    <div class="flex items-center justify-self-end">
                        <button
                            type="button"
                            class="h-9 w-11 rounded-sm border border-[#CFC3B0] text-lg"
                            :aria-label="t('guest.less')"
                            @click="dec(l.dish.id)"
                        >
                            −
                        </button>
                        <span class="w-7 text-center font-semibold num">{{
                            l.qty
                        }}</span>
                        <button
                            type="button"
                            class="h-9 w-11 rounded-sm border border-[#CFC3B0] text-lg"
                            :aria-label="t('guest.more')"
                            @click="add(l.dish)"
                        >
                            +
                        </button>
                    </div>
                </div>

                <label class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-semibold">{{
                        t('guest.comment')
                    }}</span>
                    <textarea
                        v-model="comment"
                        rows="2"
                        maxlength="250"
                        class="resize-none rounded-md border border-[#CFC3B0] bg-g-card px-3 py-2.5 text-[15px] outline-none focus:border-g-ink"
                        :placeholder="t('guest.comment_ph')"
                    />
                </label>
                <p v-if="error" class="text-sm text-g-accent-dark" role="alert">
                    {{ error }}
                </p>
            </div>
            <div
                class="mx-auto flex w-full max-w-xl flex-col gap-2 border-t border-g-line px-4 pt-3 pb-[max(env(safe-area-inset-bottom),16px)]"
            >
                <div class="flex items-baseline justify-between">
                    <span>{{ t('guest.total') }}</span
                    ><span class="text-[22px] font-semibold num">{{
                        money(total, true)
                    }}</span>
                </div>
                <button
                    type="button"
                    class="h-14 rounded-md bg-g-accent text-base font-semibold text-white disabled:opacity-50"
                    :disabled="!count || sending"
                    @click="submit"
                >
                    {{ sending ? t('guest.sending') : t('guest.send') }}
                </button>
                <span class="text-center text-xs text-g-muted">{{
                    t('guest.waiter_confirms')
                }}</span>
            </div>
        </div>
    </div>
</template>

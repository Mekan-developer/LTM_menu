<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import LangSwitch from '@/Components/LangSwitch.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    // Меню по QR-коду стола — table; доставка и самовывоз (/delivery) — delivery = { fee }.
    table: { type: Object, default: null },
    delivery: { type: Object, default: null },
    menu: { type: Array, default: () => [] },
    version: { type: String, default: '' },
    restaurant: { type: String, default: '' },
});

const { t, loc } = useI18n();

/*
 * Меню сохраняется на телефоне гостя: при слабой связи открывается сразу,
 * при появлении связи меню, цены и стоп-лист обновляются в фоне (ТЗ 2.3).
 */
const isDelivery = Boolean(props.delivery);
const menuKey = 'guest-menu';
const cartKey = isDelivery
    ? 'guest-cart:delivery'
    : `guest-cart:${props.table.token}`;
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
            isDelivery
                ? route('guest.delivery.menu.json')
                : route('guest.menu.json', props.table.token),
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

/*
 * Фото блюда может отсутствовать или ещё не сконвертироваться в очереди.
 * Битую картинку не показываем: карточка переключается на вид без фото.
 */
const brokenPhotos = ref({});
const hasPhoto = (d) => Boolean(d.photo_url) && !brokenPhotos.value[d.id];
const photoFailed = (id) => {
    brokenPhotos.value = { ...brokenPhotos.value, [id]: true };
};

// Активная вкладка категории следует за прокруткой.
const activeCat = ref(props.menu[0]?.id ?? null);
const tabs = ref(null);
let spy = null;
const scrollTo = (id) => {
    activeCat.value = id;
    document
        .getElementById(`cat-${id}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
watch(activeCat, (id) => {
    tabs.value?.querySelector(`[data-cat="${id}"]`)?.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'center',
    });
});
function observeSections() {
    spy?.disconnect();
    if (!('IntersectionObserver' in window)) return;
    spy = new IntersectionObserver(
        (entries) => {
            const visible = entries.find((e) => e.isIntersecting);
            if (visible) activeCat.value = Number(visible.target.dataset.cat);
        },
        { rootMargin: '-130px 0px -65% 0px' },
    );
    document
        .querySelectorAll('section[data-cat]')
        .forEach((s) => spy.observe(s));
}
onMounted(observeSections);
watch(liveMenu, () => requestAnimationFrame(observeSections));
onUnmounted(() => spy?.disconnect());

// Корзина и отправка
const cartOpen = ref(false);
const comment = ref('');
const sending = ref(false);
const problems = ref([]);
const error = ref('');

/*
 * Доставка и самовывоз (ТЗ 2.6): тип, контакты, адрес, способ оплаты.
 * Имя, телефон и адрес запоминаются на телефоне гостя для следующего заказа.
 */
const contactKey = 'guest-delivery-contact';
const contact = ref({
    type: 'delivery',
    name: '',
    phone: '',
    address: '',
    method: 'cash',
    ...read(contactKey, {}),
});
watch(contact, (v) => write(contactKey, v), { deep: true });
const fieldErrors = ref({});
const fee = computed(() =>
    isDelivery && contact.value.type === 'delivery' ? props.delivery.fee : 0,
);
const grandTotal = computed(() => total.value + fee.value);

// Гость убрал всё из корзины — возвращаем в меню (если нет сообщения о смене меню).
watch(count, (n) => {
    if (n === 0 && !problems.value.length) cartOpen.value = false;
});

async function submit() {
    if (!lines.value.length || sending.value) return;
    sending.value = true;
    error.value = '';
    problems.value = [];
    fieldErrors.value = {};
    const items = lines.value.map((l) => ({
        dish_id: l.dish.id,
        quantity: l.qty,
        price: l.price,
    }));
    const c = contact.value;
    try {
        const { data } = isDelivery
            ? await axios.post(route('guest.delivery.store'), {
                  ...c,
                  address: c.type === 'delivery' ? c.address : null,
                  items,
                  comment: comment.value || null,
              })
            : await axios.post(route('guest.orders.store', props.table.token), {
                  items,
                  comment: comment.value || null,
              });
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
        } else if (e.response?.status === 422) {
            // Ошибки полей формы доставки — под полями.
            fieldErrors.value = Object.fromEntries(
                Object.entries(e.response.data.errors ?? {}).map(([k, v]) => [
                    k,
                    v[0],
                ]),
            );
            error.value = e.response.data.message;
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
        <header class="sticky top-0 z-20 bg-g-bg/95 backdrop-blur-md">
            <div
                class="mx-auto flex max-w-xl items-center gap-2.5 px-5 pt-4 pb-3"
            >
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
                    >{{
                        isDelivery
                            ? t('guest.type_delivery')
                            : `${t('tables.table')} ${table.number}`
                    }}</span
                >
                <LangSwitch />
            </div>
            <nav
                ref="tabs"
                class="border-b border-g-line"
                :aria-label="t('nav.menu')"
            >
                <div
                    class="no-scrollbar mx-auto flex max-w-xl gap-6 overflow-x-auto px-5"
                >
                    <button
                        v-for="c in liveMenu"
                        :key="c.id"
                        type="button"
                        :data-cat="c.id"
                        class="-mb-px h-11 shrink-0 border-b-2 text-[15px] transition-colors"
                        :class="
                            activeCat === c.id
                                ? 'border-g-accent font-semibold text-g-ink'
                                : 'border-transparent text-g-muted'
                        "
                        :aria-current="activeCat === c.id ? 'true' : undefined"
                        @click="scrollTo(c.id)"
                    >
                        {{ loc(c, 'name') }}
                    </button>
                </div>
            </nav>
        </header>

        <main class="mx-auto flex max-w-xl flex-col gap-10 px-4 pt-6 pb-32">
            <section
                v-for="c in liveMenu"
                :id="`cat-${c.id}`"
                :key="c.id"
                :data-cat="c.id"
                class="flex scroll-mt-32 flex-col gap-4"
            >
                <h2 class="px-1 font-serif text-[32px] leading-9 font-semibold">
                    {{ loc(c, 'name') }}
                </h2>

                <!--
                    Карточка блюда. С фото — фото сверху и широкая кнопка.
                    Без фото — компактная текстовая карточка: цена акцентным
                    цветом внизу слева, кнопка справа.
                -->
                <article
                    v-for="d in c.dishes"
                    :key="d.id"
                    class="dish-card overflow-hidden rounded-2xl bg-g-card"
                >
                    <div
                        v-if="hasPhoto(d)"
                        class="relative aspect-[4/3] bg-g-photo"
                    >
                        <!-- Виден, пока фото грузится на слабой связи. -->
                        <svg
                            class="absolute inset-0 m-auto size-10 text-g-accent/40"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.2"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="M10 3.5c.6 4.2 2.3 5.9 6.5 6.5-4.2.6-5.9 2.3-6.5 6.5-.6-4.2-2.3-5.9-6.5-6.5 4.2-.6 5.9-2.3 6.5-6.5Z"
                            />
                            <path
                                d="M18 14.5c.3 1.9 1.1 2.7 3 3-1.9.3-2.7 1.1-3 3-.3-1.9-1.1-2.7-3-3 1.9-.3 2.7-1.1 3-3Z"
                            />
                        </svg>
                        <img
                            :src="d.photo_url"
                            :alt="loc(d, 'name')"
                            loading="lazy"
                            decoding="async"
                            class="relative h-full w-full object-cover"
                            @error="photoFailed(d.id)"
                        />
                    </div>

                    <div
                        class="flex flex-col"
                        :class="
                            hasPhoto(d) ? 'gap-2 p-4' : 'gap-2 px-5 pt-5 pb-4'
                        "
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <h3
                                class="font-serif text-[22px] leading-[26px] font-semibold"
                            >
                                {{ loc(d, 'name') }}
                            </h3>
                            <span
                                v-if="hasPhoto(d)"
                                class="flex flex-col items-end text-[17px] font-bold whitespace-nowrap num"
                                ><span
                                    :class="d.old_price ? 'text-g-accent' : ''"
                                    >{{ money(d.price, true) }}</span
                                ><s
                                    v-if="d.old_price"
                                    class="text-[13px] font-normal text-g-muted"
                                    >{{ money(d.old_price, true) }}</s
                                ></span
                            >
                        </div>
                        <p
                            v-if="loc(d, 'description') || d.weight"
                            class="text-[15px] leading-5 text-g-muted"
                        >
                            {{
                                [loc(d, 'description'), d.weight]
                                    .filter(Boolean)
                                    .join('. ')
                            }}
                        </p>

                        <div
                            class="mt-1.5 flex items-center"
                            :class="hasPhoto(d) ? '' : 'justify-between gap-3'"
                        >
                            <span
                                v-if="!hasPhoto(d)"
                                class="flex items-baseline gap-2 text-[20px] font-bold whitespace-nowrap text-g-accent num"
                                ><span>{{ money(d.price, true) }}</span
                                ><s
                                    v-if="d.old_price"
                                    class="text-[14px] font-normal text-g-muted"
                                    >{{ money(d.old_price, true) }}</s
                                ></span
                            >

                            <div
                                v-if="cart[d.id]"
                                class="flex h-12 items-center rounded-xl bg-g-ink p-1 text-g-bg"
                                :class="
                                    hasPhoto(d) ? 'w-full justify-between' : ''
                                "
                            >
                                <button
                                    type="button"
                                    class="flex h-10 w-11 items-center justify-center rounded-lg bg-white/10 text-xl active:bg-white/20"
                                    :aria-label="t('guest.less')"
                                    @click="dec(d.id)"
                                >
                                    −
                                </button>
                                <span
                                    class="text-center font-semibold num"
                                    :class="hasPhoto(d) ? 'text-[15px]' : 'w-9'"
                                    aria-live="polite"
                                >
                                    {{ cart[d.id].qty
                                    }}<template v-if="hasPhoto(d)">
                                        · {{ t('guest.in_cart') }}</template
                                    >
                                </span>
                                <button
                                    type="button"
                                    class="flex h-10 w-11 items-center justify-center rounded-lg bg-white/10 text-xl active:bg-white/20"
                                    :aria-label="t('guest.more')"
                                    @click="add(d)"
                                >
                                    +
                                </button>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="h-12 rounded-xl bg-g-ink text-base font-semibold text-g-bg transition active:scale-[0.98]"
                                :class="hasPhoto(d) ? 'w-full' : 'px-5'"
                                @click="add(d)"
                            >
                                + {{ t('guest.add') }}
                            </button>
                        </div>
                    </div>
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
                class="mx-auto flex h-14 w-full max-w-xl items-center justify-between rounded-xl bg-g-accent px-5 text-base font-semibold text-white shadow-[0_10px_24px_-10px_rgba(126,53,25,0.6)]"
                @click="cartOpen = true"
            >
                <span>{{ t('guest.cart') }} · {{ count }}</span
                ><span class="num">{{ money(total, true) }} →</span>
            </button>
        </div>

        <!-- Корзина -->
        <div v-if="cartOpen" class="fixed inset-0 z-40 flex flex-col bg-g-bg">
            <header class="border-b border-g-line bg-g-bg/95 backdrop-blur-md">
                <div
                    class="mx-auto flex max-w-xl items-center justify-between gap-3 px-4 py-3"
                >
                    <button
                        type="button"
                        class="flex h-10 items-center gap-1.5 rounded-xl border border-g-line bg-g-card pr-4 pl-3 text-[15px] font-medium shadow-[0_1px_2px_rgb(33_28_23/0.05)] transition active:scale-[0.97]"
                        @click="cartOpen = false"
                    >
                        <svg
                            class="size-[18px]"
                            viewBox="0 0 18 18"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M15 9H3M8 4 3 9l5 5" />
                        </svg>
                        {{ t('nav.menu') }}
                    </button>
                    <span
                        class="flex h-8 items-center rounded-lg bg-g-ink px-3 text-[13px] font-semibold text-g-bg"
                        >{{
                            isDelivery
                                ? t('guest.type_delivery')
                                : `${t('tables.table')} ${table.number}`
                        }}</span
                    >
                </div>
            </header>

            <div class="flex-1 overflow-y-auto">
                <div
                    class="mx-auto flex max-w-xl flex-col gap-4 px-4 pt-6 pb-6"
                >
                    <h2
                        class="px-1 font-serif text-[32px] leading-9 font-semibold"
                    >
                        {{ t('guest.your_order') }}
                    </h2>

                    <section
                        v-if="problems.length"
                        role="alert"
                        class="flex flex-col gap-2 rounded-2xl border border-g-accent/50 bg-[#F1DFD3] p-4"
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
                                ><s class="text-g-muted">{{ money(p.old) }}</s>
                                → <b>{{ money(p.new) }}</b></span
                            >
                        </div>
                        <span class="text-xs text-g-muted">{{
                            t('guest.check_again')
                        }}</span>
                    </section>

                    <!-- Позиция корзины: та же карточка, что и в меню. -->
                    <article
                        v-for="l in lines"
                        :key="l.dish.id"
                        class="dish-card flex flex-col gap-3 rounded-2xl bg-g-card p-4 pl-5"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                v-if="hasPhoto(l.dish)"
                                :src="l.dish.photo_url"
                                alt=""
                                class="size-14 shrink-0 rounded-xl bg-g-photo object-cover"
                                @error="photoFailed(l.dish.id)"
                            />
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <h3
                                    class="font-serif text-[22px] leading-[26px] font-semibold"
                                >
                                    {{ loc(l.dish, 'name') }}
                                </h3>
                                <span class="text-[15px] text-g-muted num">{{
                                    money(l.price, true)
                                }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <div class="flex flex-col">
                                <span
                                    class="text-[20px] leading-6 font-bold whitespace-nowrap text-g-accent num"
                                    >{{ money(l.qty * l.price, true) }}</span
                                >
                                <span class="text-[13px] text-g-muted num"
                                    >{{ money(l.price) }} × {{ l.qty }}</span
                                >
                            </div>
                            <div
                                class="flex h-12 items-center rounded-xl bg-g-ink p-1 text-g-bg"
                            >
                                <button
                                    type="button"
                                    class="flex h-10 w-11 items-center justify-center rounded-lg bg-white/10 text-xl active:bg-white/20"
                                    :aria-label="t('guest.less')"
                                    @click="dec(l.dish.id)"
                                >
                                    −
                                </button>
                                <span
                                    class="w-9 text-center font-semibold num"
                                    aria-live="polite"
                                    >{{ l.qty }}</span
                                >
                                <button
                                    type="button"
                                    class="flex h-10 w-11 items-center justify-center rounded-lg bg-white/10 text-xl active:bg-white/20"
                                    :aria-label="t('guest.more')"
                                    @click="add(l.dish)"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </article>

                    <label class="mt-2 flex flex-col gap-2">
                        <span class="px-1 text-[15px] font-semibold">{{
                            t('guest.comment')
                        }}</span>
                        <textarea
                            v-model="comment"
                            rows="3"
                            maxlength="250"
                            class="dish-card resize-none rounded-2xl bg-g-card px-4 py-3.5 text-base leading-6 outline-none placeholder:text-g-muted/70 focus:outline-2 focus:outline-offset-0 focus:outline-g-accent"
                            :placeholder="t('guest.comment_ph')"
                        />
                    </label>

                    <!-- Доставка или самовывоз: контакты и оплата -->
                    <section
                        v-if="isDelivery"
                        class="dish-card mt-2 flex flex-col gap-4 rounded-2xl bg-g-card p-4"
                    >
                        <div
                            role="radiogroup"
                            class="grid grid-cols-2 gap-1 rounded-xl bg-g-bg p-1"
                        >
                            <button
                                v-for="tp in ['delivery', 'pickup']"
                                :key="tp"
                                type="button"
                                role="radio"
                                :aria-checked="contact.type === tp"
                                class="h-11 rounded-lg text-[15px] font-semibold transition"
                                :class="
                                    contact.type === tp
                                        ? 'bg-g-ink text-g-bg'
                                        : 'text-g-muted'
                                "
                                @click="contact.type = tp"
                            >
                                {{ t(`guest.type_${tp}`) }}
                            </button>
                        </div>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[15px] font-semibold">{{
                                t('guest.your_name')
                            }}</span>
                            <input
                                v-model="contact.name"
                                autocomplete="name"
                                maxlength="120"
                                class="guest-input"
                                :placeholder="t('guest.name_ph')"
                                :aria-invalid="Boolean(fieldErrors.name)"
                            />
                            <span
                                v-if="fieldErrors.name"
                                class="text-sm text-g-accent-dark"
                                >{{ fieldErrors.name }}</span
                            >
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[15px] font-semibold">{{
                                t('guest.phone')
                            }}</span>
                            <input
                                v-model="contact.phone"
                                type="tel"
                                autocomplete="tel"
                                inputmode="tel"
                                placeholder="+993 6X XXXXXX"
                                class="guest-input num"
                                maxlength="32"
                                :aria-invalid="Boolean(fieldErrors.phone)"
                            />
                            <span
                                v-if="fieldErrors.phone"
                                class="text-sm text-g-accent-dark"
                                >{{ fieldErrors.phone }}</span
                            >
                        </label>
                        <label
                            v-if="contact.type === 'delivery'"
                            class="flex flex-col gap-1.5"
                        >
                            <span class="text-[15px] font-semibold">{{
                                t('guest.address')
                            }}</span>
                            <textarea
                                v-model="contact.address"
                                rows="2"
                                maxlength="500"
                                autocomplete="street-address"
                                :placeholder="t('guest.address_ph')"
                                class="guest-input resize-none py-3 leading-6"
                                :aria-invalid="Boolean(fieldErrors.address)"
                            />
                            <span
                                v-if="fieldErrors.address"
                                class="text-sm text-g-accent-dark"
                                >{{ fieldErrors.address }}</span
                            >
                        </label>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[15px] font-semibold">{{
                                t('guest.pay_method')
                            }}</span>
                            <div
                                role="radiogroup"
                                class="grid grid-cols-2 gap-2"
                            >
                                <button
                                    v-for="m in ['cash', 'card']"
                                    :key="m"
                                    type="button"
                                    role="radio"
                                    :aria-checked="contact.method === m"
                                    class="h-12 rounded-xl text-[15px] font-medium transition"
                                    :class="
                                        contact.method === m
                                            ? 'border-2 border-g-accent bg-g-bg text-g-ink'
                                            : 'border border-g-line text-g-muted'
                                    "
                                    @click="contact.method = m"
                                >
                                    {{
                                        m === 'cash'
                                            ? t('guest.pay_cash')
                                            : contact.type === 'delivery'
                                              ? t('guest.pay_card_courier')
                                              : t('guest.pay_card')
                                    }}
                                </button>
                            </div>
                        </div>
                    </section>
                    <p
                        v-if="error"
                        class="px-1 text-sm text-g-accent-dark"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                </div>
            </div>

            <footer
                class="border-t border-g-line bg-g-card shadow-[0_-10px_24px_-18px_rgb(33_28_23/0.35)]"
            >
                <div
                    class="mx-auto flex max-w-xl flex-col gap-3 px-4 pt-4 pb-[max(env(safe-area-inset-bottom),16px)]"
                >
                    <div
                        v-if="fee"
                        class="flex flex-col gap-0.5 px-1 text-[15px] text-g-muted"
                    >
                        <span class="flex justify-between"
                            ><span>{{ t('guest.items_sum') }}</span
                            ><span class="num">{{
                                money(total, true)
                            }}</span></span
                        >
                        <span class="flex justify-between"
                            ><span>{{ t('guest.fee') }}</span
                            ><span class="num">{{
                                money(fee, true)
                            }}</span></span
                        >
                    </div>
                    <div class="flex items-baseline justify-between px-1">
                        <span class="text-[17px]">{{ t('guest.total') }}</span
                        ><span class="text-[28px] leading-8 font-bold num">{{
                            money(grandTotal, true)
                        }}</span>
                    </div>
                    <button
                        type="button"
                        class="h-14 rounded-xl bg-g-accent text-[17px] font-semibold text-white shadow-[0_10px_24px_-10px_rgba(126,53,25,0.6)] transition active:scale-[0.98] active:bg-g-accent-dark disabled:opacity-50 disabled:shadow-none"
                        :disabled="!count || sending"
                        @click="submit"
                    >
                        {{
                            sending
                                ? t('guest.sending')
                                : isDelivery
                                  ? t('guest.send_delivery')
                                  : t('guest.send')
                        }}
                    </button>
                    <span class="text-center text-[13px] text-g-muted">{{
                        isDelivery
                            ? t('guest.admin_confirms')
                            : t('guest.waiter_confirms')
                    }}</span>
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.dish-card {
    box-shadow:
        0 0 0 1px var(--color-g-line),
        0 1px 2px rgb(33 28 23 / 0.05),
        0 10px 24px -14px rgb(33 28 23 / 0.28);
}

.no-scrollbar {
    scrollbar-width: none;
}

.guest-input {
    min-height: 3rem;
    border-radius: 0.75rem;
    border: 1px solid var(--color-g-line);
    background: var(--color-g-bg);
    padding: 0 1rem;
    font-size: 16px; /* 16px — iOS не увеличивает страницу при фокусе */
    outline: none;
}

.guest-input::placeholder {
    color: color-mix(in srgb, var(--color-g-muted) 70%, transparent);
}

.guest-input:hover {
    border-color: var(--color-g-muted);
}

.guest-input:focus {
    outline: 2px solid var(--color-g-accent);
    outline-offset: 0;
}

.guest-input[aria-invalid='true'] {
    border-color: var(--color-g-accent-dark);
    box-shadow: 0 0 0 1px var(--color-g-accent-dark);
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>

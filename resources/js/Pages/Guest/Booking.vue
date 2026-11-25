<script setup>
// Бронь на странице для гостей (ТЗ 2.2): без регистрации. Стол назначит администратор,
// предзаказ уйдёт на кухню, когда гость придёт.
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import LangSwitch from '@/Components/LangSwitch.vue';
import { useI18n } from '@/i18n';
import { date as fmtDate, money, time as fmtTime } from '@/utils/format';

const props = defineProps({
    restaurant: { type: String, default: '' },
    menu: { type: Array, default: () => [] },
    done: { type: Object, default: null },
});
const { t, loc } = useI18n();

const iso = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const today = iso(new Date());

// Время с шагом 30 минут, 10:00–23:00.
const times = [];
for (let h = 10; h <= 23; h++)
    for (const m of ['00', '30'])
        times.push(`${String(h).padStart(2, '0')}:${m}`);

const form = useForm({
    date: today,
    time: '19:00',
    guests_count: 2,
    name: '',
    phone: '',
    comment: '',
    items: [],
});

const showMenu = ref(false);
const qty = (id) => form.items.find((i) => i.dish_id === id)?.quantity ?? 0;
function add(id) {
    const row = form.items.find((i) => i.dish_id === id);
    if (row) row.quantity++;
    else form.items.push({ dish_id: id, quantity: 1 });
}
function dec(id) {
    const idx = form.items.findIndex((i) => i.dish_id === id);
    if (idx < 0) return;
    if (form.items[idx].quantity > 1) form.items[idx].quantity--;
    else form.items.splice(idx, 1);
}
const dishes = computed(() => props.menu.flatMap((c) => c.dishes));
const total = computed(() =>
    form.items.reduce(
        (s, i) =>
            s +
            (dishes.value.find((d) => d.id === i.dish_id)?.price ?? 0) *
                i.quantity,
        0,
    ),
);

const submit = () =>
    form.post(route('guest.booking.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <Head :title="t('book.title')" />
    <div class="min-h-[100dvh] bg-g-bg font-guest text-g-ink">
        <header class="border-b border-g-line">
            <div
                class="mx-auto flex max-w-xl items-center gap-2.5 px-5 pt-4 pb-3"
            >
                <span class="flex-1 font-serif text-[22px] leading-6 italic">{{
                    restaurant
                }}</span>
                <LangSwitch />
            </div>
        </header>

        <main class="mx-auto flex max-w-xl flex-col gap-5 px-4 pt-8 pb-12">
            <!-- Бронь принята -->
            <div
                v-if="done"
                class="flex flex-col items-center gap-4 px-1 pt-6 text-center"
            >
                <div
                    class="status-badge flex size-[76px] items-center justify-center rounded-full text-white"
                >
                    <svg
                        class="size-9"
                        viewBox="0 0 34 34"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M8 17.5 14 23.5 26 11" />
                    </svg>
                </div>
                <h1 class="font-serif text-[34px] leading-10 font-semibold">
                    {{ t('book.done_title') }}
                </h1>
                <p class="text-[17px]">
                    {{ fmtDate(done.starts_at) }} ·
                    {{ fmtTime(done.starts_at) }} ·
                    {{ t('book.guests_n', { n: done.guests_count }) }}
                </p>
                <p class="text-[15px] text-g-muted">
                    {{ t('book.done_text') }}
                </p>
            </div>

            <form v-else class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="flex flex-col gap-2 px-1 text-center">
                    <h1 class="font-serif text-[34px] leading-10 font-semibold">
                        {{ t('book.title') }}
                    </h1>
                    <p class="text-[17px] text-g-muted">
                        {{ t('book.subtitle') }}
                    </p>
                </div>

                <section
                    class="card flex flex-col gap-4 rounded-2xl bg-g-card p-5"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[15px] font-semibold">{{
                                t('book.date')
                            }}</span>
                            <input
                                v-model="form.date"
                                type="date"
                                :min="today"
                                class="field"
                            />
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[15px] font-semibold">{{
                                t('book.time')
                            }}</span>
                            <select v-model="form.time" class="field">
                                <option
                                    v-for="tm in times"
                                    :key="tm"
                                    :value="tm"
                                >
                                    {{ tm }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <span
                        v-if="form.errors.time || form.errors.date"
                        class="text-sm text-g-accent-dark"
                        >{{ form.errors.time || form.errors.date }}</span
                    >

                    <div class="flex items-center justify-between">
                        <span class="text-[15px] font-semibold">{{
                            t('book.guests')
                        }}</span>
                        <div
                            class="flex h-12 items-center rounded-xl bg-g-ink p-1 text-g-bg"
                        >
                            <button
                                type="button"
                                class="step"
                                :aria-label="t('guest.less')"
                                @click="
                                    form.guests_count > 1 && form.guests_count--
                                "
                            >
                                −
                            </button>
                            <span
                                class="w-10 text-center font-semibold num"
                                aria-live="polite"
                                >{{ form.guests_count }}</span
                            >
                            <button
                                type="button"
                                class="step"
                                :aria-label="t('guest.more')"
                                @click="
                                    form.guests_count < 30 &&
                                    form.guests_count++
                                "
                            >
                                +
                            </button>
                        </div>
                    </div>
                </section>

                <section
                    class="card flex flex-col gap-3 rounded-2xl bg-g-card p-5"
                >
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[15px] font-semibold">{{
                            t('book.name')
                        }}</span>
                        <input
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            class="field"
                            :placeholder="t('book.name_ph')"
                            maxlength="120"
                            :aria-invalid="Boolean(form.errors.name)"
                        />
                        <span
                            v-if="form.errors.name"
                            class="text-sm text-g-accent-dark"
                            >{{ form.errors.name }}</span
                        >
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[15px] font-semibold">{{
                            t('book.phone')
                        }}</span>
                        <input
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            inputmode="tel"
                            placeholder="+993 6X XXXXXX"
                            class="field font-mono"
                            maxlength="32"
                            :aria-invalid="
                                Boolean(
                                    form.errors.phone ||
                                    form.errors.guest_phone,
                                )
                            "
                        />
                        <span
                            v-if="form.errors.phone || form.errors.guest_phone"
                            class="text-sm text-g-accent-dark"
                            >{{
                                form.errors.phone || form.errors.guest_phone
                            }}</span
                        >
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[15px] font-semibold">{{
                            t('book.comment')
                        }}</span>
                        <textarea
                            v-model="form.comment"
                            rows="2"
                            maxlength="500"
                            class="field resize-none py-3"
                            :placeholder="t('book.comment_ph')"
                        />
                    </label>
                </section>

                <!-- Предзаказ из меню — по желанию -->
                <section class="card flex flex-col rounded-2xl bg-g-card">
                    <button
                        type="button"
                        class="flex items-center justify-between px-5 py-4 text-left"
                        :aria-expanded="showMenu"
                        @click="showMenu = !showMenu"
                    >
                        <span class="flex flex-col">
                            <span class="text-[17px] font-semibold">{{
                                t('book.preorder')
                            }}</span>
                            <span class="text-[13px] text-g-muted">{{
                                t('book.preorder_hint')
                            }}</span>
                        </span>
                        <span class="font-mono text-g-accent">{{
                            form.items.length
                                ? money(total, true)
                                : showMenu
                                  ? '−'
                                  : '+'
                        }}</span>
                    </button>
                    <div
                        v-if="showMenu"
                        class="flex flex-col border-t border-g-line px-5 pb-3"
                    >
                        <template v-for="c in menu" :key="c.id">
                            <span
                                class="pt-4 pb-1 font-serif text-lg font-semibold"
                                >{{ loc(c, 'name') }}</span
                            >
                            <div
                                v-for="d in c.dishes"
                                :key="d.id"
                                class="flex items-center justify-between gap-3 border-t border-g-line/60 py-2.5 first-of-type:border-t-0"
                            >
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate text-[15px]">{{
                                        loc(d, 'name')
                                    }}</span>
                                    <span
                                        class="font-mono text-[13px] text-g-muted"
                                        >{{ money(d.price, true)
                                        }}<s
                                            v-if="d.old_price"
                                            class="ml-1.5"
                                            >{{ money(d.old_price, true) }}</s
                                        ></span
                                    >
                                </span>
                                <span
                                    v-if="qty(d.id)"
                                    class="flex items-center gap-1"
                                >
                                    <button
                                        type="button"
                                        class="mini"
                                        :aria-label="t('guest.less')"
                                        @click="dec(d.id)"
                                    >
                                        −
                                    </button>
                                    <span class="w-6 text-center font-mono">{{
                                        qty(d.id)
                                    }}</span>
                                    <button
                                        type="button"
                                        class="mini"
                                        :aria-label="t('guest.more')"
                                        @click="add(d.id)"
                                    >
                                        +
                                    </button>
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    class="mini px-3"
                                    @click="add(d.id)"
                                >
                                    + {{ t('guest.add') }}
                                </button>
                            </div>
                        </template>
                    </div>
                    <span
                        v-if="form.errors.items"
                        class="px-5 pb-3 text-sm text-g-accent-dark"
                        >{{ form.errors.items }}</span
                    >
                </section>

                <button
                    type="submit"
                    class="h-14 rounded-xl bg-g-accent text-[17px] font-semibold text-white shadow-[0_10px_24px_-10px_rgba(126,53,25,0.6)] transition active:scale-[0.98] disabled:opacity-50"
                    :disabled="form.processing || !form.name || !form.phone"
                >
                    {{ form.processing ? t('guest.sending') : t('book.send') }}
                </button>
                <p class="text-center text-[13px] text-g-muted">
                    {{ t('book.admin_confirms') }}
                </p>
            </form>
        </main>
    </div>
</template>

<style scoped>
.card {
    box-shadow:
        0 0 0 1px var(--color-g-line),
        0 1px 2px rgb(33 28 23 / 0.05),
        0 10px 24px -14px rgb(33 28 23 / 0.28);
}
.field {
    height: 3rem;
    border-radius: 0.75rem;
    border: 1px solid var(--color-g-line);
    background: var(--color-g-bg);
    padding: 0 0.875rem;
    font-size: 1rem;
    outline: none;
}
/* Многострочному полю фиксированная высота обрезала текст. */
textarea.field {
    height: auto;
    min-height: 4.75rem;
    line-height: 1.5rem;
}
.field::placeholder {
    color: color-mix(in srgb, var(--color-g-muted) 70%, transparent);
}
.field:hover {
    border-color: var(--color-g-muted);
}
.field:focus {
    border-color: var(--color-g-accent);
    box-shadow: 0 0 0 1px var(--color-g-accent);
}
.field[aria-invalid='true'] {
    border-color: var(--color-g-accent-dark);
    box-shadow: 0 0 0 1px var(--color-g-accent-dark);
}
.step {
    display: flex;
    height: 2.5rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: rgb(255 255 255 / 0.1);
    font-size: 1.25rem;
}
.mini {
    display: inline-flex;
    height: 2.25rem;
    min-width: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.625rem;
    background: var(--color-g-ink);
    color: var(--color-g-bg);
    font-size: 15px;
    font-weight: 600;
}
.status-badge {
    background: linear-gradient(
        135deg,
        var(--color-ok) 0%,
        var(--color-g-accent) 100%
    );
}
</style>

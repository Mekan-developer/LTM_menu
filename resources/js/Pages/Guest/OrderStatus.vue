<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import LangSwitch from '@/Components/LangSwitch.vue';
import { useI18n } from '@/i18n';
import { money } from '@/utils/format';

const props = defineProps({
    token: { type: String, required: true },
    initial: { type: Object, required: true },
    restaurant: { type: String, default: '' },
});

const { t, loc } = useI18n();
const state = ref(props.initial);
let timer = null;

async function poll() {
    try {
        state.value = (
            await axios.get(route('guest.order.status', props.token))
        ).data;
    } catch {
        /* нет связи */
    }
}

onMounted(() => {
    timer = setInterval(poll, 5000);
});
onUnmounted(() => clearInterval(timer));

const steps = ['pending', 'sent', 'ready', 'served'];
const stage = computed(() => {
    const st = state.value.items
        .map((i) => steps.indexOf(i.status))
        .filter((i) => i >= 0);
    return st.length ? Math.min(...st) : 0;
});
const cancelled = computed(() => state.value.status === 'cancelled');

// Когда всё подано, последний этап тоже считается пройденным (галочка, а не часы).
const finished = computed(() => steps[stage.value] === 'served');
const passed = (i) => i < stage.value || finished.value;
const current = (i) => i === stage.value && !finished.value;

/*
 * Одинаковые блюда с одинаковым статусом (из разных дозаказов)
 * показываем одной строкой: «4 × Palaw», а не «2 × Palaw» дважды.
 */
const rows = computed(() => {
    const map = new Map();
    for (const i of state.value.items) {
        const key = `${i.name_tm}|${i.status}`;
        const row = map.get(key);
        if (row) row.quantity += i.quantity;
        else map.set(key, { ...i });
    }
    return [...map.values()];
});

const statusClass = (s) =>
    ({
        pending: 'text-g-accent/75',
        sent: 'text-g-accent',
        ready: 'font-semibold text-ok',
        served: 'text-g-muted',
    })[s] ?? 'text-g-muted';

const back = () => window.history.back();
</script>

<template>
    <Head :title="t('guest.your_order')" />
    <div class="min-h-[100dvh] bg-g-bg font-guest text-g-ink">
        <header class="border-b border-g-line">
            <div
                class="mx-auto flex max-w-xl items-center gap-2.5 px-5 pt-4 pb-3"
            >
                <span class="flex-1 font-serif text-[22px] leading-6 italic">{{
                    restaurant
                }}</span>
                <span
                    class="flex h-8 items-center rounded-lg bg-g-ink px-3 text-[13px] font-semibold text-g-bg"
                    >{{ t('tables.table') }} {{ state.table }}</span
                >
                <LangSwitch />
            </div>
        </header>

        <main class="mx-auto flex max-w-xl flex-col gap-5 px-4 pt-8 pb-12">
            <!-- Итог отправки: галочка и текущий этап -->
            <div class="flex flex-col items-center gap-4 px-1 text-center">
                <div
                    class="flex size-[76px] items-center justify-center rounded-full text-white"
                    :class="
                        cancelled
                            ? 'bg-g-muted'
                            : 'status-badge shadow-[0_12px_28px_-12px_rgba(126,53,25,0.65)]'
                    "
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
                        <path v-if="cancelled" d="M10 10l14 14M24 10 10 24" />
                        <path v-else d="M8 17.5 14 23.5 26 11" />
                    </svg>
                </div>
                <h1
                    class="font-serif text-[34px] leading-10 font-semibold"
                    aria-live="polite"
                >
                    {{
                        cancelled
                            ? t('guest.stage_cancelled')
                            : t(`guest.stage_${steps[stage]}`)
                    }}
                </h1>
            </div>

            <!-- Таймлайн этапов -->
            <ol
                v-if="!cancelled"
                class="dish-card flex flex-col rounded-2xl bg-g-card px-5 py-4"
            >
                <li
                    v-for="(s, i) in steps"
                    :key="s"
                    class="relative flex items-center gap-4 py-2.5"
                    :aria-current="current(i) ? 'step' : undefined"
                >
                    <!-- Линия к следующему этапу -->
                    <span
                        v-if="i < steps.length - 1"
                        class="absolute top-[calc(50%+14px)] left-[13px] h-[calc(100%-28px)] w-0.5 rounded-full"
                        :class="passed(i) ? 'bg-g-accent' : 'bg-g-line'"
                        aria-hidden="true"
                    />

                    <span
                        class="relative flex size-7 shrink-0 items-center justify-center rounded-full"
                        :class="
                            passed(i)
                                ? 'bg-g-accent text-white'
                                : current(i)
                                  ? 'bg-g-accent text-white ring-[5px] ring-g-accent/15'
                                  : 'border-2 border-g-line bg-g-card'
                        "
                        aria-hidden="true"
                    >
                        <!-- пройден -->
                        <svg
                            v-if="passed(i)"
                            class="size-3.5"
                            viewBox="0 0 14 14"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M3 7.5 5.8 10 11 4.5" />
                        </svg>
                        <!-- текущий: часы -->
                        <svg
                            v-else-if="current(i)"
                            class="size-4"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <circle cx="8" cy="8" r="5.6" />
                            <path d="M8 5v3.2l2 1.3" />
                        </svg>
                    </span>

                    <span
                        class="text-[17px]"
                        :class="
                            current(i)
                                ? 'font-semibold text-g-accent'
                                : passed(i)
                                  ? 'text-g-ink'
                                  : 'text-g-muted/60'
                        "
                        >{{ t(`guest.step_${s}`) }}</span
                    >
                </li>
            </ol>

            <!-- Заказанные блюда -->
            <section
                v-if="rows.length"
                class="dish-card flex flex-col rounded-2xl bg-g-card px-5 py-2"
            >
                <ul>
                    <li
                        v-for="r in rows"
                        :key="r.id"
                        class="flex items-baseline justify-between gap-4 border-t border-g-line/70 py-3 first:border-t-0"
                    >
                        <span class="text-[17px] leading-6">
                            <span class="font-semibold num"
                                >{{ r.quantity }} ×</span
                            >
                            {{ loc(r, 'name') }}
                        </span>
                        <span
                            class="shrink-0 text-[15px] whitespace-nowrap"
                            :class="statusClass(r.status)"
                            >{{ t(`item_status.${r.status}`) }}</span
                        >
                    </li>
                </ul>
                <div
                    v-if="state.total"
                    class="-mx-5 mt-1 flex items-baseline justify-between border-t border-g-line px-5 pt-3.5 pb-2"
                >
                    <span class="text-[17px]">{{ t('guest.total') }}</span
                    ><span class="text-[22px] font-bold num">{{
                        money(state.total, true)
                    }}</span>
                </div>
            </section>

            <!-- После оплаты — приглашение оценить (ТЗ 2.9) -->
            <a
                v-if="state.review_url"
                :href="state.review_url"
                class="dish-card flex items-center gap-4 rounded-2xl bg-g-card px-5 py-4 transition active:scale-[0.99]"
            >
                <span class="flex flex-1 flex-col gap-0.5">
                    <span class="text-[17px] font-semibold">{{
                        t('review.invite')
                    }}</span>
                    <span class="text-[15px] text-g-muted">{{
                        t('review.invite_text')
                    }}</span>
                </span>
                <span class="flex gap-0.5 text-g-accent" aria-hidden="true">
                    <svg
                        v-for="n in 5"
                        :key="n"
                        class="size-5"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path
                            d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"
                        />
                    </svg>
                </span>
            </a>
            <p
                v-else-if="state.reviewed"
                class="px-1 text-center text-[15px] text-g-muted"
            >
                {{ t('review.thanks') }}
            </p>

            <button
                type="button"
                class="mt-1 h-14 rounded-xl border-[1.5px] border-g-ink bg-g-card text-[17px] font-semibold shadow-[0_1px_2px_rgb(33_28_23/0.06)] transition active:scale-[0.98] active:bg-g-bg"
                @click="back"
            >
                {{ t('guest.order_more') }}
            </button>
        </main>
    </div>
</template>

<style scoped>
.dish-card {
    box-shadow:
        0 0 0 1px var(--color-g-line),
        0 1px 2px rgb(33 28 23 / 0.05),
        0 10px 24px -14px rgb(33 28 23 / 0.28);
}

.status-badge {
    background: linear-gradient(
        135deg,
        var(--color-ok) 0%,
        var(--color-g-accent) 100%
    );
}
</style>

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
const back = () => window.history.back();
</script>

<template>
    <Head :title="t('guest.your_order')" />
    <div class="min-h-[100dvh] bg-g-bg font-guest text-g-ink">
        <header class="flex items-center gap-2.5 px-5 pt-4 pb-3">
            <span class="flex-1 font-serif text-[22px] italic">{{
                restaurant
            }}</span>
            <span
                class="flex h-8 items-center rounded-sm bg-g-ink px-2.5 text-[13px] font-semibold text-g-bg"
                >{{ t('tables.table') }} {{ state.table }}</span
            >
            <LangSwitch />
        </header>
        <main class="mx-auto flex max-w-xl flex-col gap-6 px-6 pt-10 pb-16">
            <div
                class="flex size-16 items-center justify-center rounded-lg bg-g-ink"
            >
                <svg
                    width="34"
                    height="34"
                    viewBox="0 0 34 34"
                    fill="none"
                    stroke="#F6F1E8"
                    stroke-width="3"
                    aria-hidden="true"
                >
                    <path d="M8 17.5 14 23.5 26 11" />
                </svg>
            </div>
            <h1 class="font-serif text-[34px] leading-10 font-semibold">
                {{
                    state.status === 'cancelled'
                        ? t('guest.stage_cancelled')
                        : t(`guest.stage_${steps[stage]}`)
                }}
            </h1>

            <ol class="flex flex-col">
                <li
                    v-for="(s, i) in steps"
                    :key="s"
                    class="flex h-12 items-center gap-3.5"
                    :class="i > stage ? 'text-[#9A8F82]' : ''"
                >
                    <span
                        class="size-3.5 rounded-full"
                        :class="
                            i < stage
                                ? 'bg-g-ink'
                                : i === stage
                                  ? 'bg-g-accent shadow-[0_0_0_5px_rgba(156,68,38,.2)]'
                                  : 'border-2 border-[#CFC3B0]'
                        "
                    />
                    <span
                        class="text-base"
                        :class="
                            i === stage ? 'font-semibold text-g-accent' : ''
                        "
                        >{{ t(`guest.step_${s}`) }}</span
                    >
                </li>
            </ol>

            <div class="flex flex-col border-t border-g-line pt-3">
                <div
                    v-for="i in state.items"
                    :key="i.id"
                    class="flex justify-between py-1.5 text-[15px]"
                >
                    <span>{{ i.quantity }} × {{ loc(i, 'name') }}</span>
                    <span class="text-g-muted">{{
                        t(`item_status.${i.status}`)
                    }}</span>
                </div>
                <div
                    v-if="state.total"
                    class="mt-2 flex justify-between border-t border-g-line pt-3 font-semibold"
                >
                    <span>{{ t('guest.total') }}</span
                    ><span class="num">{{ money(state.total, true) }}</span>
                </div>
            </div>

            <button
                type="button"
                class="h-12 rounded-md border-[1.5px] border-g-ink text-base font-semibold"
                @click="back"
            >
                {{ t('guest.order_more') }}
            </button>
        </main>
    </div>
</template>

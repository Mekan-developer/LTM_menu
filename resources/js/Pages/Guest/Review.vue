<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import LangSwitch from '@/Components/LangSwitch.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    token: { type: String, required: true },
    table: { type: String, default: null },
    submitted: { type: Boolean, default: false },
    restaurant: { type: String, default: '' },
});

const { t } = useI18n();
const form = useForm({ rating: 0, comment: '' });
const hover = ref(0);
const shown = computed(() => hover.value || form.rating);

const submit = () =>
    form.post(route('guest.review.store', props.token), {
        preserveScroll: true,
    });
</script>

<template>
    <Head :title="t('review.title')" />
    <div class="min-h-[100dvh] bg-g-bg font-guest text-g-ink">
        <header class="border-b border-g-line">
            <div
                class="mx-auto flex max-w-xl items-center gap-2.5 px-5 pt-4 pb-3"
            >
                <span class="flex-1 font-serif text-[22px] leading-6 italic">{{
                    restaurant
                }}</span>
                <span
                    v-if="table"
                    class="flex h-8 items-center rounded-lg bg-g-ink px-3 text-[13px] font-semibold text-g-bg"
                    >{{ t('tables.table') }} {{ table }}</span
                >
                <LangSwitch />
            </div>
        </header>

        <main class="mx-auto flex max-w-xl flex-col gap-5 px-4 pt-8 pb-12">
            <!-- Уже оценили: ссылка одноразовая -->
            <div
                v-if="submitted"
                class="flex flex-col items-center gap-4 px-1 pt-6 text-center"
            >
                <div
                    class="status-badge flex size-[76px] items-center justify-center rounded-full text-white shadow-[0_12px_28px_-12px_rgba(126,53,25,0.65)]"
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
                    {{ t('review.thanks') }}
                </h1>
                <p class="text-[17px] text-g-muted">
                    {{ t('review.thanks_text') }}
                </p>
            </div>

            <form v-else class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="flex flex-col gap-2 px-1 text-center">
                    <h1 class="font-serif text-[34px] leading-10 font-semibold">
                        {{ t('review.title') }}
                    </h1>
                    <p class="text-[17px] text-g-muted">
                        {{ t('review.subtitle') }}
                    </p>
                </div>

                <!-- Звёзды 1–5 -->
                <div
                    class="dish-card flex flex-col items-center gap-3 rounded-2xl bg-g-card px-5 py-6"
                >
                    <div
                        role="radiogroup"
                        :aria-label="t('review.rating')"
                        class="flex gap-1.5"
                        @mouseleave="hover = 0"
                    >
                        <button
                            v-for="n in 5"
                            :key="n"
                            type="button"
                            role="radio"
                            :aria-checked="form.rating === n"
                            :aria-label="`${n} / 5`"
                            class="flex size-14 items-center justify-center rounded-xl transition active:scale-90"
                            @mouseenter="hover = n"
                            @click="form.rating = n"
                        >
                            <svg
                                class="size-11 transition-colors"
                                :class="
                                    n <= shown ? 'text-g-accent' : 'text-g-line'
                                "
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"
                                />
                            </svg>
                        </button>
                    </div>
                    <span
                        class="h-6 text-[17px] font-semibold text-g-accent"
                        aria-live="polite"
                        >{{ shown ? t(`review.label_${shown}`) : '' }}</span
                    >
                    <span
                        v-if="form.errors.rating"
                        class="text-sm text-g-accent-dark"
                        >{{ form.errors.rating }}</span
                    >
                </div>

                <label class="flex flex-col gap-2">
                    <span class="px-1 text-[15px] font-semibold">{{
                        t('review.comment')
                    }}</span>
                    <textarea
                        v-model="form.comment"
                        rows="4"
                        maxlength="1000"
                        class="dish-card resize-none rounded-2xl bg-g-card px-4 py-3.5 text-base leading-6 outline-none placeholder:text-g-muted/70 focus:outline-2 focus:outline-offset-0 focus:outline-g-accent"
                        :placeholder="t('review.comment_ph')"
                    />
                </label>

                <button
                    type="submit"
                    class="h-14 rounded-xl bg-g-accent text-[17px] font-semibold text-white shadow-[0_10px_24px_-10px_rgba(126,53,25,0.6)] transition active:scale-[0.98] active:bg-g-accent-dark disabled:opacity-50 disabled:shadow-none"
                    :disabled="!form.rating || form.processing"
                >
                    {{ t('review.send') }}
                </button>
            </form>
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

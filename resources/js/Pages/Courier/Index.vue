<script setup>
// Экран курьера (ТЗ 1.3, 2.6): только свои заказы. «Забрал» — заказ у курьера,
// «Доставлен» — с фактическим способом оплаты. Сверху — сколько наличных сдать в кассу.
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import DeliveryCompleteModal from '@/Components/DeliveryCompleteModal.vue';
import { useI18n } from '@/i18n';
import { useRealtime } from '@/composables/useRealtime';
import { useSound } from '@/composables/useSound';
import { money, phone, time } from '@/utils/format';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    cashOwed: { type: Number, default: 0 },
});

const { t, loc } = useI18n();
const page = usePage();
const sound = useSound();
const me = computed(() => page.props.auth.user?.id);

// Сигнал, когда заказ назначен этому курьеру или стал готов.
useRealtime('deliveries', 'deliveries.changed', (e) => {
    if (
        e &&
        e.courier_id === me.value &&
        ['ready', 'cooking', 'accepted', 'new'].includes(e.status)
    )
        sound.newOrder();
    router.reload({ only: ['deliveries', 'cashOwed'], preserveScroll: true });
});

const active = computed(() =>
    props.deliveries.filter(
        (d) => !['delivered', 'issued', 'cancelled'].includes(d.status),
    ),
);
const done = computed(() =>
    props.deliveries.filter((d) => d.status === 'delivered'),
);
const completing = ref(null);
const pickUp = (d) =>
    router.post(route('courier.pick-up', d.id), {}, { preserveScroll: true });
</script>

<template>
    <AdminLayout :title="t('nav.courier')">
        <div class="mx-auto flex w-full max-w-xl flex-col gap-4">
            <h1 class="text-[28px] leading-[34px] font-semibold tracking-tight">
                {{ t('nav.courier') }}
            </h1>

            <div
                class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3"
                :class="
                    cashOwed
                        ? 'border-ochre bg-ochre-soft'
                        : 'border-line bg-surface'
                "
            >
                <span class="text-sm font-medium">{{
                    t('courier.cash_owed')
                }}</span>
                <span class="font-mono text-xl font-semibold num">{{
                    money(cashOwed, true)
                }}</span>
            </div>

            <h2 class="text-sm font-semibold text-ink-2">
                {{ t('courier.active') }} · {{ active.length }}
            </h2>
            <article
                v-for="d in active"
                :key="d.id"
                class="flex flex-col gap-3 rounded-lg border bg-surface p-4"
                :class="d.status === 'ready' ? 'border-ok' : 'border-line'"
            >
                <div class="flex items-center gap-2">
                    <span class="font-mono text-lg font-semibold"
                        >№{{ d.order_id }}</span
                    >
                    <Badge :tone="d.status === 'ready' ? 'ok' : 'brand'">{{
                        t(`delivery.status_${d.status}`)
                    }}</Badge>
                    <span class="ml-auto font-mono text-xs text-ink-3">{{
                        time(d.created_at)
                    }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span
                        class="flex items-start gap-1.5 text-[17px] font-semibold"
                        ><Icon
                            name="pin"
                            :size="18"
                            class="mt-0.5 shrink-0"
                        />{{ d.address }}</span
                    >
                    <span class="text-sm text-ink-2">{{ d.name }}</span>
                    <span v-if="d.comment" class="text-xs text-ink-3 italic"
                        >«{{ d.comment }}»</span
                    >
                </div>

                <a
                    :href="`tel:${d.phone}`"
                    class="flex h-11 items-center justify-center gap-2 rounded-md border border-line font-mono text-[15px] hover:bg-surface-2"
                    ><Icon name="phone" :size="16" />{{ phone(d.phone) }} ·
                    {{ t('courier.call') }}</a
                >

                <ul
                    class="flex flex-col gap-0.5 border-t border-line pt-2 text-sm"
                >
                    <li v-for="i in d.items" :key="i.id">
                        <span class="font-mono text-ink-2">{{ i.qty }}×</span>
                        {{ loc(i, 'name') }}
                    </li>
                </ul>
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-ink-2"
                        >{{ t(`pay.${d.planned_method}`) }}
                        <span class="text-xs text-ink-3"
                            >({{ t('delivery.planned') }})</span
                        ></span
                    >
                    <span class="font-mono text-2xl font-semibold num">{{
                        money(d.total, true)
                    }}</span>
                </div>

                <Btn
                    v-if="d.status === 'ready'"
                    size="lg"
                    block
                    @click="pickUp(d)"
                    >{{ t('courier.picked') }}</Btn
                >
                <Btn
                    v-else-if="d.status === 'on_way'"
                    size="lg"
                    block
                    @click="completing = d"
                    >{{ t('courier.delivered') }}</Btn
                >
                <p
                    v-else
                    class="rounded-md bg-surface-2 py-2.5 text-center text-sm text-ink-2"
                >
                    {{ t('courier.wait_kitchen') }}
                </p>
            </article>
            <p
                v-if="!active.length"
                class="rounded-lg border border-dashed border-ink-3 bg-surface p-8 text-center text-sm text-ink-2"
            >
                {{ t('courier.empty') }}
            </p>

            <template v-if="done.length">
                <h2 class="mt-2 text-sm font-semibold text-ink-2">
                    {{ t('courier.done_today') }} · {{ done.length }}
                </h2>
                <div
                    class="flex flex-col rounded-lg border border-line bg-surface"
                >
                    <div
                        v-for="d in done"
                        :key="d.id"
                        class="flex items-center justify-between gap-3 border-t border-line px-4 py-2.5 text-sm first:border-t-0"
                    >
                        <span class="min-w-0 truncate"
                            ><span class="font-mono">№{{ d.order_id }}</span> ·
                            {{ d.address }}</span
                        >
                        <span class="shrink-0 font-mono text-ink-2 num"
                            >{{ d.payment ? t(`pay.${d.payment.method}`) : '' }}
                            {{ money(d.total) }}</span
                        >
                    </div>
                </div>
            </template>
        </div>

        <DeliveryCompleteModal
            :delivery="completing"
            route-name="courier.complete"
            @close="completing = null"
        />
    </AdminLayout>
</template>

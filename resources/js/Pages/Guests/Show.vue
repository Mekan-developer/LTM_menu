<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Icon from '@/Components/Icon.vue';
import GuestFormModal from '@/Components/GuestFormModal.vue';
import { useI18n } from '@/i18n';
import { date, dateTime, money, phone } from '@/utils/format';

const bookingTone = (st) =>
    ({
        new: 'ochre',
        confirmed: 'brand',
        arrived: 'ok',
        cancelled: 'muted',
        no_show: 'danger',
    })[st] ?? 'neutral';

const props = defineProps({
    guest: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    reviews: { type: Array, default: () => [] },
    bookings: { type: Array, default: () => [] },
    bonuses: { type: Array, default: () => [] },
});
const { t } = useI18n();

const editing = ref(false);
const remove = () => {
    if (window.confirm(t('guests.confirm_delete', { name: props.guest.name })))
        router.delete(route('guests.destroy', props.guest.id));
};

const tiles = computed(() => [
    { label: t('guests.visits'), value: String(props.guest.visits) },
    { label: t('guests.spent'), value: money(props.guest.spent), unit: 'TMT' },
    {
        label: t('guests.avg_check'),
        value: money(props.guest.avg_check),
        unit: 'TMT',
    },
    {
        label: t('guests.last_visit'),
        value: props.guest.last_visit ? date(props.guest.last_visit) : '—',
    },
    {
        label: t('guests.bonus_balance'),
        value: money(props.guest.bonus_balance ?? 0),
        unit: 'TMT',
    },
]);
const avgRating = computed(() =>
    props.reviews.length
        ? (
              props.reviews.reduce((s, r) => s + r.rating, 0) /
              props.reviews.length
          )
              .toFixed(1)
              .replace('.', ',')
        : null,
);
</script>

<template>
    <AdminLayout :title="guest.name">
        <div class="flex flex-col gap-5">
            <Link
                :href="route('guests.index')"
                class="inline-flex items-center gap-1 text-sm text-ink-2 hover:text-ink"
                ><Icon name="back" :size="16" />{{ t('nav.guests') }}</Link
            >

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1
                        class="text-[32px] leading-[38px] font-semibold tracking-tight"
                    >
                        {{ guest.name }}
                    </h1>
                    <a
                        :href="`tel:${guest.phone}`"
                        class="font-mono text-lg text-ink-2 hover:text-ink"
                        >{{ phone(guest.phone) }}</a
                    >
                </div>
                <div class="flex gap-2">
                    <Btn variant="secondary" @click="editing = true"
                        ><Icon name="edit" :size="16" />{{
                            t('common.edit')
                        }}</Btn
                    >
                    <Btn variant="ghost" @click="remove">{{
                        t('common.delete')
                    }}</Btn>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div
                    v-for="k in tiles"
                    :key="k.label"
                    class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4"
                >
                    <span class="text-[13px] text-ink-2">{{ k.label }}</span>
                    <span class="font-mono text-2xl font-semibold num"
                        >{{ k.value }}
                        <span v-if="k.unit" class="text-sm text-ink-3">{{
                            k.unit
                        }}</span></span
                    >
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-[320px_1fr]">
                <!-- Карточка -->
                <section
                    class="flex flex-col gap-3 self-start rounded-lg border border-line bg-surface p-5"
                >
                    <h2 class="text-base font-semibold">
                        {{ t('guests.card') }}
                    </h2>
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm"
                    >
                        <dt class="text-ink-2">{{ t('guests.birthday') }}</dt>
                        <dd>
                            {{ guest.birthday ? date(guest.birthday) : '—' }}
                        </dd>
                        <dt class="text-ink-2">{{ t('guests.address') }}</dt>
                        <dd>{{ guest.address || '—' }}</dd>
                        <dt class="text-ink-2">{{ t('guests.since') }}</dt>
                        <dd>
                            {{
                                guest.created_at ? date(guest.created_at) : '—'
                            }}
                            <span class="text-ink-3"
                                >·
                                {{ t(`guests.source_${guest.source}`) }}</span
                            >
                        </dd>
                    </dl>
                    <div
                        v-if="guest.comment"
                        class="rounded-md bg-ochre-soft p-3 text-sm text-ochre-ink"
                    >
                        <span class="mb-0.5 block text-xs font-semibold">{{
                            t('guests.comment')
                        }}</span>
                        {{ guest.comment }}
                    </div>
                </section>

                <div class="flex flex-col gap-5">
                    <!-- История заказов -->
                    <section
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <h2
                            class="border-b border-line px-4 py-3 text-base font-semibold"
                        >
                            {{ t('guests.orders') }}
                        </h2>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr
                                    v-for="o in orders"
                                    :key="o.id"
                                    class="h-11 border-t border-line first:border-t-0"
                                >
                                    <td
                                        class="px-4 font-mono text-xs text-ink-2"
                                    >
                                        {{
                                            o.paid_at
                                                ? dateTime(o.paid_at)
                                                : '—'
                                        }}
                                    </td>
                                    <td
                                        v-if="o.channel && o.channel !== 'hall'"
                                        class="px-4"
                                    >
                                        {{ t(`delivery.type_${o.channel}`) }}
                                    </td>
                                    <td v-else class="px-4">
                                        {{ t('tables.table') }}
                                        {{ o.table ?? '—' }}
                                        <span class="text-ink-3"
                                            >·
                                            {{
                                                o.waiter ??
                                                t('waiter.no_waiter')
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 text-right">
                                        <Badge
                                            v-if="o.status === 'refunded'"
                                            tone="danger"
                                            >{{ t('cashier.refund') }}</Badge
                                        >
                                        <span
                                            v-if="o.discount_percent"
                                            class="font-mono text-xs text-ink-3"
                                            >−{{ o.discount_percent }} %</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 text-right font-mono num"
                                        :class="
                                            o.status === 'refunded'
                                                ? 'text-ink-3 line-through'
                                                : ''
                                        "
                                    >
                                        {{ money(o.total) }}
                                    </td>
                                </tr>
                                <tr v-if="!orders.length">
                                    <td
                                        colspan="4"
                                        class="px-4 py-8 text-center text-ink-3"
                                    >
                                        {{ t('guests.no_orders') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <!-- Брони -->
                    <section class="rounded-lg border border-line bg-surface">
                        <h2
                            class="border-b border-line px-4 py-3 text-base font-semibold"
                        >
                            {{ t('nav.bookings') }}
                        </h2>
                        <div
                            v-for="bk in bookings"
                            :key="bk.id"
                            class="flex items-center justify-between gap-3 border-t border-line px-4 py-2.5 text-sm first:border-t-0"
                        >
                            <span class="font-mono text-xs text-ink-2">{{
                                dateTime(bk.starts_at)
                            }}</span>
                            <span class="flex-1"
                                >{{
                                    t('bookings.guests_n', {
                                        n: bk.guests_count,
                                    })
                                }}<template v-if="bk.table">
                                    · {{ t('tables.table') }}
                                    {{ bk.table }}</template
                                ></span
                            >
                            <Badge :tone="bookingTone(bk.status)">{{
                                t(`bookings.status_${bk.status}`)
                            }}</Badge>
                        </div>
                        <p
                            v-if="!bookings.length"
                            class="px-4 py-6 text-center text-sm text-ink-3"
                        >
                            {{ t('guests.no_bookings') }}
                        </p>
                    </section>

                    <!-- Бонусы -->
                    <section
                        v-if="bonuses.length"
                        class="rounded-lg border border-line bg-surface"
                    >
                        <h2
                            class="border-b border-line px-4 py-3 text-base font-semibold"
                        >
                            {{ t('guests.bonuses') }}
                        </h2>
                        <div
                            v-for="b in bonuses"
                            :key="b.id"
                            class="grid grid-cols-[1fr_auto] gap-x-3 border-t border-line px-4 py-2 text-sm first:border-t-0"
                        >
                            <span
                                >{{ t(`guests.bonus_${b.type}`) }}
                                <span
                                    v-if="b.order_id"
                                    class="font-mono text-xs text-ink-3"
                                    >№{{ b.order_id }}</span
                                ></span
                            >
                            <span
                                class="text-right font-mono num"
                                :class="
                                    b.amount < 0 ? 'text-danger' : 'text-ok'
                                "
                                >{{ b.amount > 0 ? '+' : ''
                                }}{{ money(b.amount) }}</span
                            >
                            <span class="font-mono text-xs text-ink-3"
                                >{{ date(b.created_at) }}
                                <template v-if="b.user"
                                    >· {{ b.user }}</template
                                ></span
                            >
                            <span
                                class="text-right font-mono text-xs text-ink-3"
                                >= {{ money(b.balance_after) }}</span
                            >
                        </div>
                    </section>

                    <!-- Отзывы -->
                    <section class="rounded-lg border border-line bg-surface">
                        <h2
                            class="flex items-center justify-between border-b border-line px-4 py-3 text-base font-semibold"
                        >
                            {{ t('nav.reviews') }}
                            <span
                                v-if="avgRating"
                                class="font-mono text-sm text-ink-2"
                                >★ {{ avgRating }}</span
                            >
                        </h2>
                        <div
                            v-for="r in reviews"
                            :key="r.id"
                            class="flex flex-col gap-1 border-t border-line px-4 py-3 first:border-t-0"
                        >
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span
                                    class="font-mono"
                                    :class="
                                        r.rating <= 3
                                            ? 'text-accent'
                                            : 'text-ochre-ink'
                                    "
                                    :aria-label="`${r.rating} / 5`"
                                    >{{ '★'.repeat(r.rating)
                                    }}<span class="text-line">{{
                                        '★'.repeat(5 - r.rating)
                                    }}</span></span
                                >
                                <span class="font-mono text-xs text-ink-3">{{
                                    date(r.submitted_at)
                                }}</span>
                            </div>
                            <p v-if="r.comment" class="text-sm">
                                «{{ r.comment }}»
                            </p>
                        </div>
                        <p
                            v-if="!reviews.length"
                            class="px-4 py-6 text-center text-sm text-ink-3"
                        >
                            {{ t('guests.no_reviews') }}
                        </p>
                    </section>
                </div>
            </div>
        </div>

        <GuestFormModal
            :show="editing"
            :guest="guest"
            @close="editing = false"
        />
    </AdminLayout>
</template>

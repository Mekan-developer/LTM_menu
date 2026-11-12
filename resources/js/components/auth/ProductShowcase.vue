<script setup lang="ts">
import {
    CircleCheck,
    Footprints,
    Headphones,
    Lamp,
    Search,
    Shirt,
    SlidersHorizontal,
    TrendingUp,
} from '@lucide/vue';
import type { Component } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{
    name: string;
}>();

type Status = 'synced' | 'price' | 'review';

type ShowcaseProduct = {
    title: string;
    sku: string;
    source: string;
    price: string;
    converted: string;
    status: Status;
    icon: Component;
    tint: string;
};

const products: ShowcaseProduct[] = [
    {
        title: 'Wireless Earbuds Pro',
        sku: 'TB-482130',
        source: 'Taobao',
        price: '¥89.00',
        converted: '$12.40',
        status: 'synced',
        icon: Headphones,
        tint: 'from-sky-400/30 to-sky-400/5 text-sky-200',
    },
    {
        title: 'Ceramic Desk Lamp',
        sku: 'TM-190554',
        source: 'Tmall',
        price: '¥120.30',
        converted: '$16.75',
        status: 'price',
        icon: Lamp,
        tint: 'from-amber-300/30 to-amber-300/5 text-amber-100',
    },
    {
        title: 'Linen Oversized Shirt',
        sku: '1688-73021',
        source: '1688',
        price: '¥42.50',
        converted: '$5.92',
        status: 'synced',
        icon: Shirt,
        tint: 'from-rose-400/30 to-rose-400/5 text-rose-200',
    },
    {
        title: 'Trail Running Shoes',
        sku: '1688-55817',
        source: '1688',
        price: '¥156.00',
        converted: '$21.72',
        status: 'review',
        icon: Footprints,
        tint: 'from-emerald-400/30 to-emerald-400/5 text-emerald-200',
    },
];

const statusMeta: Record<Status, { label: string; dot: string }> = {
    synced: { label: 'Synced', dot: 'bg-emerald-400' },
    price: { label: 'Price −6%', dot: 'bg-amber-400' },
    review: { label: 'In review', dot: 'bg-sky-400' },
};

const tabs = ['All', 'Taobao', '1688', 'Tmall'];

const bars = [38, 52, 44, 63, 58, 76, 92];

const sources = ['Taobao', '1688', 'Tmall', 'JD.com'];
</script>

<template>
    <aside
        class="relative isolate flex-col overflow-hidden border-r border-white/5 bg-[#0A0E17] text-white"
    >
        <!-- Background: soft accent glow + fading dot grid -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(55%_45%_at_85%_0%,rgba(91,124,250,0.16),transparent_70%),radial-gradient(40%_35%_at_0%_100%,rgba(91,124,250,0.07),transparent_70%)]"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(rgba(255,255,255,0.06)_1px,transparent_1px)] mask-[radial-gradient(ellipse_75%_65%_at_60%_45%,black,transparent)] bg-size-[22px_22px]"
        />

        <div class="flex h-full flex-col px-10 py-10 xl:px-14 xl:py-12">
            <!-- Brand -->
            <div class="flex items-center gap-3">
                <div
                    class="flex size-9 items-center justify-center rounded-[10px] bg-white text-[#0A0E17] shadow-[0_1px_0_rgba(255,255,255,0.4)_inset,0_6px_16px_-6px_rgba(91,124,250,0.6)]"
                >
                    <AppLogoIcon class="size-5" />
                </div>
                <span class="text-[17px] font-semibold tracking-[-0.01em]">
                    {{ name }}
                </span>
            </div>

            <!-- Headline -->
            <div class="mt-14 max-w-[520px] xl:mt-16">
                <p
                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] py-1 pr-3 pl-2 text-xs font-medium text-white/70"
                >
                    <span
                        class="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,0.15)]"
                    />
                    Product sourcing workspace
                </p>
                <h2
                    class="mt-5 text-[34px] leading-[1.12] font-semibold tracking-[-0.025em] text-balance xl:text-[40px]"
                >
                    Your Products.
                    <span class="text-white/50">One Platform.</span>
                </h2>
                <p
                    class="mt-4 max-w-[440px] text-[15px] leading-relaxed text-white/60"
                >
                    Manage, organize and monitor products from China's leading
                    marketplaces — all in one place.
                </p>
            </div>

            <!-- Product workspace composition (decorative) -->
            <div
                aria-hidden="true"
                class="relative mt-10 min-h-0 flex-1 overflow-hidden mask-[linear-gradient(to_bottom,black_72%,transparent)] select-none [@media(max-height:680px)]:hidden"
            >
                <div class="relative pt-2 xl:pt-12 xl:pr-10">
                    <!-- Catalog card -->
                    <div
                        class="relative w-full max-w-[560px] rounded-2xl border border-white/[0.08] bg-[#111725] shadow-[0_40px_80px_-24px_rgba(0,0,0,0.7)]"
                    >
                        <div
                            class="flex items-center justify-between gap-4 border-b border-white/[0.06] px-5 py-4"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-white/95">
                                    Products
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] text-white/40 tabular-nums"
                                >
                                    12,846 items · 4 sources
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div
                                    class="hidden h-8 items-center gap-2 rounded-lg border border-white/[0.07] bg-white/[0.03] px-2.5 text-[11px] text-white/35 xl:flex"
                                >
                                    <Search class="size-3.5" />
                                    <span class="pr-6">Search products</span>
                                </div>
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg border border-white/[0.07] bg-white/[0.03] text-white/45"
                                >
                                    <SlidersHorizontal class="size-3.5" />
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-1 px-5 pt-3">
                            <span
                                v-for="(tab, index) in tabs"
                                :key="tab"
                                :class="[
                                    'rounded-md px-2.5 py-1 text-[11px] font-medium',
                                    index === 0
                                        ? 'bg-white/[0.08] text-white/90'
                                        : 'text-white/40',
                                ]"
                            >
                                {{ tab }}
                            </span>
                        </div>

                        <div
                            class="mt-3 grid grid-cols-[minmax(0,1fr)_64px_80px_84px] gap-3 px-5 pb-2 text-[10px] font-medium tracking-[0.08em] text-white/30 uppercase"
                        >
                            <span>Product</span>
                            <span>Source</span>
                            <span class="text-right">Price</span>
                            <span>Status</span>
                        </div>

                        <ul>
                            <li
                                v-for="product in products"
                                :key="product.sku"
                                class="grid grid-cols-[minmax(0,1fr)_64px_80px_84px] items-center gap-3 border-t border-white/[0.05] px-5 py-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        :class="[
                                            'flex size-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br ring-1 ring-white/10 ring-inset',
                                            product.tint,
                                        ]"
                                    >
                                        <component
                                            :is="product.icon"
                                            class="size-4"
                                            :stroke-width="1.75"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-[13px] font-medium text-white/90"
                                        >
                                            {{ product.title }}
                                        </p>
                                        <p
                                            class="mt-0.5 truncate text-[11px] text-white/35 tabular-nums"
                                        >
                                            {{ product.sku }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="w-fit rounded-md bg-white/[0.06] px-1.5 py-0.5 text-[11px] font-medium text-white/65"
                                >
                                    {{ product.source }}
                                </span>
                                <div class="text-right tabular-nums">
                                    <p class="text-[13px] font-medium">
                                        {{ product.price }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-white/35">
                                        {{ product.converted }}
                                    </p>
                                </div>
                                <span
                                    class="flex items-center gap-1.5 text-[11px] whitespace-nowrap text-white/60"
                                >
                                    <span
                                        :class="[
                                            'size-1.5 rounded-full',
                                            statusMeta[product.status].dot,
                                        ]"
                                    />
                                    {{ statusMeta[product.status].label }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Floating: weekly imports -->
                    <div
                        class="absolute top-0 right-0 hidden w-[208px] rounded-xl border border-white/[0.09] bg-[#151B2B] p-4 shadow-[0_24px_48px_-16px_rgba(0,0,0,0.75)] xl:block"
                    >
                        <p class="text-[11px] font-medium text-white/45">
                            Imported this week
                        </p>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span
                                class="text-xl font-semibold tracking-tight tabular-nums"
                            >
                                2,481
                            </span>
                            <span
                                class="inline-flex items-center gap-0.5 rounded-full bg-emerald-400/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-300"
                            >
                                <TrendingUp class="size-3" />
                                18.2%
                            </span>
                        </div>
                        <div class="mt-3 flex h-10 items-end gap-1.5">
                            <span
                                v-for="(height, index) in bars"
                                :key="index"
                                :style="{ height: `${height}%` }"
                                :class="[
                                    'flex-1 rounded-[3px]',
                                    index === bars.length - 1
                                        ? 'bg-[#5B7CFA]'
                                        : 'bg-white/[0.12]',
                                ]"
                            />
                        </div>
                    </div>

                    <!-- Floating: sync status -->
                    <div
                        class="absolute top-[318px] right-6 hidden w-[260px] items-center gap-3 rounded-xl border border-white/[0.09] bg-[#151B2B] p-3.5 shadow-[0_24px_48px_-16px_rgba(0,0,0,0.75)] xl:flex"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-300 ring-1 ring-emerald-400/20"
                        >
                            <CircleCheck class="size-[18px]" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium text-white/90">
                                Catalog synced
                            </p>
                            <p class="mt-0.5 text-[11px] text-white/45">
                                342 products updated from 1688 · 2m ago
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="mt-8 flex items-center justify-between gap-6 border-t border-white/[0.06] pt-6 text-xs text-white/40"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span class="shrink-0">Sourcing from</span>
                    <ul class="flex flex-wrap items-center gap-1.5">
                        <li
                            v-for="source in sources"
                            :key="source"
                            class="rounded-md border border-white/[0.07] px-2 py-0.5 font-medium text-white/60"
                        >
                            {{ source }}
                        </li>
                    </ul>
                </div>
                <span class="hidden shrink-0 xl:inline">
                    © {{ new Date().getFullYear() }} {{ name }}
                </span>
            </div>
        </div>
    </aside>
</template>

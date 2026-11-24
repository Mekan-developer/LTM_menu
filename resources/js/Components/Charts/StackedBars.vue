<script setup>
// Столбцы по дням, сложенные из двух частей (наличные + карта). Одна ось.
import { computed, ref } from 'vue';
import { SERIES, niceTicks, shortDate, useWidth } from './chart';

const props = defineProps({
    // [{ date, a, b }]
    points: { type: Array, default: () => [] },
    labels: { type: Array, required: true }, // [подпись a, подпись b]
    format: { type: Function, required: true },
    axisFormat: { type: Function, required: true },
    title: { type: String, required: true },
});

// Ширина = реальная ширина контейнера: подписи остаются 11px на любом экране.
const box = ref(null);
const W = useWidth(box);
const H = 240;
const PAD = { l: 56, r: 16, t: 14, b: 28 };
const ih = H - PAD.t - PAD.b;
const iwRef = computed(() => W.value - PAD.l - PAD.r);
const GAP = 2; // зазор цвета поверхности между частями

const ticks = computed(() =>
    niceTicks(Math.max(0, ...props.points.map((p) => p.a + p.b))),
);
const top = computed(() => ticks.value.at(-1) || 1);
const slot = computed(() => iwRef.value / Math.max(1, props.points.length));
const bw = computed(() => Math.max(4, Math.min(36, slot.value * 0.62)));
const y = (v) => PAD.t + ih - (v / top.value) * ih;
const h = (v) => (v / top.value) * ih;
const bx = (i) => PAD.l + i * slot.value + (slot.value - bw.value) / 2;

// Верхний конец столбца скруглён на 4px, основание прямое — «стоит» на оси.
function roundedTop(x, yTop, w, height, r = 4) {
    if (height <= 0) return '';
    const rr = Math.min(r, height, w / 2);
    const yb = yTop + height;
    return `M${x},${yb}V${yTop + rr}Q${x},${yTop} ${x + rr},${yTop}H${x + w - rr}Q${x + w},${yTop} ${x + w},${yTop + rr}V${yb}Z`;
}

const bars = computed(() =>
    props.points.map((p, i) => {
        const x = bx(i);
        const ha = h(p.a);
        const hb = h(p.b);
        const yA = PAD.t + ih - ha;
        // b лежит поверх a с зазором; верхней частью скругляем ту, что сверху.
        const yB = yA - hb - (p.a && p.b ? GAP : 0);
        return {
            ...p,
            x,
            a: p.b
                ? `M${x},${yA}h${bw.value}v${ha}h${-bw.value}Z`
                : roundedTop(x, yA, bw.value, ha),
            b: roundedTop(x, yB, bw.value, hb),
            raw: p,
        };
    }),
);

const every = computed(() => Math.max(1, Math.ceil(props.points.length / 10)));
const hover = ref(null);
const hp = computed(() =>
    hover.value === null ? null : props.points[hover.value],
);
</script>

<template>
    <figure class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-2">
            <span
                v-for="(l, k) in labels"
                :key="l"
                class="flex items-center gap-1.5"
                ><span
                    class="size-2.5 rounded-[3px]"
                    :style="{
                        background: k ? SERIES.secondary : SERIES.primary,
                    }"
                />{{ l }}</span
            >
        </div>
        <div ref="box" class="relative" @mouseleave="hover = null">
            <svg
                :viewBox="`0 0 ${W} ${H}`"
                class="block h-auto w-full"
                role="img"
                :aria-label="title"
            >
                <g class="text-[11px]">
                    <template v-for="tk in ticks" :key="tk">
                        <line
                            :x1="PAD.l"
                            :x2="W - PAD.r"
                            :y1="y(tk)"
                            :y2="y(tk)"
                            stroke="var(--color-line)"
                            :stroke-dasharray="tk ? '2 4' : null"
                        />
                        <text
                            :x="PAD.l - 8"
                            :y="y(tk) + 4"
                            text-anchor="end"
                            fill="var(--color-ink-3)"
                            class="font-mono"
                        >
                            {{ axisFormat(tk) }}
                        </text>
                    </template>
                    <template v-for="(p, i) in points" :key="p.date">
                        <text
                            v-if="i % every === 0 || i === points.length - 1"
                            :x="bx(i) + bw / 2"
                            :y="H - 8"
                            text-anchor="middle"
                            fill="var(--color-ink-3)"
                            class="font-mono"
                        >
                            {{ shortDate(p.date) }}
                        </text>
                    </template>
                </g>
                <g
                    v-for="(b, i) in bars"
                    :key="`b${b.date}`"
                    :opacity="hover === null || hover === i ? 1 : 0.45"
                >
                    <path :d="b.a" :style="{ fill: SERIES.primary }" />
                    <path :d="b.b" :style="{ fill: SERIES.secondary }" />
                    <rect
                        :x="PAD.l + i * slot"
                        :y="PAD.t"
                        :width="slot"
                        :height="ih"
                        fill="transparent"
                        @mouseenter="hover = i"
                        @touchstart.passive="hover = i"
                    />
                </g>
            </svg>
            <div
                v-if="hp"
                class="pointer-events-none absolute top-1 z-10 flex min-w-40 flex-col gap-1 rounded-md border border-line bg-surface px-3 py-2 text-xs shadow-lg"
                :style="{
                    left: `${((bx(hover) + bw / 2) / W) * 100}%`,
                    transform: `translateX(${hover > points.length / 2 ? 'calc(-100% - 12px)' : '12px'})`,
                }"
            >
                <span class="font-mono text-ink-3">{{
                    shortDate(hp.date)
                }}</span>
                <span
                    v-for="(l, k) in labels"
                    :key="l"
                    class="flex items-center justify-between gap-3"
                    ><span class="flex items-center gap-1.5 text-ink-2"
                        ><span
                            class="size-2 rounded-[2px]"
                            :style="{
                                background: k
                                    ? SERIES.secondary
                                    : SERIES.primary,
                            }"
                        />{{ l }}</span
                    ><span class="font-mono font-semibold text-ink">{{
                        format(k ? hp.b : hp.a)
                    }}</span></span
                >
            </div>
        </div>
    </figure>
</template>

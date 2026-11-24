<script setup>
// Линия по дням + пунктир прошлого периода. Одна ось — одна величина.
import { computed, ref } from 'vue';
import { SERIES, niceTicks, shortDate, useWidth } from './chart';

const props = defineProps({
    // [{ date, value, prev, prevDate }]
    points: { type: Array, default: () => [] },
    format: { type: Function, required: true }, // значение → строка для подсказки
    axisFormat: { type: Function, required: true }, // значение → подпись оси
    label: { type: String, required: true },
    prevLabel: { type: String, required: true },
});

// Ширина = реальная ширина контейнера: подписи остаются 11px на любом экране.
const box = ref(null);
const W = useWidth(box);
const H = 240;
const PAD = { l: 56, r: 16, t: 14, b: 28 };
const ih = H - PAD.t - PAD.b;
const iwRef = computed(() => W.value - PAD.l - PAD.r);

const ticks = computed(() =>
    niceTicks(Math.max(0, ...props.points.flatMap((p) => [p.value, p.prev]))),
);
const top = computed(() => ticks.value.at(-1) || 1);
const x = (i) =>
    PAD.l +
    (props.points.length > 1
        ? (i * iwRef.value) / (props.points.length - 1)
        : iwRef.value / 2);
const y = (v) => PAD.t + ih - (v / top.value) * ih;
const path = (key) =>
    props.points
        .map(
            (p, i) =>
                `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`,
        )
        .join(' ');

// Не больше ~10 подписей по оси X.
const every = computed(() => Math.max(1, Math.ceil(props.points.length / 10)));

const hover = ref(null);
const hp = computed(() =>
    hover.value === null ? null : props.points[hover.value],
);
const colW = computed(() => iwRef.value / Math.max(1, props.points.length - 1));
</script>

<template>
    <figure class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-2">
            <span class="flex items-center gap-1.5"
                ><span
                    class="h-0.5 w-4 rounded-full"
                    :style="{ background: SERIES.primary }"
                />{{ label }}</span
            >
            <span class="flex items-center gap-1.5"
                ><span
                    class="h-0 w-4 border-t-2 border-dashed"
                    :style="{ borderColor: SERIES.previous }"
                />{{ prevLabel }}</span
            >
        </div>
        <div ref="box" class="relative" @mouseleave="hover = null">
            <svg
                :viewBox="`0 0 ${W} ${H}`"
                class="block h-auto w-full"
                role="img"
                :aria-label="label"
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
                            :x="x(i)"
                            :y="H - 8"
                            text-anchor="middle"
                            fill="var(--color-ink-3)"
                            class="font-mono"
                        >
                            {{ shortDate(p.date) }}
                        </text>
                    </template>
                </g>

                <path
                    :d="path('prev')"
                    fill="none"
                    :style="{ stroke: SERIES.previous }"
                    stroke-width="2"
                    stroke-dasharray="5 5"
                    stroke-linejoin="round"
                />
                <path
                    :d="path('value')"
                    fill="none"
                    :style="{ stroke: SERIES.primary }"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />

                <template v-if="hp">
                    <line
                        :x1="x(hover)"
                        :x2="x(hover)"
                        :y1="PAD.t"
                        :y2="PAD.t + ih"
                        stroke="var(--color-ink-3)"
                    />
                    <circle
                        :cx="x(hover)"
                        :cy="y(hp.prev)"
                        r="4"
                        :style="{ fill: SERIES.previous }"
                        stroke="var(--color-surface)"
                        stroke-width="2"
                    />
                    <circle
                        :cx="x(hover)"
                        :cy="y(hp.value)"
                        r="5"
                        :style="{ fill: SERIES.primary }"
                        stroke="var(--color-surface)"
                        stroke-width="2"
                    />
                </template>

                <!-- Зоны наведения шире самой линии -->
                <rect
                    v-for="(p, i) in points"
                    :key="`h${p.date}`"
                    :x="x(i) - colW / 2"
                    :y="PAD.t"
                    :width="colW"
                    :height="ih"
                    fill="transparent"
                    @mouseenter="hover = i"
                    @touchstart.passive="hover = i"
                />
            </svg>

            <div
                v-if="hp"
                class="pointer-events-none absolute top-1 z-10 flex min-w-40 flex-col gap-1 rounded-md border border-line bg-surface px-3 py-2 text-xs shadow-lg"
                :style="{
                    left: `${(x(hover) / W) * 100}%`,
                    transform: `translateX(${hover > points.length / 2 ? 'calc(-100% - 12px)' : '12px'})`,
                }"
            >
                <span class="font-mono text-ink-3">{{
                    shortDate(hp.date)
                }}</span>
                <span class="flex items-center justify-between gap-3"
                    ><span class="flex items-center gap-1.5 text-ink-2"
                        ><span
                            class="size-2 rounded-full"
                            :style="{ background: SERIES.primary }"
                        />{{ label }}</span
                    ><span class="font-mono font-semibold text-ink">{{
                        format(hp.value)
                    }}</span></span
                >
                <span class="flex items-center justify-between gap-3"
                    ><span class="flex items-center gap-1.5 text-ink-2"
                        ><span
                            class="size-2 rounded-full"
                            :style="{ background: SERIES.previous }"
                        />{{ shortDate(hp.prevDate) }}</span
                    ><span class="font-mono text-ink-2">{{
                        format(hp.prev)
                    }}</span></span
                >
            </div>
        </div>
    </figure>
</template>

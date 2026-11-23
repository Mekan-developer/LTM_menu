<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import Badge from '@/Components/Badge.vue';
import Field from '@/Components/Field.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from '@/i18n';
import { dateTime, time } from '@/utils/format';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    modes: { type: Array, default: () => [] },
});

const { t, loc } = useI18n();
const search = ref('');

const form = useForm({ type: 'dish', id: null, mode: 'end_of_day', until: '' });

const candidates = computed(() => {
    const q = search.value.trim().toLowerCase();
    const rows = [];
    props.categories.forEach((c) => {
        rows.push({
            type: 'category',
            id: c.id,
            name: loc(c, 'name'),
            sub: t('stop.whole_category'),
            stopped: c.stopped,
        });
        c.dishes.forEach((d) =>
            rows.push({
                type: 'dish',
                id: d.id,
                name: loc(d, 'name'),
                sub: loc(c, 'name'),
                stopped: d.stopped,
            }),
        );
    });
    return rows
        .filter((r) => !q || r.name.toLowerCase().includes(q))
        .slice(0, 40);
});

function pick(row) {
    form.type = row.type;
    form.id = row.id;
}

const submit = () =>
    form.post(route('stop-list.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('id', 'until'),
    });
const remove = (entry) =>
    router.delete(route('stop-list.destroy', entry.id), {
        preserveScroll: true,
    });
const untilLabel = (e) =>
    e.mode === 'manual'
        ? t('stop.mode_manual')
        : `${t('stop.until')} ${e.mode === 'end_of_day' ? t('stop.end_of_day_short') : time(e.until)}`;
</script>

<template>
    <AdminLayout :title="t('nav.stop_list')">
        <div class="flex flex-col gap-5">
            <h1 class="text-[32px] leading-[38px] font-semibold tracking-tight">
                {{ t('nav.stop_list') }}
            </h1>

            <div class="flex flex-wrap gap-5">
                <section
                    class="min-w-0 flex-[999_1_480px] overflow-hidden rounded-lg border border-line bg-surface"
                >
                    <div
                        class="flex items-center justify-between border-b border-line px-4 py-3"
                    >
                        <h2 class="text-lg font-semibold">
                            {{ t('stop.now') }}
                        </h2>
                        <Badge tone="ochre">{{ entries.length }}</Badge>
                    </div>
                    <div
                        v-for="e in entries"
                        :key="e.id"
                        class="grid grid-cols-[1fr_auto] items-center gap-3 border-b border-line px-4 py-3 last:border-b-0"
                    >
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium"
                                >{{ loc(e, 'name') }}
                                <span
                                    v-if="e.type === 'category'"
                                    class="text-xs text-ink-3"
                                    >· {{ t('stop.whole_category') }}</span
                                ></span
                            >
                            <span class="font-mono text-xs text-ink-2"
                                >{{ untilLabel(e) }} · {{ e.author }} ·
                                {{ dateTime(e.created_at) }}</span
                            >
                        </div>
                        <Btn variant="secondary" size="sm" @click="remove(e)">{{
                            t('stop.remove')
                        }}</Btn>
                    </div>
                    <p
                        v-if="!entries.length"
                        class="px-4 py-8 text-center text-sm text-ink-3"
                    >
                        {{ t('stop.empty') }}
                    </p>
                </section>

                <section
                    class="flex min-w-0 flex-[1_1_340px] flex-col gap-4 rounded-lg border border-line bg-surface p-4"
                >
                    <h2 class="text-lg font-semibold">{{ t('stop.add') }}</h2>
                    <TextInput
                        v-model="search"
                        :placeholder="t('stop.search')"
                        :aria-label="t('stop.search')"
                    />
                    <div
                        class="flex max-h-72 flex-col overflow-y-auto rounded-md border border-line"
                    >
                        <button
                            v-for="row in candidates"
                            :key="row.type + row.id"
                            type="button"
                            class="flex items-center justify-between gap-2 border-b border-line px-3 py-2 text-left text-sm last:border-b-0"
                            :class="
                                form.id === row.id && form.type === row.type
                                    ? 'bg-brand-soft'
                                    : 'hover:bg-surface-2'
                            "
                            :disabled="row.stopped"
                            @click="pick(row)"
                        >
                            <span class="flex flex-col"
                                ><span class="font-medium">{{ row.name }}</span
                                ><span class="text-xs text-ink-3">{{
                                    row.sub
                                }}</span></span
                            >
                            <Badge v-if="row.stopped" tone="ochre">{{
                                t('stop.in_stop')
                            }}</Badge>
                        </button>
                    </div>
                    <Field
                        :label="t('stop.how_long')"
                        :error="form.errors.mode || form.errors.id"
                    >
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="m in modes"
                                :key="m"
                                type="button"
                                class="h-11 rounded-md border px-2 text-[13px] leading-4"
                                :class="
                                    form.mode === m
                                        ? 'border-2 border-brand bg-brand-soft font-semibold'
                                        : 'border-line hover:bg-surface-2'
                                "
                                @click="form.mode = m"
                            >
                                {{ t(`stop.mode_${m}`) }}
                            </button>
                        </div>
                    </Field>
                    <Field
                        v-if="form.mode === 'until_time'"
                        :label="t('stop.until')"
                        for="s-until"
                        :error="form.errors.until"
                    >
                        <TextInput
                            id="s-until"
                            v-model="form.until"
                            type="datetime-local"
                            mono
                        />
                    </Field>
                    <Btn
                        :disabled="!form.id"
                        :loading="form.processing"
                        size="lg"
                        @click="submit"
                        >{{ t('stop.put') }}</Btn
                    >
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

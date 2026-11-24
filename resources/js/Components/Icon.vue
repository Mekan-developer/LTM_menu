<script setup>
// Линейные иконки 18×18, stroke = currentColor. Без эмодзи.
defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 18 },
});

const paths = {
    dashboard:
        '<rect x="2" y="2" width="6" height="6"/><rect x="10" y="2" width="6" height="4"/><rect x="10" y="8" width="6" height="8"/><rect x="2" y="10" width="6" height="6"/>',
    menu: '<path d="M3 4h12M3 9h12M3 14h8"/>',
    stop: '<circle cx="9" cy="9" r="6.5"/><path d="M4.5 13.5l9-9"/>',
    tables: '<circle cx="5" cy="5" r="2.5"/><rect x="10" y="2.5" width="6" height="5"/><rect x="2.5" y="10.5" width="13" height="5"/>',
    staff: '<circle cx="9" cy="6" r="3"/><path d="M3 16c0-3.3 2.7-5 6-5s6 1.7 6 5"/>',
    roles: '<path d="M9 2l6 2.5v4.5c0 3.5-2.6 6-6 7-3.4-1-6-3.5-6-7V4.5z"/><path d="M6.5 9l2 2 3.5-3.5"/>',
    settings:
        '<circle cx="9" cy="9" r="2.5"/><path d="M9 1.5v2M9 14.5v2M1.5 9h2M14.5 9h2M3.7 3.7l1.4 1.4M12.9 12.9l1.4 1.4M3.7 14.3l1.4-1.4M12.9 5.1l1.4-1.4"/>',
    log: '<path d="M4 2h10v14H4z"/><path d="M7 6h4M7 9h4M7 12h2"/>',
    waiter: '<rect x="4" y="1.5" width="10" height="15" rx="2"/><path d="M8 13.5h2"/>',
    kitchen: '<path d="M3 8h12v7H3z"/><path d="M5 8V5a4 4 0 0 1 8 0v3"/>',
    cashier:
        '<rect x="2" y="4" width="14" height="10" rx="1"/><path d="M2 8h14"/>',
    logout: '<path d="M7 3H3v12h4"/><path d="M11 6l3 3-3 3M14 9H7"/>',
    plus: '<path d="M9 3v12M3 9h12"/>',
    minus: '<path d="M3 9h12"/>',
    back: '<path d="M11 3 5 9l6 6"/>',
    check: '<path d="M3.5 9.5 7.5 13.5 14.5 5"/>',
    bell: '<path d="M4.5 12.5V8a4.5 4.5 0 0 1 9 0v4.5l1.5 1.5h-12z"/><path d="M7.5 15.5a1.5 1.5 0 0 0 3 0"/>',
    print: '<path d="M5 6V2h8v4"/><rect x="2" y="6" width="14" height="7" rx="1"/><path d="M5 11h8v5H5z"/>',
    qr: '<rect x="2" y="2" width="5" height="5"/><rect x="11" y="2" width="5" height="5"/><rect x="2" y="11" width="5" height="5"/><path d="M11 11h2v2h-2zM14 14h2v2h-2zM11 15h1M15 11h1"/>',
    trash: '<path d="M3 5h12M7 5V3h4v2M5 5l1 11h6l1-11"/>',
    edit: '<path d="M12 2.5l3.5 3.5L6 15.5H2.5V12z"/>',
    more: '<circle cx="4" cy="9" r="1"/><circle cx="9" cy="9" r="1"/><circle cx="14" cy="9" r="1"/>',
    guests: '<circle cx="6.5" cy="6" r="2.5"/><path d="M2 15c0-2.6 2-4 4.5-4s4.5 1.4 4.5 4"/><circle cx="12.5" cy="6.5" r="2"/><path d="M12.5 11c2 0 3.5 1.1 3.5 3.3"/>',
    star: '<path d="M9 2.2l2.1 4.3 4.7.7-3.4 3.3.8 4.7L9 13l-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z"/>',
    chart: '<path d="M2.5 15.5h13"/><path d="M4.5 12.5V9M8 12.5V5M11.5 12.5V7.5M15 12.5V3.5"/>',
    excel: '<path d="M4 2h7l3 3v11H4z"/><path d="M11 2v3h3"/><path d="M6.5 9l3 4M9.5 9l-3 4"/>',
};
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 18 18"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        v-html="paths[name] ?? ''"
    />
</template>

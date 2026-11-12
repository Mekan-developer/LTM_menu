<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProductShowcase from '@/components/auth/ProductShowcase.vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex min-h-dvh bg-background font-display [font-feature-settings:'cv11','ss01'] text-foreground antialiased"
    >
        <!-- Branding (desktop / landscape tablet) -->
        <ProductShowcase
            :name="name"
            class="sticky top-0 hidden h-dvh lg:flex lg:w-[55%] lg:shrink-0"
        />

        <!-- Authentication -->
        <main
            class="flex min-h-dvh min-w-0 flex-1 flex-col md:bg-muted/40 lg:bg-background"
        >
            <div
                class="flex flex-1 items-center justify-center px-5 py-10 sm:px-8 md:py-16"
            >
                <div
                    class="w-full max-w-[400px] md:max-w-[472px] lg:max-w-[400px]"
                >
                    <!-- Compact brand for mobile / portrait tablet -->
                    <div
                        class="mb-10 flex items-center gap-3 md:mb-8 md:justify-center lg:hidden"
                    >
                        <div
                            class="flex size-9 items-center justify-center rounded-[10px] bg-primary text-primary-foreground shadow-sm"
                        >
                            <AppLogoIcon class="size-5" />
                        </div>
                        <span
                            class="text-[17px] font-semibold tracking-[-0.01em]"
                        >
                            {{ name }}
                        </span>
                    </div>

                    <div
                        class="md:rounded-2xl md:border md:bg-card md:p-9 md:shadow-[0_1px_2px_rgba(16,24,40,0.04),0_12px_32px_-12px_rgba(16,24,40,0.12)] lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none"
                    >
                        <slot />
                    </div>
                </div>
            </div>

            <footer
                class="flex items-center justify-center gap-1.5 px-6 pb-6 text-xs text-muted-foreground"
            >
                <ShieldCheck class="size-3.5" aria-hidden="true" />
                Secure, encrypted connection
            </footer>
        </main>
    </div>
</template>

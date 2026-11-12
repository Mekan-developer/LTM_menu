<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    CircleCheck,
    Eye,
    EyeOff,
    Lock,
    User,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthShowcaseLayout from '@/layouts/auth/AuthShowcaseLayout.vue';
import { store } from '@/routes/login';

defineOptions({
    layout: AuthShowcaseLayout,
});

defineProps<{
    status?: string;
}>();

const showPassword = ref(false);

const inputClass =
    'h-11 rounded-[10px] bg-background pr-3.5 pl-10.5 text-[15px] shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition-[border-color,box-shadow,background-color] duration-150 placeholder:text-muted-foreground/70 hover:border-foreground/20 md:text-[15px] dark:bg-input/20 focus-visible:border-brand focus-visible:ring-4 focus-visible:ring-brand/15 aria-invalid:border-red-500/70 aria-invalid:focus-visible:border-red-500 aria-invalid:focus-visible:ring-red-500/15';

const iconClass =
    'pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted-foreground/80 transition-colors duration-150 group-focus-within:text-foreground';
</script>

<template>
    <Head title="Sign in" />

    <div>
        <h1
            class="text-[26px] leading-tight font-semibold tracking-[-0.02em] sm:text-[28px]"
        >
            Welcome back
        </h1>
        <p class="mt-2 text-[15px] leading-relaxed text-muted-foreground">
            Sign in to access your product management workspace.
        </p>
    </div>

    <div
        v-if="status"
        role="status"
        class="mt-6 flex items-start gap-2.5 rounded-[10px] border border-emerald-600/20 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-emerald-300"
    >
        <CircleCheck class="mt-px size-4 shrink-0" aria-hidden="true" />
        <span>{{ status }}</span>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="mt-8 grid gap-5"
    >
        <!-- Login -->
        <div class="grid gap-2">
            <Label for="email" class="text-[13px] font-medium">Login</Label>
            <div class="group relative">
                <User :class="iconClass" aria-hidden="true" />
                <Input
                    id="email"
                    type="text"
                    name="email"
                    inputmode="email"
                    autocapitalize="none"
                    :spellcheck="false"
                    required
                    v-focus
                    autocomplete="username"
                    placeholder="Enter your login"
                    :aria-invalid="errors.email ? true : undefined"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                    :class="inputClass"
                />
            </div>
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="-translate-y-0.5 opacity-0"
            >
                <p
                    v-if="errors.email"
                    id="email-error"
                    class="flex items-start gap-1.5 text-[13px] leading-5 text-red-600 dark:text-red-400"
                >
                    <CircleAlert
                        class="mt-[3px] size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{ errors.email }}</span>
                </p>
            </Transition>
        </div>

        <!-- Password -->
        <div class="grid gap-2">
            <Label for="password" class="text-[13px] font-medium">
                Password
            </Label>
            <div class="group relative">
                <Lock :class="iconClass" aria-hidden="true" />
                <Input
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    :aria-invalid="errors.password ? true : undefined"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                    :class="[inputClass, 'pr-12']"
                />
                <button
                    type="button"
                    class="absolute inset-y-1 right-1 flex w-9 items-center justify-center rounded-[7px] text-muted-foreground transition-colors duration-150 outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-brand/40"
                    :aria-label="
                        showPassword ? 'Hide password' : 'Show password'
                    "
                    :aria-pressed="showPassword"
                    aria-controls="password"
                    @click="showPassword = !showPassword"
                >
                    <EyeOff
                        v-if="showPassword"
                        class="size-[18px]"
                        aria-hidden="true"
                    />
                    <Eye v-else class="size-[18px]" aria-hidden="true" />
                </button>
            </div>
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="-translate-y-0.5 opacity-0"
            >
                <p
                    v-if="errors.password"
                    id="password-error"
                    class="flex items-start gap-1.5 text-[13px] leading-5 text-red-600 dark:text-red-400"
                >
                    <CircleAlert
                        class="mt-[3px] size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{ errors.password }}</span>
                </p>
            </Transition>
        </div>

        <!-- Remember me -->
        <div class="flex items-center gap-2.5">
            <Checkbox
                id="remember"
                name="remember"
                class="size-[18px] rounded-[5px] border-foreground/20 transition-colors duration-150 hover:border-foreground/35 focus-visible:border-brand focus-visible:ring-4 focus-visible:ring-brand/15"
            />
            <Label
                for="remember"
                class="cursor-pointer text-sm font-normal text-muted-foreground"
            >
                Remember me
            </Label>
        </div>

        <Button
            type="submit"
            class="group mt-1 h-11 w-full rounded-[10px] text-[15px] font-semibold shadow-[0_1px_2px_rgba(16,24,40,0.1),inset_0_1px_0_rgba(255,255,255,0.08)] transition-[background-color,box-shadow,transform] duration-150 hover:bg-primary/90 hover:shadow-[0_6px_16px_-6px_rgba(16,24,40,0.35)] focus-visible:ring-4 focus-visible:ring-brand/25 active:scale-[0.99] disabled:opacity-80"
            :disabled="processing"
            :aria-busy="processing"
            data-test="login-button"
        >
            <template v-if="processing">
                <Spinner />
                Signing in…
            </template>
            <template v-else>
                Sign in
                <ArrowRight
                    class="size-4 transition-transform duration-150 group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </template>
        </Button>
    </Form>
</template>

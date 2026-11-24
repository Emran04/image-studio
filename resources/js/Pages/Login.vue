<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import AuthLayout from '@/components/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps({
    submitUrl: { type: String, required: true },
    registerUrl: { type: String, default: null },
    admin: { type: Boolean, default: false },
});

const form = useForm({ email: '', password: '' });

function signIn() {
    if (!form.processing) {
        form.post(props.submitUrl, {
            onFinish: () => form.reset('password'),
        });
    }
}
</script>

<template>
    <AuthLayout :admin="admin">
        <Head :title="admin ? 'Admin sign in · Stillroom' : 'Sign in · Stillroom'" />

        <p class="mb-3 font-mono text-xs tracking-[.16em] text-primary uppercase">{{ admin ? 'Restricted access' : 'Workspace access' }}</p>
        <h1 id="auth-title" class="text-4xl font-semibold tracking-[-.055em] sm:text-5xl">{{ admin ? 'Admin sign in' : 'Sign in' }}</h1>
        <p class="mt-3 text-sm text-muted-foreground">{{ admin ? 'Use an administrator account to open the operations report.' : 'Use your account to open the image studio.' }}</p>

        <form :action="submitUrl" method="post" novalidate class="mt-8 grid gap-5" :aria-busy="form.processing" @submit.prevent="signIn">
            <div class="grid gap-2">
                <label for="login-email" class="text-sm font-semibold">Email address</label>
                <Input id="login-email" v-model="form.email" name="email" type="email" autocomplete="username" maxlength="255" required placeholder="you@example.com" class="h-11 bg-white" :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'login-email-error' : undefined" @input="form.clearErrors('email')" />
                <p v-if="form.errors.email" id="login-email-error" class="text-sm text-destructive" role="alert">{{ form.errors.email }}</p>
            </div>
            <div class="grid gap-2">
                <label for="login-password" class="text-sm font-semibold">Password</label>
                <Input id="login-password" v-model="form.password" name="password" type="password" autocomplete="current-password" maxlength="1024" required class="h-11 bg-white" :aria-invalid="!!form.errors.password" :aria-describedby="form.errors.password ? 'login-password-error' : undefined" @input="form.clearErrors('password')" />
                <p v-if="form.errors.password" id="login-password-error" class="text-sm text-destructive" role="alert">{{ form.errors.password }}</p>
            </div>

            <Button type="submit" class="mt-1 h-11 w-full justify-between px-4" :disabled="form.processing">
                {{ form.processing ? 'Signing in…' : 'Sign in' }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Button>
        </form>
        <p v-if="registerUrl" class="mt-6 border-t border-border pt-5 text-sm text-muted-foreground">
            New to Stillroom? <Link :href="registerUrl" class="font-semibold text-primary underline-offset-4 hover:underline">Create an account</Link>
        </p>
    </AuthLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import AuthLayout from '@/components/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps({
    submitUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
});

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const fields = [
    { name: 'name', label: 'Name', type: 'text', autocomplete: 'name', placeholder: 'Your name', maxlength: 255 },
    { name: 'email', label: 'Email address', type: 'email', autocomplete: 'username', placeholder: 'you@example.com', maxlength: 255 },
    { name: 'password', label: 'Password', type: 'password', autocomplete: 'new-password', hint: 'Use at least 8 characters.' },
    { name: 'password_confirmation', label: 'Confirm password', type: 'password', autocomplete: 'new-password' },
];

function register() {
    if (!form.processing) {
        form.post(props.submitUrl, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
}
</script>

<template>
    <AuthLayout>
        <Head title="Create an account · Stillroom" />

        <p class="mb-3 font-mono text-xs tracking-[.16em] text-primary uppercase">Workspace access</p>
        <h1 id="auth-title" class="text-4xl font-semibold tracking-[-.055em] sm:text-5xl">Create an account</h1>
        <p class="mt-3 text-sm text-muted-foreground">Set up your account to open the image studio.</p>

        <form :action="submitUrl" method="post" novalidate class="mt-7 grid gap-4" :aria-busy="form.processing" @submit.prevent="register">
            <div v-for="field in fields" :key="field.name" class="grid gap-1.5">
                <label :for="`register-${field.name}`" class="text-sm font-semibold">{{ field.label }}</label>
                <Input
                    :id="`register-${field.name}`"
                    v-model="form[field.name]"
                    :name="field.name"
                    :type="field.type"
                    :autocomplete="field.autocomplete"
                    :placeholder="field.placeholder"
                    :maxlength="field.maxlength"
                    :aria-invalid="!!form.errors[field.name]"
                    :aria-describedby="form.errors[field.name] || field.hint ? `register-${field.name}-message` : undefined"
                    required
                    class="h-11 bg-white"
                    @input="form.clearErrors(field.name === 'password_confirmation' ? 'password' : field.name)"
                />
                <p v-if="form.errors[field.name]" :id="`register-${field.name}-message`" class="text-sm text-destructive" role="alert">{{ form.errors[field.name] }}</p>
                <p v-else-if="field.hint" :id="`register-${field.name}-message`" class="text-xs text-muted-foreground">{{ field.hint }}</p>
            </div>

            <Button type="submit" class="mt-1 h-11 w-full justify-between px-4" :disabled="form.processing">
                {{ form.processing ? 'Creating account…' : 'Create account' }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Button>
        </form>

        <p class="mt-6 border-t border-border pt-5 text-sm text-muted-foreground">
            Already have an account? <Link :href="loginUrl" class="font-semibold text-primary underline-offset-4 hover:underline">Sign in</Link>
        </p>
    </AuthLayout>
</template>

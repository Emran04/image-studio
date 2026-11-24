<script setup>
import { Link } from '@inertiajs/vue3';
import { Scan } from '@lucide/vue';

defineProps({
    admin: { type: Boolean, default: false },
});
</script>

<template>
    <main class="grid min-h-dvh grid-rows-[auto_1fr] bg-background text-foreground md:grid-cols-[1fr_1.05fr] md:grid-rows-1">
        <div class="relative flex flex-col overflow-hidden bg-[#202452] px-6 py-6 text-white sm:px-10 md:justify-between md:p-12">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_80%_65%,#6469c880,transparent_62%)]"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-25 [background-image:linear-gradient(#a7abff55_1px,transparent_1px),linear-gradient(90deg,#a7abff55_1px,transparent_1px)] [background-size:29px_29px]"></div>
            <div aria-hidden="true" class="pointer-events-none absolute top-1/2 -right-24 hidden size-80 -translate-y-1/2 rotate-[-28deg] items-center justify-center md:flex">
                <div v-for="inset in [0, 14, 28, 42, 56, 70, 84]" :key="inset" class="absolute rounded-[44%] border border-[#bdc5ff]/35" :style="{ inset: `${inset}px` }"></div>
            </div>

            <Link href="/" class="relative flex w-fit items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                <span class="grid size-10 place-items-center rounded-xl border border-white/25 bg-white/10">
                    <Scan class="size-5" aria-hidden="true" />
                </span>
                <span>
                    <span class="block text-lg leading-none font-semibold tracking-tight">Stillroom</span>
                    <span class="mt-1 block font-mono text-[0.63rem] tracking-[.17em] text-[#d3d7ff] uppercase">{{ admin ? 'Administration' : 'Image studio' }}</span>
                </span>
            </Link>

            <div class="relative hidden max-w-md md:block">
                <p class="mb-4 flex items-center gap-2 font-mono text-[0.65rem] tracking-[.2em] text-[#ced3ff] uppercase">
                    <span class="h-px w-5 bg-[#b4bcff]" aria-hidden="true"></span>
                    {{ admin ? 'The operations desk' : 'The generation desk' }}
                </p>
                <p class="text-[clamp(2.6rem,5.4vw,5rem)] leading-[.96] font-medium tracking-[-.065em]">
                    <template v-if="admin">Studio<br />operations.</template>
                    <template v-else>Imagine it.<br />Then make it.</template>
                </p>
            </div>

            <p class="relative hidden border-t border-white/20 pt-4 font-mono text-[0.65rem] tracking-[.15em] text-[#d3d7ff] uppercase md:block">Stillroom / {{ admin ? 'Admin' : 'Workspace' }}</p>
        </div>

        <section aria-labelledby="auth-title" class="flex min-w-0 items-center justify-center px-6 py-10 sm:px-10 md:py-14">
            <div class="w-full max-w-sm">
                <slot />
            </div>
        </section>
    </main>
</template>

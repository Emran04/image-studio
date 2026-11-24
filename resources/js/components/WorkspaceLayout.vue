<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, Images, LogOut, Scan, Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = defineProps({
    active: { type: String, required: true },
    createUrl: { type: String, required: true },
    indexUrl: { type: String, required: true },
    logoutUrl: { type: String, required: true },
});

const links = [
    { key: 'create', label: 'Image Generation', url: props.createUrl, icon: ImagePlus, detail: 'Prompt studio' },
    { key: 'index', label: 'Generation History', url: props.indexUrl, icon: Images, detail: 'Image archive' },
];
const current = links.find((link) => link.key === props.active);
const logoutForm = useForm({});
</script>

<template>
    <div class="min-h-dvh bg-background text-foreground md:grid md:grid-cols-[14.5rem_minmax(0,1fr)]">
        <aside class="flex flex-col gap-4 border-b border-border bg-[#eeeee9] px-4 py-4 sm:px-6 md:sticky md:top-0 md:h-dvh md:gap-10 md:border-r md:border-b-0 md:px-4 md:py-6">
            <Link href="/" class="flex w-fit items-center gap-3 rounded-lg px-1 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                <span class="grid size-9 place-items-center rounded-[10px] bg-[#24264e] text-white">
                    <Scan class="size-5" aria-hidden="true" />
                </span>
                <span>
                    <span class="block text-[1.05rem] leading-none font-semibold tracking-tight">Stillroom</span>
                    <span class="mt-1 block font-mono text-[0.6rem] tracking-[.15em] text-muted-foreground uppercase">Image studio</span>
                </span>
            </Link>

            <nav aria-label="Workspace">
                <p class="mb-2 px-2 font-mono text-[0.65rem] font-medium tracking-[.16em] text-muted-foreground uppercase md:mb-3">Workspace</p>
                <ul class="grid gap-1">
                    <li v-for="link in links" :key="link.key">
                        <Link :href="link.url" :aria-current="link.key === active ? 'page' : undefined" class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-primary" :class="link.key === active ? 'border-[#d8d9eb] bg-[#e2e3f3] text-[#292d6a] hover:bg-[#d9dcf2]' : 'border-transparent text-muted-foreground hover:bg-white/65 hover:text-foreground'">
                            <component :is="link.icon" class="size-4 shrink-0" aria-hidden="true" />
                            {{ link.label }}
                            <span v-if="link.key === active" class="ml-auto size-1.5 rounded-full bg-primary" aria-hidden="true"></span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="mt-auto hidden items-center gap-2 border-t border-border pt-4 text-xs text-muted-foreground md:flex">
                <span class="size-2 rounded-full border border-[#777ac2]" aria-hidden="true"></span>
                Local workspace
            </div>
        </aside>

        <main class="flex min-w-0 flex-col">
            <header class="flex min-h-14 items-center justify-between gap-2 border-b border-border px-4 sm:px-8">
                <div class="flex items-center gap-2 text-sm">
                    <span class="hidden text-muted-foreground sm:inline">Workspace</span>
                    <span class="hidden text-muted-foreground sm:inline" aria-hidden="true">/</span>
                    <span class="font-medium">{{ current.label }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden items-center gap-1.5 rounded-full border border-border bg-white px-2.5 py-1 font-mono text-[0.6rem] tracking-wider text-muted-foreground uppercase lg:flex">
                        <Sparkles class="size-3 text-primary" aria-hidden="true" />
                        {{ current.detail }}
                    </span>
                    <form :action="logoutUrl" method="post" @submit.prevent="logoutForm.post(logoutUrl)">
                        <Button type="submit" variant="outline" size="sm" class="border-border bg-white" :disabled="logoutForm.processing">
                            <LogOut class="size-4" aria-hidden="true" />
                            {{ logoutForm.processing ? 'Signing out…' : 'Log out' }}
                        </Button>
                    </form>
                </div>
            </header>
            <slot />
        </main>
    </div>
</template>

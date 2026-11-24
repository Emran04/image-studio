<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Scan } from '@lucide/vue';
import { ref, watch } from 'vue';
import OperationsUsersTable from '@/components/OperationsUsersTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { amount, metrics } from '@/lib/operationsReport';

const props = defineProps({
    report: { type: Object, required: true },
    month: { type: String, required: true },
    markupPercent: { type: Number, required: true },
    timezone: { type: String, required: true },
    reportUrl: { type: String, required: true },
    logoutUrl: { type: String, required: true },
});

const view = ref('day');
const filter = useForm({ month: props.month });
const dateFormat = new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeZone: 'UTC' });

watch(() => props.month, (month) => {
    filter.month = month;
});

function filterReport() {
    filter.get(props.reportUrl, { preserveState: true });
}
</script>

<template>
    <div class="min-h-dvh bg-background text-foreground">
        <Head title="Operations · Stillroom Admin" />

        <header class="border-b border-white/10 bg-[#202452] text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-8">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl border border-white/25 bg-white/10">
                        <Scan class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-lg leading-none font-semibold tracking-tight">Stillroom</p>
                        <p class="mt-1 font-mono text-[.63rem] tracking-[.17em] text-[#d3d7ff] uppercase">Administration</p>
                    </div>
                </div>
                <Link :href="logoutUrl" method="post" as="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-[#edf0ff] hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2">
                    <LogOut class="size-4" aria-hidden="true" /> Sign out
                </Link>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-7 px-4 py-8 sm:px-8 sm:py-12">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="mb-2 font-mono text-xs tracking-[.18em] text-primary uppercase">Financial overview</p>
                    <h1 class="text-4xl font-semibold tracking-[-.055em] sm:text-5xl">Operations</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Generation costs and profit by submission day and user.</p>
                </div>
                <form class="grid gap-1.5" @submit.prevent="filterReport">
                    <label for="report-month" class="text-xs font-semibold text-muted-foreground">Reporting month</label>
                    <div class="flex gap-2">
                        <Input id="report-month" v-model="filter.month" name="month" type="month" min="1000-01" max="9999-12" required class="h-9 w-auto bg-white" />
                        <Button type="submit" :disabled="filter.processing">Apply</Button>
                    </div>
                </form>
            </div>

            <section aria-label="Monthly totals" class="grid overflow-hidden rounded-xl border border-border bg-white sm:grid-cols-3">
                <div v-for="metric in metrics" :key="metric.key" class="min-w-0 border-b border-border px-5 py-4 last:border-b-0 sm:border-r sm:border-b-0 sm:px-6 sm:py-6 sm:last:border-r-0" :class="{ 'bg-accent text-accent-foreground': metric.key === 'profit' }">
                    <p class="font-mono text-xs tracking-wider text-muted-foreground uppercase">{{ metric.label }}</p>
                    <p class="mt-2 break-all text-3xl leading-tight font-semibold tracking-tight tabular-nums">{{ amount(report.totals, metric.key) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ metric.description }}</p>
                </div>
            </section>

            <section aria-labelledby="breakdown-title" class="grid min-w-0 gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="breakdown-title" class="text-xl font-semibold tracking-tight">Cost breakdown</h2>
                        <p v-if="report.totals.missingPricing" class="mt-1 text-sm text-destructive">
                            Totals exclude {{ report.totals.missingPricing }} completed {{ report.totals.missingPricing === 1 ? 'generation' : 'generations' }} with missing or invalid pricing data.
                        </p>
                    </div>
                    <div class="inline-flex rounded-lg border border-border bg-secondary p-1" role="group" aria-label="Group costs">
                        <button v-for="option in ['day', 'user']" :key="option" type="button" :aria-pressed="view === option" class="rounded-md px-3 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-ring" :class="view === option ? 'bg-white text-primary shadow-xs' : 'text-muted-foreground hover:text-foreground'" @click="view = option">
                            By {{ option }}
                        </button>
                    </div>
                </div>

                <div v-if="!report.days.length" class="rounded-xl border border-dashed border-border bg-white px-6 py-12 text-center">
                    <p class="font-semibold">No finished generations this month</p>
                    <p class="mt-1 text-sm text-muted-foreground">Choose another month to view its activity.</p>
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-border bg-white">
                    <template v-if="view === 'day'">
                        <details v-for="day in report.days" :key="day.date" class="group border-b border-border last:border-b-0">
                            <summary class="grid cursor-pointer list-none grid-cols-3 gap-x-2 gap-y-3 px-4 py-4 hover:bg-secondary/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,1fr))] sm:items-center sm:px-6 [&::-webkit-details-marker]:hidden">
                                <div class="col-span-3 flex items-center gap-2 sm:col-span-1">
                                    <ChevronDown class="size-4 shrink-0 -rotate-90 text-primary transition-transform group-open:rotate-0 motion-reduce:transition-none" aria-hidden="true" />
                                    <div>
                                        <time :datetime="day.date" class="block text-sm font-semibold">{{ dateFormat.format(new Date(day.date)) }}</time>
                                        <span class="text-xs text-muted-foreground">{{ day.users.length }} {{ day.users.length === 1 ? 'user' : 'users' }}<template v-if="day.missingPricing"> · {{ day.missingPricing }} missing pricing</template></span>
                                    </div>
                                </div>
                                <div v-for="metric in metrics" :key="metric.key" class="min-w-0 sm:text-right">
                                    <p class="text-xs text-muted-foreground">{{ metric.label }}</p>
                                    <p class="mt-1 break-all font-mono text-xs tabular-nums sm:text-sm">{{ amount(day, metric.key) }}</p>
                                </div>
                            </summary>
                            <OperationsUsersTable :users="day.users" />
                        </details>
                    </template>
                    <OperationsUsersTable v-else :users="report.users" />
                </div>
            </section>

            <p class="border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground">
                USD · {{ timezone }}. Credit spend is calculated with the workspace’s {{ markupPercent }}% markup; it isn’t a record of credit debits. Provider costs use saved token usage and rates, not invoices. Pending generations are excluded; failed generations are treated as zero cost.
            </p>
        </main>
    </div>
</template>

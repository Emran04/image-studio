<script setup>
import { amount, metrics } from '@/lib/operationsReport';

defineProps({
    users: { type: Array, required: true },
});
</script>

<template>
    <div class="overflow-x-auto" role="region" aria-label="User cost breakdown" tabindex="0">
        <table class="w-full min-w-[36rem] text-sm">
            <thead class="border-b border-border bg-secondary/60 text-xs text-muted-foreground">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-medium sm:px-6">User</th>
                    <th v-for="metric in metrics" :key="metric.key" scope="col" class="px-4 py-3 text-right font-medium sm:px-6">{{ metric.label }}<span v-if="metric.key !== 'spend'" class="block font-normal">Calculated</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-for="user in users" :key="user.id">
                    <th scope="row" class="px-4 py-3 text-left font-medium sm:px-6">
                        <span class="block break-all">{{ user.name }}</span>
                        <span class="block break-all text-xs font-normal text-muted-foreground">{{ user.email }}</span>
                        <span v-if="user.missingPricing" class="mt-1 block text-xs font-normal text-destructive">{{ user.missingPricing }} missing pricing</span>
                    </th>
                    <td v-for="metric in metrics" :key="metric.key" class="px-4 py-3 text-right font-mono whitespace-nowrap tabular-nums sm:px-6">{{ amount(user, metric.key) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

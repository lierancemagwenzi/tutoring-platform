<script setup>
import { computed } from 'vue'
import { ChartPieIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'

const props = defineProps({
    snapshot: { type: Object, default: null },
})

const byType = computed(() => Object.entries(props.snapshot?.by_type ?? {}))
</script>

<template>
    <div class="bg-card rounded-2xl p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Performance Snapshot</h2>

        <div v-if="!snapshot || snapshot.activities_completed === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="bg-card-alt text-muted flex h-14 w-14 items-center justify-center rounded-full">
                <ChartPieIcon class="h-7 w-7" />
            </span>
            <p class="text-muted mt-3 text-sm">Complete an activity to see your performance here.</p>
        </div>

        <template v-else>
            <div class="mt-6 flex items-center gap-6">
                <div class="border-border flex h-24 w-24 shrink-0 items-center justify-center rounded-full border-8">
                    <p class="text-body text-xl font-bold">{{ snapshot.average_percentage }}%</p>
                </div>
                <div>
                    <p class="text-body font-semibold">Overall average</p>
                    <p class="text-muted text-sm">Across {{ snapshot.activities_completed }} completed activities</p>
                </div>
            </div>

            <ul v-if="byType.length" class="border-border mt-6 space-y-3 border-t pt-4">
                <li v-for="[type, data] in byType" :key="type" class="flex items-center justify-between text-sm">
                    <span class="text-muted flex items-center gap-2">
                        <component :is="blockRegistry[type]?.icon ?? ChartPieIcon" class="h-4 w-4" />
                        {{ blockRegistry[type]?.label ?? type }}
                        <span class="text-xs">({{ data.count }})</span>
                    </span>
                    <span class="text-body font-semibold">{{ data.average_percentage }}%</span>
                </li>
            </ul>
        </template>
    </div>
</template>

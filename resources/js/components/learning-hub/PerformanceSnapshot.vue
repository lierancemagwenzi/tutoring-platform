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
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Performance Snapshot</h2>

        <div v-if="!snapshot || snapshot.activities_completed === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <ChartPieIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">Complete an activity to see your performance here.</p>
        </div>

        <template v-else>
            <div class="mt-6 flex items-center gap-6">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border-8 border-gray-100">
                    <p class="text-ink text-xl font-bold">{{ snapshot.average_percentage }}%</p>
                </div>
                <div>
                    <p class="text-ink font-semibold">Overall average</p>
                    <p class="text-sm text-gray-500">Across {{ snapshot.activities_completed }} completed activities</p>
                </div>
            </div>

            <ul v-if="byType.length" class="mt-6 space-y-3 border-t border-gray-100 pt-4">
                <li v-for="[type, data] in byType" :key="type" class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-gray-600">
                        <component :is="blockRegistry[type]?.icon ?? ChartPieIcon" class="h-4 w-4 text-gray-400" />
                        {{ blockRegistry[type]?.label ?? type }}
                        <span class="text-xs text-gray-400">({{ data.count }})</span>
                    </span>
                    <span class="text-ink font-semibold">{{ data.average_percentage }}%</span>
                </li>
            </ul>
        </template>
    </div>
</template>

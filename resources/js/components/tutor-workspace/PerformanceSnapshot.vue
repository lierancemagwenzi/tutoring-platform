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
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Performance Snapshot</h2>

        <div v-if="!snapshot || snapshot.activities_completed === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <ChartPieIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">Once students complete work, their performance will show here.</p>
        </div>

        <template v-else>
            <div class="mt-6 flex items-center gap-6">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border-8 border-border">
                    <p class="text-body text-xl font-bold">{{ snapshot.average_percentage }}%</p>
                </div>
                <div>
                    <p class="text-body font-semibold">Average student score</p>
                    <p class="text-sm text-muted">Across {{ snapshot.activities_completed }} completed activities</p>
                </div>
            </div>

            <ul v-if="byType.length" class="mt-6 space-y-3 border-t border-border pt-4">
                <li v-for="[type, data] in byType" :key="type" class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-muted">
                        <component :is="blockRegistry[type]?.icon ?? ChartPieIcon" class="h-4 w-4 text-muted" />
                        {{ blockRegistry[type]?.label ?? type }}
                        <span class="text-xs text-muted">({{ data.count }})</span>
                    </span>
                    <span class="text-body font-semibold">{{ data.average_percentage }}%</span>
                </li>
            </ul>
        </template>

        <div v-if="snapshot?.session_attendance_rate !== null && snapshot?.session_attendance_rate !== undefined" class="mt-4 border-t border-border pt-4">
            <p class="flex items-center justify-between text-sm">
                <span class="text-muted">Session attendance</span>
                <span class="text-body font-semibold">{{ snapshot.session_attendance_rate }}%</span>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({
    stats: { type: Object, default: null },
})

const auth = useAuthStore()
const firstName = computed(() => auth.user?.first_name ?? 'there')

const greeting = computed(() => {
    const hour = new Date().getHours()
    if (hour < 12) return 'Good morning'
    if (hour < 18) return 'Good afternoon'
    return 'Good evening'
})

const items = computed(() => [
    { label: 'Sessions today', value: props.stats?.sessions_today ?? 0 },
    { label: 'Pending activities', value: props.stats?.pending_activities ?? 0 },
    { label: 'Needs revision', value: props.stats?.awaiting_revision ?? 0 },
    {
        label: 'Average score',
        value: props.stats?.average_percentage !== null && props.stats?.average_percentage !== undefined ? `${props.stats.average_percentage}%` : '—',
    },
])
</script>

<template>
    <div class="from-panel-start to-panel-end rounded-2xl bg-gradient-to-b px-8 py-8">
        <p class="text-lg font-bold text-white">{{ greeting }}, {{ firstName }}</p>
        <p class="mt-1 text-sm text-white/70">Here's what's happening with your learning today.</p>

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div v-for="item in items" :key="item.label" class="rounded-xl border border-white/10 bg-white/10 p-4">
                <p class="text-xl font-bold text-white">{{ item.value }}</p>
                <p class="mt-1 text-xs text-white/70">{{ item.label }}</p>
            </div>
        </div>
    </div>
</template>

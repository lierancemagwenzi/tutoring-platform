<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const firstName = computed(() => auth.user?.first_name ?? 'there')

const greeting = computed(() => {
    const hour = new Date().getHours()
    if (hour < 12) return 'Good morning'
    if (hour < 18) return 'Good afternoon'
    return 'Good evening'
})
</script>

<template>
    <div class="from-panel-start to-panel-end rounded-2xl bg-gradient-to-b px-8 py-8">
        <p class="text-lg font-bold text-white">{{ greeting }}, {{ firstName }}.</p>
        <p class="mt-1 text-sm text-white/70">Here's today's summary of your teaching workspace.</p>
    </div>
</template>

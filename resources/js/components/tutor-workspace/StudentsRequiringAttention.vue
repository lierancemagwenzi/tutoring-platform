<script setup>
import { CheckCircleIcon } from '@heroicons/vue/24/outline'

defineProps({
    students: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Students Requiring Attention</h2>

        <div v-if="students.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <CheckCircleIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">No students require attention right now.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-border">
            <li v-for="entry in students" :key="entry.student_id" class="py-3 first:pt-0 last:pb-0">
                <p class="text-body font-semibold">{{ entry.student_name }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <component
                        :is="reason.url ? 'router-link' : 'span'"
                        v-for="(reason, index) in entry.reasons"
                        :key="index"
                        :to="reason.url"
                        class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600"
                        :class="reason.url ? 'hover:bg-red-100' : ''"
                    >
                        {{ reason.label }}
                    </component>
                </div>
            </li>
        </ul>
    </div>
</template>

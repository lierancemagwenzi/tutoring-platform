<script setup>
import { computed } from 'vue'

const props = defineProps({
    course: { type: Object, required: true },
})

const rows = computed(() => [
    { label: 'Total Modules', value: props.course.modules_count },
    { label: 'Total Learning Activities', value: props.course.activities_count },
    { label: 'Total Assessments', value: props.course.assessments_count },
    {
        label: 'Estimated Duration',
        value: props.course.estimated_duration_minutes ? `${Math.round(props.course.estimated_duration_minutes / 60)}h` : '—',
    },
    { label: 'Language', value: props.course.language ?? '—' },
    { label: 'Difficulty', value: props.course.difficulty ?? '—', capitalize: true },
    { label: 'Grade', value: props.course.grade?.name ?? '—' },
])
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Course Information</h2>
        <dl class="mt-3 space-y-2 text-sm">
            <div v-for="row in rows" :key="row.label" class="flex justify-between">
                <dt class="text-gray-500">{{ row.label }}</dt>
                <dd class="text-ink font-semibold" :class="row.capitalize ? 'capitalize' : ''">{{ row.value }}</dd>
            </div>
        </dl>
    </div>
</template>

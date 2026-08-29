<script setup>
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

defineProps({
    module: { type: Object, required: true },
    expanded: { type: Boolean, default: false },
})

defineEmits(['toggle'])
</script>

<template>
    <div class="rounded-xl border border-gray-100">
        <button type="button" class="flex w-full items-center gap-3 px-4 py-3.5 text-left" @click="$emit('toggle')">
            <component :is="expanded ? ChevronDownIcon : ChevronRightIcon" class="h-4 w-4 shrink-0 text-gray-400" />
            <div class="min-w-0 flex-1">
                <p class="text-ink font-semibold">{{ module.title }}</p>
                <p v-if="module.description" class="truncate text-sm text-gray-500">{{ module.description }}</p>
            </div>
            <span class="shrink-0 text-xs text-gray-400">
                {{ module.activities_count }} activities &middot; {{ module.assessments_count }} assessments
            </span>
        </button>

        <div v-if="expanded" class="space-y-1 border-t border-gray-100 px-4 py-3">
            <div v-if="module.activities.length === 0 && module.assessments.length === 0" class="py-1.5 text-sm text-gray-400 italic">
                No content in this module yet.
            </div>
            <div v-for="activity in module.activities" :key="`activity-${activity.id}`" class="flex items-center justify-between py-1.5 text-sm">
                <span class="text-gray-700">{{ activity.title }}</span>
                <span class="text-xs text-gray-400 capitalize">{{ activity.type.replace('_', ' ') }}</span>
            </div>
            <div
                v-for="assessment in module.assessments"
                :key="`assessment-${assessment.id}`"
                class="flex items-center justify-between py-1.5 text-sm"
            >
                <span class="text-gray-700">{{ assessment.title }}</span>
                <span class="text-xs text-gray-400 capitalize">{{ assessment.assessment_type.replace('_', ' ') }}</span>
            </div>
        </div>
    </div>
</template>

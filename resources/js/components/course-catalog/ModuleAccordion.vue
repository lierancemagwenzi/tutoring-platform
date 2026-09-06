<script setup>
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

defineProps({
    module: { type: Object, required: true },
    expanded: { type: Boolean, default: false },
})

defineEmits(['toggle'])
</script>

<template>
    <div class="border-border rounded-xl border">
        <button type="button" class="flex w-full items-center gap-3 px-4 py-3.5 text-left" @click="$emit('toggle')">
            <component :is="expanded ? ChevronDownIcon : ChevronRightIcon" class="text-muted h-4 w-4 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-body font-semibold">{{ module.title }}</p>
                <p v-if="module.description" class="text-muted truncate text-sm">{{ module.description }}</p>
            </div>
            <span class="text-muted shrink-0 text-xs">
                {{ module.activities_count }} activities &middot; {{ module.assessments_count }} assessments
            </span>
        </button>

        <div v-if="expanded" class="border-border space-y-1 border-t px-4 py-3">
            <div v-if="module.activities.length === 0 && module.assessments.length === 0" class="text-muted py-1.5 text-sm italic">
                No content in this module yet.
            </div>
            <div v-for="activity in module.activities" :key="`activity-${activity.id}`" class="flex items-center justify-between py-1.5 text-sm">
                <span class="text-body">{{ activity.title }}</span>
                <span class="text-muted text-xs capitalize">{{ activity.type.replace('_', ' ') }}</span>
            </div>
            <div
                v-for="assessment in module.assessments"
                :key="`assessment-${assessment.id}`"
                class="flex items-center justify-between py-1.5 text-sm"
            >
                <span class="text-body">{{ assessment.title }}</span>
                <span class="text-muted text-xs capitalize">{{ assessment.assessment_type.replace('_', ' ') }}</span>
            </div>
        </div>
    </div>
</template>

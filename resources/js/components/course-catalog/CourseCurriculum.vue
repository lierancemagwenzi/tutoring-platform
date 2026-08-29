<script setup>
import { ref } from 'vue'
import ModuleAccordion from './ModuleAccordion.vue'

const props = defineProps({
    course: { type: Object, required: true },
})

const expandedModules = ref(new Set())

function toggle(moduleId) {
    if (expandedModules.value.has(moduleId)) {
        expandedModules.value.delete(moduleId)
    } else {
        expandedModules.value.add(moduleId)
    }
    expandedModules.value = new Set(expandedModules.value)
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Course Curriculum</h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ course.modules_count }} modules &middot; {{ course.activities_count }} activities &middot; {{ course.assessments_count }} assessments
        </p>

        <div v-if="course.modules.length === 0" class="mt-4 text-sm text-gray-400 italic">No modules published yet.</div>

        <div v-else class="mt-4 space-y-2">
            <ModuleAccordion
                v-for="module in course.modules"
                :key="module.id"
                :module="module"
                :expanded="expandedModules.has(module.id)"
                @toggle="toggle(module.id)"
            />
        </div>
    </div>
</template>

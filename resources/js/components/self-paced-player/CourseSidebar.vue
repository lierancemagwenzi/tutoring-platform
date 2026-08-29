<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'
import StatusPill from '../common/StatusPill.vue'

// Only module-level locking exists in the backend (see ModuleProgressService)
// — items within an unlocked module are always individually accessible
// regardless of order. So an item's pill is 'locked' only when its module
// is locked; otherwise it's 'completed' or 'available', never a
// client-invented "locked" state for an individual item.
const props = defineProps({
    course: { type: Object, required: true },
    open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const route = useRoute()

const expandedModuleIds = ref(new Set(props.course.modules.filter((module) => module.state !== 'locked').map((module) => module.id)))

function isExpanded(moduleId) {
    return expandedModuleIds.value.has(moduleId)
}

function toggle(moduleId) {
    const next = new Set(expandedModuleIds.value)
    next.has(moduleId) ? next.delete(moduleId) : next.add(moduleId)
    expandedModuleIds.value = next
}

function moduleItems(module) {
    return [
        ...module.activities.map((activity) => ({ ...activity, kind: 'activity' })),
        ...module.assessments.map((assessment) => ({ ...assessment, kind: 'assessment' })),
    ].sort((a, b) => a.position - b.position)
}

function itemDone(item) {
    return item.kind === 'activity' ? item.completed : item.passed
}

function itemStatus(module, item) {
    if (module.state === 'locked') return 'locked'
    return itemDone(item) ? 'completed' : 'available'
}

function itemRoute(item) {
    return item.kind === 'activity'
        ? { name: 'student.self-paced.activity', params: { courseId: props.course.id, activityId: item.id } }
        : { name: 'student.self-paced.assessment', params: { courseId: props.course.id, assessmentId: item.id } }
}

function isActive(item) {
    if (item.kind === 'activity') {
        return route.name === 'student.self-paced.activity' && Number(route.params.activityId) === item.id
    }

    return route.name === 'student.self-paced.assessment' && Number(route.params.assessmentId) === item.id
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="emit('close')" />

    <aside
        class="fixed inset-y-0 left-0 z-40 w-80 shrink-0 overflow-y-auto border-r border-gray-100 bg-white transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="p-4">
            <p class="text-ink px-1 text-sm font-bold">Curriculum</p>

            <div v-for="module in course.modules" :key="module.id" class="mt-3 rounded-xl border border-gray-100">
                <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left" @click="toggle(module.id)">
                    <span class="text-ink truncate text-sm font-semibold">{{ module.title }}</span>
                    <span class="flex shrink-0 items-center gap-2">
                        <StatusPill :status="module.state" />
                        <ChevronDownIcon class="h-4 w-4 text-gray-400 transition-transform" :class="isExpanded(module.id) ? 'rotate-180' : ''" />
                    </span>
                </button>

                <ul v-if="isExpanded(module.id)" class="space-y-1 px-3 pb-3">
                    <li v-for="item in moduleItems(module)" :key="`${item.kind}-${item.id}`">
                        <component
                            :is="module.state === 'locked' ? 'div' : 'router-link'"
                            :to="module.state === 'locked' ? undefined : itemRoute(item)"
                            class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-xs"
                            :class="[
                                module.state === 'locked' ? 'cursor-not-allowed text-gray-300' : 'text-gray-600 hover:bg-gray-50',
                                isActive(item) ? 'bg-accent/10 text-accent font-semibold' : '',
                            ]"
                            @click="module.state !== 'locked' && emit('close')"
                        >
                            <span class="truncate">{{ item.title }}</span>
                            <StatusPill :status="itemStatus(module, item)" />
                        </component>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</template>

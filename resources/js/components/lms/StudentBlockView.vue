<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'

const props = defineProps({
    block: { type: Object, required: true },
})

const route = useRoute()
const router = useRouter()

const label = computed(() => blockRegistry[props.block.block_type]?.label ?? props.block.block_type)
const summary = computed(() => blockRegistry[props.block.block_type]?.summary?.(props.block) ?? '')

function open() {
    router.push(`/student/bookings/${route.params.id}/lesson-blocks/${props.block.id}`)
}
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center gap-3 rounded-2xl bg-white p-5 text-left shadow-sm transition hover:bg-gray-50"
        @click="open"
    >
        <span class="bg-accent/10 text-accent flex h-9 w-9 shrink-0 items-center justify-center rounded-full">
            <component :is="blockRegistry[block.block_type]?.icon" class="h-4 w-4" />
        </span>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <p class="text-ink font-bold">{{ block.title || label }}</p>
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ label }}</span>
            </div>
            <p v-if="summary" class="mt-1 truncate text-sm text-gray-500">{{ summary }}</p>
        </div>

        <ChevronRightIcon class="h-4 w-4 shrink-0 text-gray-400" />
    </button>
</template>

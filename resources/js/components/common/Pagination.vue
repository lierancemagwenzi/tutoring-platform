<script setup>
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    meta: { type: Object, required: true },
})

const emit = defineEmits(['change'])

function goToPage(page) {
    if (page < 1 || page > props.meta.last_page) {
        return
    }
    emit('change', page)
}
</script>

<template>
    <div v-if="meta.last_page > 1" class="mt-8 flex items-center justify-center gap-4">
        <button
            type="button"
            :disabled="meta.current_page <= 1"
            class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
            @click="goToPage(meta.current_page - 1)"
        >
            <ChevronLeftIcon class="h-5 w-5" />
        </button>
        <span class="text-sm text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button
            type="button"
            :disabled="meta.current_page >= meta.last_page"
            class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
            @click="goToPage(meta.current_page + 1)"
        >
            <ChevronRightIcon class="h-5 w-5" />
        </button>
    </div>
</template>

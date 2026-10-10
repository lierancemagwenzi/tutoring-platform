<script setup>
import { computed, ref } from 'vue'
import { SignalIcon } from '@heroicons/vue/24/outline'
import SpeedTestModal from './SpeedTestModal.vue'
import { HEALTH, lastResult } from '../../services/speedTest'

// A one-line network status (last result, or a prompt to test) with a
// button that opens the full speed test — for forms like scheduling a
// session, where the full panel would be too much.
const open = ref(false)
const result = ref(lastResult())

const STYLES = {
    great: 'bg-green-100 text-green-700',
    good: 'bg-green-100 text-green-700',
    fair: 'bg-amber-100 text-amber-700',
    poor: 'bg-red-100 text-red-700',
}

const testedAgo = computed(() => {
    if (!result.value?.testedAt) return ''
    const minutes = Math.round((Date.now() - new Date(result.value.testedAt).getTime()) / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes} min ago`
    const hours = Math.round(minutes / 60)
    return hours < 24 ? `${hours} h ago` : new Date(result.value.testedAt).toLocaleDateString()
})

function onModalToggle(isOpen) {
    open.value = isOpen
    // Pick up whatever the test in the dialog just measured.
    if (!isOpen) result.value = lastResult()
}
</script>

<template>
    <div class="border-border flex items-center justify-between gap-3 rounded-xl border px-4 py-3">
        <div class="flex min-w-0 items-center gap-2 text-sm">
            <SignalIcon class="text-accent h-4 w-4 shrink-0" />
            <template v-if="result?.health">
                <span class="text-body">Network health:</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="STYLES[result.health]">{{ HEALTH[result.health].label }}</span>
                <span class="text-muted truncate text-xs">tested {{ testedAgo }}</span>
            </template>
            <span v-else class="text-muted">Check your internet connection before the session.</span>
        </div>
        <button type="button" class="text-accent shrink-0 text-sm font-semibold" @click="open = true">
            {{ result?.health ? 'Test again' : 'Test now' }}
        </button>

        <SpeedTestModal :model-value="open" @update:model-value="onModalToggle" />
    </div>
</template>

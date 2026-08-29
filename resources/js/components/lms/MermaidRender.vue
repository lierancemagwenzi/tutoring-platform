<script setup>
import { ref, watch } from 'vue'
import mermaid from 'mermaid'

mermaid.initialize({ startOnLoad: false, securityLevel: 'strict' })

const props = defineProps({
    diagram: { type: String, default: '' },
})

const svg = ref('')
const error = ref('')
let renderCount = 0

async function render() {
    if (!props.diagram?.trim()) {
        svg.value = ''
        error.value = ''
        return
    }

    const id = `mermaid-${Date.now()}-${renderCount++}`

    try {
        const result = await mermaid.render(id, props.diagram)
        svg.value = result.svg
        error.value = ''
    } catch (err) {
        error.value = err.message ?? 'Invalid diagram syntax.'
        svg.value = ''
    }
}

watch(() => props.diagram, render, { immediate: true })
</script>

<template>
    <div>
        <div v-if="svg" class="overflow-x-auto" v-html="svg" />
        <p v-else-if="error" class="text-sm text-red-600">Invalid diagram: {{ error }}</p>
        <p v-else class="text-sm text-gray-400">Nothing to preview yet.</p>
    </div>
</template>

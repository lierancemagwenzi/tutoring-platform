<script setup>
import { ref, watch } from 'vue'
import mermaid from 'mermaid'

mermaid.initialize({ startOnLoad: false, securityLevel: 'strict' })

const props = defineProps({
    diagram: { type: String, default: '' },
})

const svg = ref('')
const error = ref('')

async function render() {
    if (!props.diagram?.trim()) {
        svg.value = ''
        error.value = ''
        return
    }

    // Unique across instances: a Content block can hold several diagrams that
    // all render in the same tick, and mermaid needs a distinct DOM id each.
    const id = `mermaid-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`

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
        <p v-else class="text-sm text-muted">Nothing to preview yet.</p>
    </div>
</template>

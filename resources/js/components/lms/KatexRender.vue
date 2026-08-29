<script setup>
import { computed } from 'vue'
import katex from 'katex'
import 'katex/dist/katex.min.css'

const props = defineProps({
    latex: { type: String, default: '' },
    displayMode: { type: Boolean, default: false },
})

const html = computed(() => {
    if (!props.latex) {
        return ''
    }

    try {
        return katex.renderToString(props.latex, { displayMode: props.displayMode, throwOnError: false })
    } catch (error) {
        return `<span class="text-red-600">Invalid LaTeX: ${error.message}</span>`
    }
})
</script>

<template>
    <div v-html="html" />
</template>

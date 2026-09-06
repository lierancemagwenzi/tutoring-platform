<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { configureH5pAjax, loadH5pAssets } from '../../services/h5p'
import { useH5pContentStore } from '../../stores/h5pContent'

const props = defineProps({
    contentId: { type: String, required: true },
    // Lets non-tutor contexts (e.g. a student viewing available session
    // content) fetch the player model through their own access-controlled
    // endpoint instead of the tutor-only one this defaults to.
    loadContent: { type: Function, default: null },
})

const emit = defineEmits(['initialized', 'xapi'])

const store = useH5pContentStore()
const container = ref(null)
const loading = ref(true)
const error = ref('')
let instance = null

async function render() {
    loading.value = true
    error.value = ''
    instance = null

    try {
        const model = props.loadContent ? await props.loadContent(props.contentId) : await store.fetchPlayerModel(props.contentId)

        // Must happen before loadH5pAssets(): h5p.js runs an auto-init tied
        // to document-ready that reads the bare global H5PIntegration
        // immediately as the script executes (the document is already
        // "ready" in an SPA, so jQuery fires it synchronously on load) —
        // setting this after the scripts load is too late.
        window.H5PIntegration = window.H5PIntegration || {}
        Object.assign(window.H5PIntegration, model.integration, {
            contents: { ...window.H5PIntegration.contents, ...model.integration.contents },
        })

        await loadH5pAssets(model.scripts, model.styles)
        configureH5pAjax()

        if (!container.value) return
        container.value.innerHTML = ''
        const target = document.createElement('div')
        target.className = 'h5p-content'
        target.dataset.contentId = props.contentId
        container.value.appendChild(target)

        window.H5P.init(container.value)
        instance = window.H5P.instances[window.H5P.instances.length - 1]
        instance?.on('xAPI', (event) => emit('xapi', event.data?.statement ?? event.data))

        loading.value = false
        emit('initialized')
    } catch (err) {
        loading.value = false
        error.value = err.message ?? 'Failed to load H5P content.'
    }
}

onMounted(render)
onBeforeUnmount(() => {
    if (container.value) container.value.innerHTML = ''
})

watch(() => props.contentId, render)
</script>

<template>
    <div>
        <p v-if="loading" class="text-sm text-muted">Loading H5P preview…</p>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <div ref="container"></div>
    </div>
</template>

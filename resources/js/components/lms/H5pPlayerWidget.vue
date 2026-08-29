<script setup>
import { onMounted, ref, watch } from 'vue'
import { defineElements } from '@lumieducation/h5p-webcomponents'
import { useH5pContentStore } from '../../stores/h5pContent'

defineElements()

const props = defineProps({
    contentId: { type: String, required: true },
    // Lets non-tutor contexts (e.g. a student viewing available session
    // content) fetch the player model through their own access-controlled
    // endpoint instead of the tutor-only one this defaults to.
    loadContent: { type: Function, default: null },
})

const emit = defineEmits(['initialized', 'xapi'])

const store = useH5pContentStore()
const playerEl = ref(null)
const loading = ref(true)

async function loadContentCallback(contentId) {
    return props.loadContent ? props.loadContent(contentId) : store.fetchPlayerModel(contentId)
}

onMounted(() => {
    const el = playerEl.value
    el.addEventListener('initialized', () => {
        loading.value = false
        emit('initialized')
    })
    // The h5p-webcomponents player fires this on every interaction/completion
    // xAPI statement — callers that care about attempt results (as opposed
    // to a plain tutor preview) listen for it via the xapi event.
    el.addEventListener('xAPI', (event) => {
        emit('xapi', event.detail ?? event.data ?? event)
    })
    // Set before loadContentCallback: assigning loadContentCallback triggers
    // an immediate render() using whatever contentId is set at that moment.
    el.contentId = props.contentId
    el.loadContentCallback = loadContentCallback
})

watch(
    () => props.contentId,
    (contentId) => {
        if (playerEl.value) {
            playerEl.value.contentId = contentId
        }
    },
)
</script>

<template>
    <div>
        <p v-if="loading" class="text-sm text-gray-400">Loading H5P preview…</p>
        <h5p-player ref="playerEl" />
    </div>
</template>

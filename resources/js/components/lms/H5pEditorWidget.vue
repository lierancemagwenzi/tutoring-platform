<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { defineElements } from '@lumieducation/h5p-webcomponents'
import { useH5pContentStore } from '../../stores/h5pContent'

// Registers the <h5p-editor>/<h5p-player> custom elements globally; safe to
// call more than once, defineElements() no-ops if already registered.
defineElements()

const props = defineProps({
    contentId: { type: String, default: null },
})

const emit = defineEmits(['saved', 'save-error'])

const store = useH5pContentStore()
const editorEl = ref(null)
const loading = ref(true)
const error = ref('')

async function loadContentCallback(contentId) {
    return contentId ? store.fetchEditorModel(contentId) : store.fetchNewEditorModel()
}

async function saveContentCallback(contentId, requestBody) {
    const result = await store.saveContent(contentId || props.contentId, requestBody)
    return { contentId: result.id, metadata: result.metadata }
}

function onSaved(event) {
    emit('saved', event.detail)
}

function onSaveError(event) {
    error.value = event.detail?.message ?? 'Failed to save H5P content.'
    emit('save-error', event.detail)
}

onMounted(() => {
    const el = editorEl.value
    el.addEventListener('saved', onSaved)
    el.addEventListener('save-error', onSaveError)
    el.addEventListener('validation-error', onSaveError)
    el.addEventListener('editorloaded', () => {
        loading.value = false
    })
    // 'new' is the sentinel content-id H5PEditorComponent expects for
    // not-yet-saved content — render() early-returns on a falsy contentId,
    // and internally treats 'new' as "call loadContentCallback(undefined)".
    // Must be set before loadContentCallback, since assigning
    // loadContentCallback synchronously triggers the initial render() using
    // whatever contentId is already set.
    el.contentId = props.contentId || 'new'
    el.saveContentCallback = saveContentCallback
    el.loadContentCallback = loadContentCallback
})

onBeforeUnmount(() => {
    const el = editorEl.value
    el?.removeEventListener('saved', onSaved)
    el?.removeEventListener('save-error', onSaveError)
    el?.removeEventListener('validation-error', onSaveError)
})

async function save() {
    return editorEl.value.save()
}

defineExpose({ save })
</script>

<template>
    <div>
        <p v-if="loading" class="text-sm text-gray-400">Loading H5P editor…</p>
        <p v-if="error" class="mb-2 text-sm text-red-600">{{ error }}</p>
        <h5p-editor ref="editorEl" />
    </div>
</template>

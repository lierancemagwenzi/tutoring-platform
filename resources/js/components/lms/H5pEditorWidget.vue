<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { authBootstrapScriptUrl, configureH5pAjax, loadH5pAssets } from '../../services/h5p'
import { useH5pContentStore } from '../../stores/h5pContent'

const props = defineProps({
    contentId: { type: String, default: null },
})

const emit = defineEmits(['saved', 'save-error'])

const store = useH5pContentStore()
// H5PEditor.Editor replaces `target` in the DOM with its own <iframe> (see
// ns.Editor's `$iframe.replaceAll(replace)`) rather than appending into it —
// `target` itself ends up detached, so `wrapper` (which stays put) is what
// cleanup and the loading/error overlay actually rely on.
const wrapper = ref(null)
const target = ref(null)
const loading = ref(true)
const error = ref('')
let editor = null

async function mount() {
    loading.value = true
    error.value = ''

    try {
        const model = props.contentId ? await store.fetchEditorModel(props.contentId) : await store.fetchNewEditorModel()

        // The editor iframe (see below) makes its first ajax call the
        // instant its own H5P.jQuery is ready — before we'd get a chance to
        // call configureH5pAjax() against its window — so its auth header
        // has to be in place before any of its scripts even load.
        const bootstrap = authBootstrapScriptUrl()
        if (bootstrap) {
            model.integration.editor.assets.js = [bootstrap, ...model.integration.editor.assets.js]
        }

        // Must happen before loadH5pAssets(): h5p.js runs an auto-init tied
        // to document-ready that reads the bare global H5PIntegration
        // immediately as the script executes (the document is already
        // "ready" in an SPA, so jQuery fires it synchronously on load) —
        // setting this after the scripts load is too late.
        window.H5PIntegration = window.H5PIntegration || {}
        Object.assign(window.H5PIntegration, model.integration)

        // H5PEditor.Editor renders into its own <iframe> with its own JS
        // realm, so this asset list (core + editor combined — see
        // App\Services\H5p\H5PService::editorSettings()) needs loading on
        // the parent page too: the Editor constructor itself runs here and
        // reads window.H5P/window.H5PEditor before the iframe exists.
        await loadH5pAssets(model.integration.editor.assets.js, model.integration.editor.assets.css)
        configureH5pAjax()

        const ns = window.H5PEditor
        ns.$ = window.H5P.jQuery
        ns.basePath = model.integration.editor.libraryUrl
        ns.fileIcon = model.integration.editor.fileIcon
        ns.ajaxPath = model.integration.editor.ajaxPath
        ns.filesPath = model.integration.editor.filesPath
        ns.apiVersion = model.integration.editor.apiVersion
        ns.contentLanguage = model.integration.editor.language ?? 'en'
        ns.copyrightSemantics = model.integration.editor.copyrightSemantics
        ns.metadataSemantics = model.integration.editor.metadataSemantics
        ns.assets = model.integration.editor.assets
        ns.baseUrl = ''
        ns.enableContentHub = model.integration.editor.enableContentHub

        if (!target.value) return

        editor = new ns.Editor(model.library ?? '', JSON.stringify(model.params ?? {}), target.value)

        // The iframe ns.Editor just created is a separate window with its
        // own empty global scope — it loads the same core/editor scripts
        // fresh (written into its <head>), and those reference the bare
        // global H5PIntegration too, so it needs its own copy. This must
        // happen synchronously right after construction: populateIframe()
        // already ran and wrote the <script> tags, but they're fetched
        // asynchronously, so there's a window before they execute.
        const iframe = wrapper.value?.querySelector('iframe.h5p-editor-iframe')
        if (iframe) {
            iframe.contentWindow.H5PIntegration = window.H5PIntegration
        }

        loading.value = false
    } catch (err) {
        loading.value = false
        error.value = err.message ?? 'Failed to load H5P editor.'
    }
}

async function save() {
    if (!editor) return

    return new Promise((resolve, reject) => {
        editor.getContent(
            async (content) => {
                try {
                    // getContent() hands us { library, title, params } where
                    // params is a JSON string of { params, metadata } (see
                    // Editor.prototype.getParams()) — title is the separately
                    // validated main-title field, folded into metadata here.
                    const { params, metadata } = JSON.parse(content.params)
                    const result = await store.saveContent(props.contentId, {
                        library: content.library,
                        params: {
                            params,
                            metadata: { ...metadata, title: content.title },
                        },
                    })
                    emit('saved', result)
                    resolve(result)
                } catch (err) {
                    error.value = err.response?.data?.message ?? 'Failed to save H5P content.'
                    emit('save-error', err)
                    reject(err)
                }
            },
            (errorCode) => {
                error.value = `Could not save: ${errorCode}`
                emit('save-error', errorCode)
                reject(new Error(errorCode))
            },
        )
    })
}

onMounted(mount)
onBeforeUnmount(() => {
    if (wrapper.value) wrapper.value.innerHTML = ''
})

defineExpose({ save })
</script>

<template>
    <div>
        <p v-if="loading" class="text-sm text-gray-400">Loading H5P editor…</p>
        <p v-if="error" class="mb-2 text-sm text-red-600">{{ error }}</p>
        <div ref="wrapper">
            <div ref="target"></div>
        </div>
    </div>
</template>

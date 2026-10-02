<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { PlusIcon } from '@heroicons/vue/24/outline'
import { useCoursesStore } from '../../../stores/courses'
import { useMediaItemsStore } from '../../../stores/mediaItems'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import ContentItemsEditor from '../../../components/lms/content/ContentItemsEditor.vue'
import MediaItemFormModal from '../../../components/lms/content/MediaItemFormModal.vue'
import { CONTENT_ITEM_TYPES, contentItemsSnapshot } from '../../../lms/contentItemTypes'

// Editor page for a Tutor-Led Content block: an ordered list of any number
// of text, maths, diagram and file items. Files upload immediately as this
// block's media items (they need a stored row to point at) and join the
// list, which is then saved with everything else via "Save".
const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const route = useRoute()
const router = useRouter()
const store = useCoursesStore()
const mediaStore = useMediaItemsStore()
const blockId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const savedMessage = ref('')
const block = ref(null)
const title = ref('')
const status = ref('draft')
const items = ref([])
const mediaById = ref({})
const savedSnapshot = ref('')
const addMenuOpen = ref(false)
const editor = ref(null)

const mediaModalOpen = ref(false)
const editingMedia = ref(null)
// Where a newly uploaded file goes in the list (null = at the end).
const insertAt = ref(null)

function snapshot() {
    return JSON.stringify({ title: title.value, status: status.value, items: contentItemsSnapshot(items.value) })
}
const dirty = computed(() => !loading.value && snapshot() !== savedSnapshot.value)

function setMedia(list) {
    mediaById.value = Object.fromEntries((list ?? []).map((media) => [media.id, media]))
}

onMounted(async () => {
    try {
        block.value = await store.fetchBlock(blockId.value)
        title.value = block.value.title ?? ''
        status.value = block.value.status
        // A plain deep copy — block.value is a reactive proxy, which structuredClone can't clone.
        items.value = JSON.parse(JSON.stringify(block.value.content?.items ?? []))
        setMedia(block.value.media_items)
    } catch (err) {
        error.value = err.response?.data?.message ?? 'This block could not be loaded.'
    } finally {
        loading.value = false
        savedSnapshot.value = snapshot()
    }
})

function addItem(type) {
    addMenuOpen.value = false
    editor.value?.addItem(type)
}

function openAddMedia(index) {
    insertAt.value = index
    editingMedia.value = null
    mediaModalOpen.value = true
}

function openEditMedia(item) {
    editingMedia.value = mediaById.value[item.media_item_id]
    mediaModalOpen.value = true
}

function saveMedia(payload, existing) {
    return existing ? mediaStore.updateMediaItem(existing.id, payload) : mediaStore.createMediaItem(blockId.value, payload)
}

function onMediaSaved(media) {
    mediaById.value = { ...mediaById.value, [media.id]: media }
    if (!editingMedia.value) editor.value?.addMediaItem(media.id, insertAt.value)
}

async function save() {
    saving.value = true
    error.value = ''
    savedMessage.value = ''

    try {
        const saved = await store.updateBlock(blockId.value, {
            block_type: 'content',
            title: title.value || null,
            status: status.value,
            items: items.value.map((item) => ({ ...item })),
        })
        block.value = saved
        setMedia(saved.media_items)
        savedSnapshot.value = snapshot()
        savedMessage.value = 'Saved.'
        setTimeout(() => (savedMessage.value = ''), 2500)
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors
            ? 'Please check the highlighted items: ' + [...new Set(Object.values(errors).flat())].join(' ')
            : (err.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

function onBeforeUnload(event) {
    if (dirty.value) {
        event.preventDefault()
        event.returnValue = ''
    }
}

onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))
onBeforeRouteLeave(() => !dirty.value || confirm('You have unsaved changes. Leave without saving?'))
</script>

<template>
    <div class="flex min-h-full flex-col px-8 pt-8">
        <button type="button" class="text-accent self-start text-sm font-semibold" @click="router.back()">&larr; Back to Lesson Builder</button>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="!block" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

        <template v-else>
            <h1 class="text-body mt-1 text-2xl font-bold">Content Block</h1>
            <p class="mt-1 text-muted">Add as many text, maths, diagram and file items as you need, in any order.</p>

            <div class="mt-6 grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-[1fr_12rem]">
                <FloatingLabelInput id="content-block-title" v-model="title" label="Block title (optional)" />
                <SelectInput id="content-block-status" v-model="status" label="Status" :options="STATUS_OPTIONS" />
            </div>

            <p v-if="error" class="mt-6 max-w-3xl rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <div class="mt-8 mb-8 max-w-3xl">
                <ContentItemsEditor ref="editor" v-model="items" :media-by-id="mediaById" @add-media="openAddMedia" @edit-media="openEditMedia" />
            </div>

            <!-- Sticky action bar: add at the end + save, always in reach on long blocks. -->
            <div class="bg-card border-border sticky bottom-0 z-20 -mx-8 mt-auto border-t px-8 py-3 shadow-elevated">
                <div class="flex max-w-3xl items-center justify-between gap-3">
                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-full border border-border px-4 py-2.5 text-sm font-semibold text-body"
                            @click="addMenuOpen = !addMenuOpen"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Add item
                        </button>
                        <div v-if="addMenuOpen" class="shadow-popover absolute bottom-full left-0 mb-2 w-48 rounded-xl bg-card py-1">
                            <button
                                v-for="(meta, type) in CONTENT_ITEM_TYPES"
                                :key="type"
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:brightness-95"
                                @click="addItem(type)"
                            >
                                <component :is="meta.icon" class="text-accent h-4 w-4" />
                                {{ meta.label }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span v-if="savedMessage" class="text-sm font-semibold text-green-600">{{ savedMessage }}</span>
                        <span v-else-if="dirty" class="text-muted text-sm">Unsaved changes</span>
                        <button
                            type="button"
                            :disabled="saving"
                            class="bg-amber rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="save"
                        >
                            {{ saving ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <MediaItemFormModal v-model="mediaModalOpen" :save="saveMedia" :item="editingMedia" @saved="onMediaSaved" />
    </div>
</template>

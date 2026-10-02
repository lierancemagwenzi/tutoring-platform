<script setup>
import draggable from 'vuedraggable'
import { ArrowDownIcon, ArrowUpIcon, Bars3Icon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import TextareaInput from '../../forms/TextareaInput.vue'
import SelectInput from '../../forms/SelectInput.vue'
import RichTextEditor from '../RichTextEditor.vue'
import KatexRender from '../KatexRender.vue'
import MermaidRender from '../MermaidRender.vue'
import MediaItemView from '../MediaItemView.vue'
import { MERMAID_TEMPLATES, MERMAID_TEMPLATE_OPTIONS } from '../../../lms/mermaidTemplates'
import { mediaTypeMeta } from '../../../lms/mediaTypes'
import { CONTENT_ITEM_TYPES } from '../../../lms/contentItemTypes'

// The ordered item list of a Content block (Tutor-Led) or Content activity
// (Self-Paced). Text/maths/diagram items are edited inline. Files need an
// uploaded row to point at, which differs per host — so "add/edit file" is
// emitted for the host to handle, and the host calls addMediaItem() with the
// saved row's id.
const items = defineModel({ type: Array, required: true })

defineProps({
    // { [fileRowId]: { id, media_type, title, url, … } }
    mediaById: { type: Object, required: true },
})

const emit = defineEmits(['add-media', 'edit-media'])

function newId() {
    return globalThis.crypto?.randomUUID?.() ?? `item-${Date.now()}-${Math.random().toString(36).slice(2)}`
}

/**
 * Add an item of `type` at `index` (null = at the end). A file item is
 * handed to the host to upload first — see addMediaItem().
 */
function addItem(type, index = null) {
    if (type === 'media') {
        emit('add-media', index)
        return
    }

    const item = {
        id: newId(),
        type,
        ...(type === 'rich_text' ? { html: '', json: null } : {}),
        ...(type === 'math' ? { latex: '', display_mode: true } : {}),
        ...(type === 'mermaid' ? { diagram: MERMAID_TEMPLATES.flowchart } : {}),
    }

    if (index === null) items.value.push(item)
    else items.value.splice(index, 0, item)
}

function addMediaItem(mediaId, index = null) {
    const item = { id: newId(), type: 'media', media_item_id: mediaId }
    if (index === null) items.value.push(item)
    else items.value.splice(index, 0, item)
}

defineExpose({ addItem, addMediaItem })

function move(index, direction) {
    const target = index + direction
    if (target < 0 || target >= items.value.length) return
    const [item] = items.value.splice(index, 1)
    items.value.splice(target, 0, item)
}

function isBlankText(html) {
    return !html?.replace(/<[^>]*>/g, '').trim()
}

function removeItem(index) {
    const item = items.value[index]
    const isEmpty = (item.type === 'rich_text' && isBlankText(item.html)) || (item.type === 'math' && !item.latex?.trim())
    if (!isEmpty && !confirm(`Remove this ${CONTENT_ITEM_TYPES[item.type].label.toLowerCase()} item?`)) return
    items.value.splice(index, 1)
}

function insertTemplate(item, key) {
    if (key) item.diagram = MERMAID_TEMPLATES[key]
}

function itemProblem(item, mediaById) {
    if (item.type === 'rich_text' && isBlankText(item.html)) return 'This text is empty.'
    if (item.type === 'math' && !item.latex?.trim()) return 'Enter a formula.'
    if (item.type === 'mermaid' && !item.diagram?.trim()) return 'Enter a diagram.'
    if (item.type === 'media' && !mediaById[item.media_item_id]) return 'This file is missing — remove it and upload again.'
    return null
}
</script>

<template>
    <div v-if="items.length === 0" class="rounded-2xl border-2 border-dashed border-border p-10 text-center">
        <p class="text-muted">This is empty. Add your first item.</p>
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            <button
                v-for="(meta, type) in CONTENT_ITEM_TYPES"
                :key="type"
                type="button"
                class="flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-semibold text-body hover:border-accent"
                @click="addItem(type)"
            >
                <component :is="meta.icon" class="text-accent h-4 w-4" />
                {{ meta.label }}
            </button>
        </div>
    </div>

    <draggable v-else v-model="items" item-key="id" handle=".drag-handle" class="space-y-4">
        <template #item="{ element: item, index }">
            <div class="rounded-2xl bg-card p-5 shadow-elevated" :class="itemProblem(item, mediaById) ? 'ring-1 ring-red-200' : ''">
                <div class="flex items-center gap-3">
                    <span class="drag-handle cursor-grab text-muted" title="Drag to reorder"><Bars3Icon class="h-5 w-5" /></span>
                    <span class="bg-accent/10 text-accent flex h-8 w-8 items-center justify-center rounded-full">
                        <component :is="CONTENT_ITEM_TYPES[item.type].icon" class="h-4 w-4" />
                    </span>
                    <p class="text-body flex-1 text-sm font-bold">{{ CONTENT_ITEM_TYPES[item.type].label }}</p>
                    <button type="button" class="text-muted hover:text-body disabled:opacity-30" :disabled="index === 0" title="Move up" @click="move(index, -1)">
                        <ArrowUpIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="text-muted hover:text-body disabled:opacity-30"
                        :disabled="index === items.length - 1"
                        title="Move down"
                        @click="move(index, 1)"
                    >
                        <ArrowDownIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="text-red-500 hover:text-red-700" title="Remove" @click="removeItem(index)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-4">
                    <RichTextEditor v-if="item.type === 'rich_text'" v-model="item.html" @update:json="(json) => (item.json = json)" />

                    <div v-else-if="item.type === 'math'" class="space-y-3">
                        <TextareaInput :id="`math-${item.id}`" v-model="item.latex" label="LaTeX formula" :rows="3" />
                        <label class="text-body flex items-center gap-2 text-sm">
                            <input v-model="item.display_mode" type="checkbox" class="text-accent h-4 w-4 rounded border-border" />
                            Display on its own line
                        </label>
                        <div v-if="item.latex?.trim()" class="rounded-xl border border-border bg-card-alt p-4">
                            <KatexRender :latex="item.latex" :display-mode="item.display_mode" />
                        </div>
                    </div>

                    <div v-else-if="item.type === 'mermaid'" class="space-y-3">
                        <SelectInput
                            :id="`mermaid-template-${item.id}`"
                            model-value=""
                            label="Start from a template"
                            :options="MERMAID_TEMPLATE_OPTIONS"
                            @update:model-value="(key) => insertTemplate(item, key)"
                        />
                        <TextareaInput :id="`mermaid-${item.id}`" v-model="item.diagram" label="Mermaid syntax" :rows="6" />
                        <div class="rounded-xl border border-border bg-card-alt p-4">
                            <MermaidRender :diagram="item.diagram" />
                        </div>
                    </div>

                    <div v-else-if="item.type === 'media' && mediaById[item.media_item_id]" class="space-y-3">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-muted">
                                {{ mediaTypeMeta(mediaById[item.media_item_id].media_type).label }} ·
                                <span class="text-body font-semibold">{{ mediaById[item.media_item_id].title || mediaById[item.media_item_id].original_name }}</span>
                            </span>
                            <button type="button" class="text-accent flex items-center gap-1 font-semibold" @click="emit('edit-media', item)">
                                <PencilSquareIcon class="h-4 w-4" />
                                Edit
                            </button>
                        </div>
                        <MediaItemView :item="mediaById[item.media_item_id]" />
                    </div>
                </div>

                <p v-if="itemProblem(item, mediaById)" class="mt-3 text-xs text-red-600">{{ itemProblem(item, mediaById) }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-3">
                    <span class="text-muted text-xs">Insert below:</span>
                    <button
                        v-for="(meta, type) in CONTENT_ITEM_TYPES"
                        :key="type"
                        type="button"
                        class="text-muted hover:text-accent flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold"
                        @click="addItem(type, index + 1)"
                    >
                        <component :is="meta.icon" class="h-3.5 w-3.5" />
                        {{ meta.label }}
                    </button>
                </div>
            </div>
        </template>
    </draggable>
</template>

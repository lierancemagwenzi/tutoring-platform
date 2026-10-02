<script setup>
import { computed } from 'vue'
import KatexRender from './KatexRender.vue'
import MermaidRender from './MermaidRender.vue'
import MediaItemView from './MediaItemView.vue'

// Renders a Content block's / Content activity's items in the Insider's
// order. File items point by id at `media` (the block's media_items, or the
// activity's attachments).
const props = defineProps({
    items: { type: Array, required: true },
    media: { type: Array, default: () => [] },
})

const mediaById = computed(() => Object.fromEntries(props.media.map((item) => [item.id, item])))
const visibleItems = computed(() => props.items.filter((item) => item.type !== 'media' || mediaById.value[item.media_item_id]))
</script>

<template>
    <p v-if="visibleItems.length === 0" class="text-sm text-gray-500">Nothing has been added here yet.</p>

    <div v-else class="space-y-6">
        <template v-for="item in visibleItems" :key="item.id">
            <div v-if="item.type === 'rich_text'" class="prose prose-sm max-w-none" v-html="item.html" />
            <KatexRender v-else-if="item.type === 'math'" :latex="item.latex" :display-mode="item.display_mode" />
            <MermaidRender v-else-if="item.type === 'mermaid'" :diagram="item.diagram" />
            <MediaItemView v-else-if="item.type === 'media'" :item="mediaById[item.media_item_id]" />
        </template>
    </div>
</template>

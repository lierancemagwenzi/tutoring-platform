<script setup>
import { computed, ref, watch } from 'vue'
import StudentBlockView from './StudentBlockView.vue'

const TAB_DEFINITIONS = [
    { key: 'content', label: 'Lesson Content', types: ['rich_text', 'media', 'math', 'mermaid', 'h5p'] },
    { key: 'quizzes', label: 'Quizzes', types: ['quiz'] },
    {
        key: 'activities',
        label: 'Activities',
        types: ['assignment', 'homework', 'practice', 'assessment', 'project', 'lab', 'reflection', 'reading', 'external_activity'],
    },
]

const props = defineProps({
    blocks: { type: Array, required: true },
})

const tabs = computed(() =>
    TAB_DEFINITIONS.map((tab) => ({
        ...tab,
        blocks: props.blocks.filter((block) => tab.types.includes(block.block_type)),
    })).filter((tab) => tab.blocks.length > 0),
)

const activeTab = ref(tabs.value[0]?.key ?? null)

watch(tabs, (newTabs) => {
    if (!newTabs.some((tab) => tab.key === activeTab.value)) {
        activeTab.value = newTabs[0]?.key ?? null
    }
})

const activeBlocks = computed(() => tabs.value.find((tab) => tab.key === activeTab.value)?.blocks ?? [])
</script>

<template>
    <div>
        <div v-if="tabs.length > 1" class="flex gap-1 border-b border-gray-100">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="border-b-2 px-4 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
                <span class="ml-1 text-xs font-normal text-gray-400">{{ tab.blocks.length }}</span>
            </button>
        </div>

        <div class="space-y-3" :class="tabs.length > 1 ? 'mt-4' : ''">
            <StudentBlockView v-for="block in activeBlocks" :key="block.id" :block="block" />
        </div>
    </div>
</template>

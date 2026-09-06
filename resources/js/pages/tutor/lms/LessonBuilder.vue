<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { Bars3Icon, DocumentDuplicateIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCoursesStore } from '../../../stores/courses'
import Modal from '../../../components/common/Modal.vue'
import { blockRegistry, blockTypeOptions } from '../../../lms/blockRegistry'

const route = useRoute()
const router = useRouter()
const store = useCoursesStore()
const lessonId = computed(() => Number(route.params.id))

const loading = ref(true)
const actionError = ref('')
const blocks = ref([])

const pickerOpen = ref(false)
const modalOpen = ref(false)

const ungroupedBlockTypes = computed(() => blockTypeOptions().filter((option) => !option.group))
const groupedBlockTypes = computed(() => {
    const groups = new Map()
    for (const option of blockTypeOptions()) {
        if (!option.group) {
            continue
        }
        if (!groups.has(option.group)) {
            groups.set(option.group, [])
        }
        groups.get(option.group).push(option)
    }
    return [...groups.entries()].map(([name, options]) => ({ name, options }))
})
const editingBlock = ref(null)
const creatingType = ref(null)

onMounted(async () => {
    blocks.value = await store.fetchBlocks(lessonId.value)
    loading.value = false
})

async function openCreate(type) {
    pickerOpen.value = false
    const meta = blockRegistry[type]

    if (meta.mode === 'page') {
        actionError.value = ''
        try {
            const created = await store.createBlock(lessonId.value, { block_type: type })
            router.push(meta.route(created))
        } catch (error) {
            actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
        }
        return
    }

    editingBlock.value = null
    creatingType.value = type
    modalOpen.value = true
}

function openEdit(block) {
    const meta = blockRegistry[block.block_type]

    if (meta.mode === 'page') {
        router.push(meta.route(block))
        return
    }

    editingBlock.value = block
    creatingType.value = block.block_type
    modalOpen.value = true
}

function onSaved(savedBlock) {
    const index = blocks.value.findIndex((block) => block.id === savedBlock.id)
    if (index !== -1) {
        blocks.value[index] = savedBlock
    } else {
        blocks.value.push(savedBlock)
    }
    modalOpen.value = false
}

async function duplicate(block) {
    actionError.value = ''
    try {
        const created = await store.duplicateBlock(block.id)
        const index = blocks.value.findIndex((entry) => entry.id === block.id)
        blocks.value.splice(index + 1, 0, created)
        await store.reorderBlocks(lessonId.value, blocks.value.map((entry) => entry.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function remove(block) {
    if (!confirm('Delete this block? This cannot be undone.')) {
        return
    }

    actionError.value = ''
    try {
        await store.deleteBlock(block.id)
        blocks.value = blocks.value.filter((entry) => entry.id !== block.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorder() {
    try {
        await store.reorderBlocks(lessonId.value, blocks.value.map((block) => block.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back to Lessons</button>
                <h1 class="text-body mt-1 text-2xl font-bold">Lesson Builder</h1>
                <p class="mt-1 text-muted">Add and arrange content blocks. Students will see them in this order.</p>
            </div>
            <div class="relative">
                <button
                    type="button"
                    class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                    @click="pickerOpen = !pickerOpen"
                >
                    <PlusIcon class="h-4 w-4" />
                    Add Block
                </button>
                <div v-if="pickerOpen" class="shadow-popover absolute top-full right-0 z-10 mt-2 max-h-96 w-56 overflow-y-auto rounded-xl bg-card py-1">
                    <button
                        v-for="option in ungroupedBlockTypes"
                        :key="option.type"
                        type="button"
                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:brightness-95"
                        @click="openCreate(option.type)"
                    >
                        <component :is="option.icon" class="text-accent h-4 w-4" />
                        {{ option.label }}
                    </button>

                    <div v-for="group in groupedBlockTypes" :key="group.name" class="border-t border-border py-1 first:border-t-0">
                        <p class="px-4 pt-1.5 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">{{ group.name }}</p>
                        <button
                            v-for="option in group.options"
                            :key="option.type"
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:brightness-95"
                            @click="openCreate(option.type)"
                        >
                            <component :is="option.icon" class="text-accent h-4 w-4" />
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="blocks.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-muted">No content blocks yet. Add your first block to get started.</p>
        </div>

        <draggable
            v-else
            v-model="blocks"
            item-key="id"
            handle=".drag-handle"
            class="mt-8 space-y-3"
            @end="onReorder"
        >
            <template #item="{ element: block }">
                <div class="flex items-start gap-4 rounded-2xl bg-card p-5 shadow-elevated">
                    <span class="drag-handle mt-1 cursor-grab text-muted">
                        <Bars3Icon class="h-5 w-5" />
                    </span>

                    <span class="bg-accent/10 text-accent mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full">
                        <component :is="blockRegistry[block.block_type]?.icon" class="h-4 w-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-body font-bold">{{ block.title || blockRegistry[block.block_type]?.label }}</p>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                                :class="block.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ block.status }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-sm text-muted">{{ blockRegistry[block.block_type]?.summary(block) }}</p>
                    </div>

                    <button type="button" class="shrink-0 text-muted hover:text-body" @click="openEdit(block)">
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="shrink-0 text-muted hover:text-body" @click="duplicate(block)">
                        <DocumentDuplicateIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="remove(block)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </draggable>

        <Modal
            v-model="modalOpen"
            :title="editingBlock ? `Edit ${blockRegistry[creatingType]?.label} Block` : `Add ${blockRegistry[creatingType]?.label} Block`"
        >
            <component
                :is="blockRegistry[creatingType]?.editor"
                v-if="modalOpen"
                :block="editingBlock"
                :lesson-id="lessonId"
                @saved="onSaved"
                @cancelled="modalOpen = false"
            />
        </Modal>
    </div>
</template>

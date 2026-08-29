<script setup>
import { onMounted, reactive, ref } from 'vue'
import draggable from 'vuedraggable'
import { Bars3Icon, ChevronDownIcon, ChevronRightIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import Modal from '../common/Modal.vue'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import TextareaInput from '../forms/TextareaInput.vue'
import ModuleContentList from './ModuleContentList.vue'

const props = defineProps({
    courseId: { type: Number, required: true },
})

const store = useSelfPacedCoursesStore()
const loading = ref(true)
const modules = ref([])
const expanded = ref(new Set())
const actionError = ref('')

const modalOpen = ref(false)
const editingModule = ref(null)
const saving = ref(false)
const form = reactive({ title: '', description: '', activity_completion_required: true, assessment_completion_required: false })

async function load() {
    loading.value = true
    modules.value = await store.fetchModules(props.courseId)
    loading.value = false
}

onMounted(load)

function toggle(moduleId) {
    if (expanded.value.has(moduleId)) {
        expanded.value.delete(moduleId)
    } else {
        expanded.value.add(moduleId)
    }
    expanded.value = new Set(expanded.value)
}

function openCreate() {
    editingModule.value = null
    form.title = ''
    form.description = ''
    form.activity_completion_required = true
    form.assessment_completion_required = false
    modalOpen.value = true
}

function openEdit(module) {
    editingModule.value = module
    form.title = module.title
    form.description = module.description ?? ''
    form.activity_completion_required = module.activity_completion_required
    form.assessment_completion_required = module.assessment_completion_required
    modalOpen.value = true
}

async function save() {
    saving.value = true
    actionError.value = ''

    try {
        if (editingModule.value) {
            const updated = await store.updateModule(editingModule.value.id, { ...form })
            const index = modules.value.findIndex((module) => module.id === updated.id)
            modules.value[index] = { ...modules.value[index], ...updated }
        } else {
            const created = await store.createModule(props.courseId, { title: form.title, description: form.description })
            modules.value.push({ ...created, activities: [], assessments: [] })
        }
        modalOpen.value = false
    } catch (err) {
        const errors = err.response?.data?.errors
        actionError.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}

async function remove(module) {
    if (!confirm(`Delete module "${module.title}"? Its content will be deleted too.`)) return

    await store.deleteModule(module.id)
    modules.value = modules.value.filter((entry) => entry.id !== module.id)
}

async function onReorder() {
    actionError.value = ''
    try {
        await store.reorderModules(
            props.courseId,
            modules.value.map((module) => module.id),
        )
    } catch (err) {
        actionError.value = err.response?.data?.message ?? 'Could not save the new order.'
    }
}

function onModuleContentChanged(moduleId, updated) {
    const index = modules.value.findIndex((module) => module.id === moduleId)
    if (index !== -1) {
        modules.value[index] = { ...modules.value[index], ...updated }
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Organize your course into modules. Drag to reorder.</p>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="openCreate"
            >
                <PlusIcon class="h-4 w-4" />
                Add Module
            </button>
        </div>

        <p v-if="actionError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="modules.length === 0" class="mt-8 flex flex-col items-center text-center">
            <p class="text-gray-500">No modules yet. Add your first module to start building this course.</p>
        </div>

        <draggable v-else v-model="modules" item-key="id" handle=".drag-handle" class="mt-6 space-y-3" @end="onReorder">
            <template #item="{ element: module }">
                <div class="rounded-2xl bg-white shadow-sm">
                    <div class="flex items-center gap-3 p-5">
                        <span class="drag-handle cursor-grab text-gray-400">
                            <Bars3Icon class="h-5 w-5" />
                        </span>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="toggle(module.id)">
                            <component :is="expanded.has(module.id) ? ChevronDownIcon : ChevronRightIcon" class="h-4 w-4" />
                        </button>
                        <div class="min-w-0 flex-1 cursor-pointer" @click="toggle(module.id)">
                            <p class="text-ink font-bold">{{ module.title }}</p>
                            <p v-if="module.description" class="mt-0.5 truncate text-sm text-gray-500">{{ module.description }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ module.activities.length }} activit{{ module.activities.length === 1 ? 'y' : 'ies' }}
                                &middot; {{ module.assessments.length }} assessment{{ module.assessments.length === 1 ? '' : 's' }}
                            </p>
                        </div>
                        <button type="button" class="shrink-0 text-gray-500 hover:text-gray-700" @click="openEdit(module)">
                            <PencilSquareIcon class="h-4 w-4" />
                        </button>
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="remove(module)">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <div v-if="expanded.has(module.id)" class="border-t border-gray-100 p-5">
                        <ModuleContentList :module="module" @changed="(updated) => onModuleContentChanged(module.id, updated)" />
                    </div>
                </div>
            </template>
        </draggable>

        <Modal v-model="modalOpen" :title="editingModule ? 'Edit Module' : 'Add Module'">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <FloatingLabelInput id="module-title" v-model="form.title" label="Module Title" />
                <TextareaInput id="module-description" v-model="form.description" label="Description" />

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.activity_completion_required" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                        Require all Learning Activities to be completed
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.assessment_completion_required" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                        Require required Assessments to be passed
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="modalOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>

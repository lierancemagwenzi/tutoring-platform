<script setup>
import { reactive, ref } from 'vue'
import { useCoursesStore } from '../../../stores/courses'
import FloatingLabelInput from '../../forms/FloatingLabelInput.vue'
import TextareaInput from '../../forms/TextareaInput.vue'
import SelectInput from '../../forms/SelectInput.vue'
import KatexRender from '../KatexRender.vue'

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const props = defineProps({
    block: { type: Object, default: null },
    lessonId: { type: Number, required: true },
})

const emit = defineEmits(['saved', 'cancelled'])

const store = useCoursesStore()
const saving = ref(false)
const error = ref('')

const form = reactive({
    title: props.block?.title ?? '',
    status: props.block?.status ?? 'draft',
    latex: props.block?.content?.latex ?? '',
    displayMode: props.block?.content?.display_mode ?? true,
})

async function save() {
    saving.value = true
    error.value = ''

    const payload = {
        block_type: 'math',
        title: form.title || null,
        status: form.status,
        latex: form.latex,
        display_mode: form.displayMode,
    }

    try {
        const saved = props.block
            ? await store.updateBlock(props.block.id, payload)
            : await store.createBlock(props.lessonId, payload)
        emit('saved', saved)
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors
            ? Object.values(errors).flat().join(' ')
            : (err.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="save">
        <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

        <FloatingLabelInput id="math-title" v-model="form.title" label="Title (optional)" />

        <TextareaInput id="math-latex" v-model="form.latex" label="LaTeX" :rows="4" />

        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.displayMode" type="checkbox" class="accent-accent h-4 w-4 rounded" />
            Display mode (centered, larger equation)
        </label>

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
            <p class="mb-2 text-xs font-semibold text-gray-500 uppercase">Preview</p>
            <KatexRender :latex="form.latex" :display-mode="form.displayMode" />
        </div>

        <SelectInput v-if="block" id="math-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />

        <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="$emit('cancelled')">
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
</template>

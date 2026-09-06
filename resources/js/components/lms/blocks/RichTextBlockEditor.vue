<script setup>
import { reactive, ref } from 'vue'
import { useCoursesStore } from '../../../stores/courses'
import FloatingLabelInput from '../../forms/FloatingLabelInput.vue'
import SelectInput from '../../forms/SelectInput.vue'
import RichTextEditor from '../RichTextEditor.vue'

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
    contentHtml: props.block?.content?.html ?? '',
    contentJson: props.block?.content?.json ?? null,
})

async function save() {
    saving.value = true
    error.value = ''

    const payload = {
        block_type: 'rich_text',
        title: form.title || null,
        status: form.status,
        content_html: form.contentHtml,
        content_json: form.contentJson,
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

        <FloatingLabelInput id="rich-text-title" v-model="form.title" label="Title (optional)" />

        <RichTextEditor
            v-model="form.contentHtml"
            @update:json="(json) => (form.contentJson = json)"
        />

        <SelectInput v-if="block" id="rich-text-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />

        <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="rounded-full border border-border px-5 py-2.5 font-semibold text-body" @click="$emit('cancelled')">
                Cancel
            </button>
            <button
                type="submit"
                :disabled="saving"
                class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ saving ? 'Saving…' : 'Save' }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useCoursesStore } from '../../../stores/courses'
import FloatingLabelInput from '../../forms/FloatingLabelInput.vue'
import TextareaInput from '../../forms/TextareaInput.vue'
import SelectInput from '../../forms/SelectInput.vue'
import MermaidRender from '../MermaidRender.vue'

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const TEMPLATES = {
    flowchart: 'flowchart TD\n    A[Start] --> B{Decision}\n    B -->|Yes| C[Option 1]\n    B -->|No| D[Option 2]',
    sequence: 'sequenceDiagram\n    participant A\n    participant B\n    A->>B: Hello B\n    B-->>A: Hello A',
    class: 'classDiagram\n    class Animal {\n      +String name\n      +makeSound()\n    }\n    Animal <|-- Dog',
    state: 'stateDiagram-v2\n    [*] --> Idle\n    Idle --> Running\n    Running --> [*]',
    er: 'erDiagram\n    CUSTOMER ||--o{ ORDER : places\n    ORDER ||--|{ LINE_ITEM : contains',
    journey: 'journey\n    title My Day\n    section Morning\n      Wake up: 5: Me\n      Coffee: 3: Me',
    gantt: 'gantt\n    title Project Plan\n    dateFormat YYYY-MM-DD\n    section Phase 1\n      Task 1 :a1, 2024-01-01, 7d',
}

const TEMPLATE_OPTIONS = [
    { value: 'flowchart', label: 'Flowchart' },
    { value: 'sequence', label: 'Sequence Diagram' },
    { value: 'class', label: 'Class Diagram' },
    { value: 'state', label: 'State Diagram' },
    { value: 'er', label: 'ER Diagram' },
    { value: 'journey', label: 'Journey Diagram' },
    { value: 'gantt', label: 'Gantt Chart' },
]

const props = defineProps({
    block: { type: Object, default: null },
    lessonId: { type: Number, required: true },
})

const emit = defineEmits(['saved', 'cancelled'])

const store = useCoursesStore()
const saving = ref(false)
const error = ref('')
const template = ref('flowchart')

const form = reactive({
    title: props.block?.title ?? '',
    status: props.block?.status ?? 'draft',
    diagram: props.block?.content?.diagram ?? '',
})

function insertTemplate() {
    form.diagram = TEMPLATES[template.value]
}

async function save() {
    saving.value = true
    error.value = ''

    const payload = {
        block_type: 'mermaid',
        title: form.title || null,
        status: form.status,
        diagram: form.diagram,
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

        <FloatingLabelInput id="mermaid-title" v-model="form.title" label="Title (optional)" />

        <div class="flex items-end gap-3">
            <div class="flex-1">
                <SelectInput id="mermaid-template" v-model="template" label="Insert Template" :options="TEMPLATE_OPTIONS" />
            </div>
            <button
                type="button"
                class="rounded-full border border-border px-4 py-2.5 text-sm font-semibold text-body"
                @click="insertTemplate"
            >
                Insert
            </button>
        </div>

        <TextareaInput id="mermaid-diagram" v-model="form.diagram" label="Mermaid Syntax" :rows="6" />

        <div class="rounded-xl border border-border bg-card-alt p-4">
            <p class="mb-2 text-xs font-semibold text-muted uppercase">Preview</p>
            <MermaidRender :diagram="form.diagram" />
        </div>

        <SelectInput v-if="block" id="mermaid-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />

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

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTutorApplicationStore } from '../../../stores/tutorApplication'
import SelectInput from '../../../components/forms/SelectInput.vue'
import FileUploadInput from '../../../components/forms/FileUploadInput.vue'

const TYPE_OPTIONS = [
    { value: 'degree', label: 'Degree' },
    { value: 'teaching_certificate', label: 'Teaching Certificate' },
    { value: 'police_clearance', label: 'Police Clearance' },
    { value: 'other', label: 'Other' },
]

const TYPE_LABELS = Object.fromEntries(TYPE_OPTIONS.map((option) => [option.value, option.label]))

const tutorApp = useTutorApplicationStore()
const router = useRouter()

const loading = ref(true)
const newDocType = ref('')
const adding = ref(false)
const addError = ref('')
const rowErrors = ref({})
const rowBusy = ref({})

const documents = computed(() => tutorApp.application?.documents ?? [])

onMounted(async () => {
    if (!tutorApp.application) {
        await tutorApp.fetch()
    }
    loading.value = false
})

async function handleAdd(file) {
    if (!newDocType.value) {
        addError.value = 'Please choose a document type first.'
        return
    }

    adding.value = true
    addError.value = ''

    try {
        await tutorApp.addDocument(newDocType.value, file)
        newDocType.value = ''
    } catch (error) {
        const errors = error.response?.data?.errors
        addError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        adding.value = false
    }
}

async function handleReplace(id, file) {
    rowBusy.value[id] = true
    rowErrors.value[id] = ''

    try {
        await tutorApp.replaceDocument(id, file)
    } catch {
        rowErrors.value[id] = 'Could not replace this document. Please try again.'
    } finally {
        rowBusy.value[id] = false
    }
}

async function handleRemove(id) {
    rowBusy.value[id] = true
    rowErrors.value[id] = ''

    try {
        await tutorApp.deleteDocument(id)
    } catch {
        rowErrors.value[id] = 'Could not delete this document. Please try again.'
    } finally {
        rowBusy.value[id] = false
    }
}

function continueApplication() {
    router.push('/tutor/application/review')
}
</script>

<template>
    <div v-if="loading" class="flex justify-center py-24">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>

    <div v-else class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Supporting documents</h1>
            <p class="mt-2 text-muted">Add any documents that support your application.</p>
        </div>

        <ul class="mt-8 space-y-4">
            <li v-for="document in documents" :key="document.id">
                <p v-if="rowErrors[document.id]" class="mb-2 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-600">
                    {{ rowErrors[document.id] }}
                </p>
                <FileUploadInput
                    :id="`document-${document.id}`"
                    :label="TYPE_LABELS[document.type] ?? document.type"
                    :file-name="document.original_name"
                    :uploading="rowBusy[document.id]"
                    removable
                    @select="(file) => handleReplace(document.id, file)"
                    @remove="() => handleRemove(document.id)"
                />
            </li>
        </ul>

        <div class="mt-8 space-y-4 rounded-xl border border-border p-5">
            <p class="font-semibold text-body">Add a document</p>
            <p v-if="addError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ addError }}</p>
            <SelectInput id="new-document-type" v-model="newDocType" label="Document Type" :options="TYPE_OPTIONS" />
            <FileUploadInput id="new-document-file" label="Document File" :uploading="adding" @select="handleAdd" />
        </div>

        <button
            type="button"
            class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95"
            @click="continueApplication"
        >
            Save & Continue
        </button>
    </div>
</template>

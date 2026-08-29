<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useTutorApplicationStore } from '../../../stores/tutorApplication'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'

const LEVEL_OPTIONS = [
    { value: 'certificate', label: "Certificate" },
    { value: 'diploma', label: 'Diploma' },
    { value: 'bachelors_degree', label: "Bachelor's Degree" },
    { value: 'honours', label: 'Honours' },
    { value: 'masters', label: "Master's" },
    { value: 'phd', label: 'PhD' },
    { value: 'other', label: 'Other' },
]

const LEVEL_LABELS = Object.fromEntries(LEVEL_OPTIONS.map((option) => [option.value, option.label]))

const tutorApp = useTutorApplicationStore()
const router = useRouter()

const loading = ref(true)
const submitting = ref(false)
const listError = ref('')
const formError = ref('')
const showForm = ref(false)
const editingId = ref(null)

const emptyForm = () => ({
    title: '',
    level: '',
    fieldOfStudy: '',
    institution: '',
    startYear: '',
    completionYear: '',
    isCurrentlyStudying: false,
    description: '',
})

const form = reactive(emptyForm())

const qualifications = computed(() => tutorApp.application?.qualifications ?? [])
const canContinue = computed(() => qualifications.value.length > 0)

onMounted(async () => {
    if (!tutorApp.application) {
        await tutorApp.fetch()
    }
    loading.value = false
})

function openAddForm() {
    Object.assign(form, emptyForm())
    editingId.value = null
    formError.value = ''
    showForm.value = true
}

function openEditForm(qualification) {
    form.title = qualification.title
    form.level = qualification.level
    form.fieldOfStudy = qualification.field_of_study
    form.institution = qualification.institution
    form.startYear = qualification.start_year
    form.completionYear = qualification.completion_year ?? ''
    form.isCurrentlyStudying = qualification.is_currently_studying
    form.description = qualification.description ?? ''
    editingId.value = qualification.id
    formError.value = ''
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editingId.value = null
}

async function saveQualification() {
    submitting.value = true
    formError.value = ''

    const payload = {
        title: form.title,
        level: form.level,
        field_of_study: form.fieldOfStudy,
        institution: form.institution,
        start_year: form.startYear,
        completion_year: form.isCurrentlyStudying ? null : form.completionYear,
        is_currently_studying: form.isCurrentlyStudying,
        description: form.description || null,
    }

    try {
        if (editingId.value) {
            await tutorApp.updateQualification(editingId.value, payload)
        } else {
            await tutorApp.addQualification(payload)
        }
        closeForm()
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        submitting.value = false
    }
}

async function removeQualification(id) {
    listError.value = ''
    try {
        await tutorApp.deleteQualification(id)
    } catch {
        listError.value = 'Could not delete this qualification. Please try again.'
    }
}

function continueApplication() {
    router.push('/tutor/application/identity-document')
}
</script>

<template>
    <div v-if="loading" class="flex justify-center py-24">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>

    <div v-else class="w-full max-w-lg">
        <div class="text-center">
            <h1 class="text-ink text-3xl font-bold">Your qualifications</h1>
            <p class="mt-2 text-gray-500">Add every qualification relevant to the subjects you teach.</p>
        </div>

        <p v-if="listError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ listError }}</p>

        <ul class="mt-8 space-y-4">
            <li v-for="qualification in qualifications" :key="qualification.id" class="rounded-xl border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-ink font-bold">{{ qualification.title }}</p>
                        <p class="text-sm text-gray-500">{{ LEVEL_LABELS[qualification.level] ?? qualification.level }} · {{ qualification.field_of_study }}</p>
                        <p class="text-sm text-gray-500">{{ qualification.institution }}</p>
                        <p class="text-sm text-gray-500">
                            {{ qualification.start_year }} –
                            {{ qualification.is_currently_studying ? 'Present' : qualification.completion_year }}
                        </p>
                        <p v-if="qualification.description" class="mt-2 text-sm text-gray-600">{{ qualification.description }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" aria-label="Edit qualification" class="text-accent" @click="openEditForm(qualification)">
                            <PencilSquareIcon class="h-5 w-5" />
                        </button>
                        <button type="button" aria-label="Delete qualification" class="text-red-600" @click="removeQualification(qualification.id)">
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </li>
        </ul>

        <button
            v-if="!showForm"
            type="button"
            class="border-accent text-accent mt-6 flex w-full items-center justify-center gap-2 rounded-full border-2 py-3 font-semibold"
            @click="openAddForm"
        >
            <PlusIcon class="h-5 w-5" />
            Add qualification
        </button>

        <form v-else class="mt-6 space-y-6 rounded-xl border border-gray-200 p-5" novalidate @submit.prevent="saveQualification">
            <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

            <FloatingLabelInput id="qualification-title" v-model="form.title" label="Qualification Title" />
            <SelectInput id="qualification-level" v-model="form.level" label="Qualification Level" :options="LEVEL_OPTIONS" />
            <FloatingLabelInput id="qualification-field" v-model="form.fieldOfStudy" label="Field of Study" />
            <FloatingLabelInput id="qualification-institution" v-model="form.institution" label="Institution" />
            <FloatingLabelInput id="qualification-start-year" v-model="form.startYear" label="Start Year" type="number" />

            <label class="flex cursor-pointer items-center gap-3">
                <input v-model="form.isCurrentlyStudying" type="checkbox" class="accent-accent h-5 w-5 rounded border-gray-300" />
                <span class="text-gray-600">I am currently studying towards this qualification</span>
            </label>

            <FloatingLabelInput
                v-if="!form.isCurrentlyStudying"
                id="qualification-completion-year"
                v-model="form.completionYear"
                label="Completion Year"
                type="number"
            />

            <TextareaInput id="qualification-description" v-model="form.description" label="Qualification Description (optional)" :rows="3" />

            <div class="flex gap-3">
                <button type="button" class="w-full rounded-full border border-gray-300 py-3 font-semibold text-gray-700" @click="closeForm">
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="submitting"
                    class="bg-amber w-full rounded-full py-3 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ submitting ? 'Saving…' : 'Save qualification' }}
                </button>
            </div>
        </form>

        <button
            type="button"
            :disabled="!canContinue"
            class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            @click="continueApplication"
        >
            Save & Continue
        </button>
    </div>
</template>

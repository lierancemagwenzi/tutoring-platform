<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { useTutorApplicationStore } from '../../../stores/tutorApplication'
import FileUploadInput from '../../../components/forms/FileUploadInput.vue'

const tutorApp = useTutorApplicationStore()
const router = useRouter()

const loading = ref(true)
const uploading = ref(false)
const uploadError = ref('')

const governmentId = computed(() => tutorApp.application?.government_id ?? null)
const canContinue = computed(() => Boolean(governmentId.value))

onMounted(async () => {
    if (!tutorApp.application) {
        await tutorApp.fetch()
    }
    loading.value = false
})

async function handleSelect(file) {
    uploading.value = true
    uploadError.value = ''

    try {
        await tutorApp.uploadIdentityDocument(file)
    } catch (error) {
        const errors = error.response?.data?.errors
        uploadError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        uploading.value = false
    }
}

function continueApplication() {
    router.push('/tutor/application/documents')
}
</script>

<template>
    <div v-if="loading" class="flex justify-center py-24">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>

    <div v-else class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Verify your identity</h1>
            <p class="mt-2 text-muted">Upload a government-issued ID so we can confirm who you are.</p>
        </div>

        <div class="mt-10 space-y-6">
            <p v-if="uploadError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ uploadError }}</p>

            <FileUploadInput
                id="government-id"
                label="Government ID"
                :file-name="governmentId?.name ?? ''"
                :uploading="uploading"
                @select="handleSelect"
            />

            <div class="flex items-center justify-center gap-2 text-sm text-muted">
                <ShieldCheckIcon class="h-5 w-5" />
                Your information is confidential and secure
            </div>

            <button
                type="button"
                :disabled="!canContinue"
                class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                @click="continueApplication"
            >
                Save & Continue
            </button>
        </div>
    </div>
</template>

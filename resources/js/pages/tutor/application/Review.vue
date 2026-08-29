<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTutorApplicationStore } from '../../../stores/tutorApplication'
import { useAuthStore } from '../../../stores/auth'

const LEVEL_LABELS = {
    certificate: 'Certificate',
    diploma: 'Diploma',
    bachelors_degree: "Bachelor's Degree",
    honours: 'Honours',
    masters: "Master's",
    phd: 'PhD',
    other: 'Other',
}

const DOCUMENT_TYPE_LABELS = {
    degree: 'Degree',
    teaching_certificate: 'Teaching Certificate',
    police_clearance: 'Police Clearance',
    other: 'Other',
}

const tutorApp = useTutorApplicationStore()
const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const submitting = ref(false)
const submitError = ref('')

const app = computed(() => tutorApp.application)

onMounted(async () => {
    if (!tutorApp.application) {
        await tutorApp.fetch()
    }
    loading.value = false
})

async function submitApplication() {
    submitting.value = true
    submitError.value = ''

    try {
        await tutorApp.submit()
        auth.markTutorOnboardingComplete()
        router.push('/tutor/application/success')
    } catch (error) {
        const errors = error.response?.data?.errors?.application
        submitError.value = errors
            ? errors.join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div v-if="loading" class="flex justify-center py-24">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>

    <div v-else class="w-full max-w-lg">
        <div class="text-center">
            <h1 class="text-ink text-3xl font-bold">Review your application</h1>
            <p class="mt-2 text-gray-500">Make sure everything looks right before you submit.</p>
        </div>

        <p v-if="submitError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ submitError }}</p>

        <div class="mt-8 space-y-6">
            <section class="rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Basic Information</h2>
                    <router-link to="/tutor/application/basic-info" class="text-accent text-sm font-semibold">Edit</router-link>
                </div>
                <dl class="mt-3 space-y-1 text-sm text-gray-600">
                    <div v-if="app.profile_photo_url">
                        <img :src="app.profile_photo_url" alt="Profile photo" class="h-16 w-16 rounded-full object-cover" />
                    </div>
                    <p><span class="font-medium text-gray-900">Display name:</span> {{ app.display_name }}</p>
                    <p><span class="font-medium text-gray-900">Bio:</span> {{ app.bio }}</p>
                    <p><span class="font-medium text-gray-900">Experience:</span> {{ app.years_experience }} years</p>
                    <p><span class="font-medium text-gray-900">Occupation:</span> {{ app.occupation }}</p>
                    <p><span class="font-medium text-gray-900">Languages:</span> {{ app.languages.join(', ') }}</p>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Professional Profile</h2>
                    <router-link to="/tutor/application/professional-profile" class="text-accent text-sm font-semibold">Edit</router-link>
                </div>
                <dl class="mt-3 space-y-1 text-sm text-gray-600">
                    <p><span class="font-medium text-gray-900">Teaching style:</span> {{ app.teaching_style }}</p>
                    <p><span class="font-medium text-gray-900">About me:</span> {{ app.about_me }}</p>
                    <p><span class="font-medium text-gray-900">Why choose me:</span> {{ app.why_choose_me }}</p>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Qualifications</h2>
                    <router-link to="/tutor/application/qualifications" class="text-accent text-sm font-semibold">Edit</router-link>
                </div>
                <ul class="mt-3 space-y-3">
                    <li v-for="qualification in app.qualifications" :key="qualification.id" class="text-sm text-gray-600">
                        <p class="font-medium text-gray-900">{{ qualification.title }}</p>
                        <p>{{ LEVEL_LABELS[qualification.level] ?? qualification.level }} · {{ qualification.field_of_study }} · {{ qualification.institution }}</p>
                        <p>
                            {{ qualification.start_year }} –
                            {{ qualification.is_currently_studying ? 'Present' : qualification.completion_year }}
                        </p>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Identity Verification</h2>
                    <router-link to="/tutor/application/identity-document" class="text-accent text-sm font-semibold">Edit</router-link>
                </div>
                <p class="mt-3 text-sm text-gray-600">{{ app.government_id?.name ?? 'Not uploaded' }}</p>
            </section>

            <section class="rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Supporting Documents</h2>
                    <router-link to="/tutor/application/documents" class="text-accent text-sm font-semibold">Edit</router-link>
                </div>
                <ul class="mt-3 space-y-1 text-sm text-gray-600">
                    <li v-for="document in app.documents" :key="document.id">
                        {{ DOCUMENT_TYPE_LABELS[document.type] ?? document.type }} — {{ document.original_name }}
                    </li>
                    <li v-if="app.documents.length === 0" class="italic text-gray-400">None uploaded</li>
                </ul>
            </section>
        </div>

        <button
            type="button"
            :disabled="submitting"
            class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            @click="submitApplication"
        >
            {{ submitting ? 'Submitting…' : 'Submit Application' }}
        </button>
    </div>
</template>

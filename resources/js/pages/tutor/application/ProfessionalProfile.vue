<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { useTutorApplicationStore } from '../../../stores/tutorApplication'
import TextareaInput from '../../../components/forms/TextareaInput.vue'

const tutorApp = useTutorApplicationStore()
const router = useRouter()

const loading = ref(true)
const submitting = ref(false)
const submitError = ref('')

const teachingStyle = ref('')
const aboutMe = ref('')
const whyChooseMe = ref('')

const canContinue = computed(() => teachingStyle.value && aboutMe.value && whyChooseMe.value)

onMounted(async () => {
    if (!tutorApp.application) {
        await tutorApp.fetch()
    }
    const app = tutorApp.application
    teachingStyle.value = app.teaching_style ?? ''
    aboutMe.value = app.about_me ?? ''
    whyChooseMe.value = app.why_choose_me ?? ''
    loading.value = false
})

async function continueApplication() {
    submitting.value = true
    submitError.value = ''

    try {
        await tutorApp.saveProfessionalProfile({
            teachingStyle: teachingStyle.value,
            aboutMe: aboutMe.value,
            whyChooseMe: whyChooseMe.value,
        })
        router.push('/tutor/application/qualifications')
    } catch (error) {
        const errors = error.response?.data?.errors
        submitError.value = errors
            ? Object.values(errors).flat().join(' ')
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

    <div v-else class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Your professional profile</h1>
            <p class="mt-2 text-muted">Help students understand how you teach and why they should pick you.</p>
        </div>

        <form class="mt-10 space-y-6" novalidate @submit.prevent="continueApplication">
            <p v-if="submitError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ submitError }}</p>

            <TextareaInput id="teaching-style" v-model="teachingStyle" label="Teaching Style" :rows="4" />
            <TextareaInput id="about-me" v-model="aboutMe" label="About Me" :rows="4" />
            <TextareaInput id="why-choose-me" v-model="whyChooseMe" label="Why Students Should Choose Me" :rows="4" />

            <div class="flex items-center justify-center gap-2 text-sm text-muted">
                <ShieldCheckIcon class="h-5 w-5" />
                Your information is confidential and secure
            </div>

            <button
                type="submit"
                :disabled="!canContinue || submitting"
                class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ submitting ? 'Saving…' : 'Save & Continue' }}
            </button>
        </form>
    </div>
</template>

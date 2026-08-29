<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { useTutorApplicationStore } from '../../../stores/tutorApplication'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'
import TagInput from '../../../components/forms/TagInput.vue'
import FileUploadInput from '../../../components/forms/FileUploadInput.vue'

const tutorApp = useTutorApplicationStore()
const router = useRouter()

const loading = ref(true)
const submitting = ref(false)
const submitError = ref('')

const displayName = ref('')
const bio = ref('')
const yearsExperience = ref('')
const occupation = ref('')
const languages = ref([])
const profilePhoto = ref(null)
const existingPhotoName = ref('')

const canContinue = computed(
    () => displayName.value && bio.value && yearsExperience.value !== '' && occupation.value && languages.value.length > 0,
)

onMounted(async () => {
    if (!tutorApp.application) {
        await tutorApp.fetch()
    }
    const app = tutorApp.application
    displayName.value = app.display_name ?? ''
    bio.value = app.bio ?? ''
    yearsExperience.value = app.years_experience ?? ''
    occupation.value = app.occupation ?? ''
    languages.value = app.languages ?? []
    if (app.profile_photo_url) {
        existingPhotoName.value = 'Current photo'
    }
    loading.value = false
})

async function continueApplication() {
    submitting.value = true
    submitError.value = ''

    try {
        await tutorApp.saveBasicInfo({
            displayName: displayName.value,
            bio: bio.value,
            yearsExperience: yearsExperience.value,
            occupation: occupation.value,
            languages: languages.value,
            profilePhoto: profilePhoto.value,
        })
        router.push('/tutor/application/professional-profile')
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
            <h1 class="text-ink text-3xl font-bold">Tell us about yourself</h1>
            <p class="mt-2 text-gray-500">This helps students get to know you before booking a session.</p>
        </div>

        <form class="mt-10 space-y-6" novalidate @submit.prevent="continueApplication">
            <p v-if="submitError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ submitError }}</p>

            <FloatingLabelInput id="display-name" v-model="displayName" label="Display Name" />

            <FileUploadInput
                id="profile-photo"
                label="Profile Photo"
                accept=".jpg,.jpeg,.png"
                hint="JPG, JPEG or PNG"
                :file-name="profilePhoto?.name ?? existingPhotoName"
                removable
                @select="
                    (file) => {
                        profilePhoto = file
                        existingPhotoName = ''
                    }
                "
                @remove="
                    () => {
                        profilePhoto = null
                        existingPhotoName = ''
                    }
                "
            />

            <TextareaInput id="bio" v-model="bio" label="Biography" :rows="4" />

            <FloatingLabelInput id="years-experience" v-model="yearsExperience" label="Years of Teaching Experience" type="number" />

            <FloatingLabelInput id="occupation" v-model="occupation" label="Current Occupation" />

            <TagInput id="languages" v-model="languages" label="Languages Spoken (press Enter to add)" />

            <div class="flex items-center justify-center gap-2 text-sm text-gray-500">
                <ShieldCheckIcon class="h-5 w-5" />
                Your information is confidential and secure
            </div>

            <button
                type="submit"
                :disabled="!canContinue || submitting"
                class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ submitting ? 'Saving…' : 'Save & Continue' }}
            </button>
        </form>
    </div>
</template>

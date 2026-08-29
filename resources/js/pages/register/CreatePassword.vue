<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CheckIcon, EyeIcon, EyeSlashIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { useRegistrationStore } from '../../stores/registration'
import { useAuthStore } from '../../stores/auth'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'
import api from '../../services/api'

const registration = useRegistrationStore()
const auth = useAuthStore()
const router = useRouter()

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const submitting = ref(false)
const submitError = ref('')

const rules = computed(() => [
    { label: 'Min 8 characters', met: registration.password.length >= 8 },
    { label: 'An uppercase', met: /[A-Z]/.test(registration.password) },
    { label: 'A special character', met: /[^A-Za-z0-9]/.test(registration.password) },
    { label: 'A number', met: /[0-9]/.test(registration.password) },
])

const canContinue = computed(
    () =>
        rules.value.every((rule) => rule.met) &&
        registration.password.length > 0 &&
        registration.password === registration.confirmPassword,
)

function buildPayload() {
    const base = {
        first_name: registration.learner.firstName,
        last_name: registration.learner.surname,
        password: registration.password,
        password_confirmation: registration.confirmPassword,
    }

    if (registration.role === 'guide') {
        return {
            ...base,
            email: registration.learner.email,
            phone: registration.learner.countryCode + registration.learner.cellphone,
        }
    }

    if (registration.isUnderage) {
        return {
            ...base,
            email: registration.guardian.email,
            phone: registration.guardian.countryCode + registration.guardian.cellphone,
            is_minor: true,
            guardian_first_name: registration.guardian.firstName,
            guardian_last_name: registration.guardian.surname,
            guardian_email: registration.guardian.email,
            guardian_phone: registration.guardian.countryCode + registration.guardian.cellphone,
            relationship_to_student: registration.guardian.relationshipToStudent,
        }
    }

    return {
        ...base,
        email: registration.learner.email,
        phone: registration.learner.countryCode + registration.learner.cellphone,
        is_minor: false,
    }
}

async function continueRegistration() {
    submitting.value = true
    submitError.value = ''

    const endpoint = registration.role === 'guide' ? '/register/tutor' : '/register/student'

    try {
        const { data } = await api.post(endpoint, buildPayload())
        auth.setSession(data.user, data.token)
        router.push('/register/otp')
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
    <div v-if="!submitting" class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-ink text-3xl font-bold">Now, create your password</h1>
            <p class="mt-2 text-gray-500">Your password must be strong to keep your profile safe.</p>
        </div>

        <form class="mt-10 space-y-6" novalidate @submit.prevent="continueRegistration">
            <p v-if="submitError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ submitError }}</p>

            <FloatingLabelInput
                id="password"
                v-model="registration.password"
                label="Password"
                :type="showPassword ? 'text' : 'password'"
            >
                <template #icon>
                    <button type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
                        <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
                        <EyeIcon v-else class="h-5 w-5" />
                    </button>
                </template>
            </FloatingLabelInput>

            <FloatingLabelInput
                id="confirm-password"
                v-model="registration.confirmPassword"
                label="Confirm password"
                :type="showConfirmPassword ? 'text' : 'password'"
            >
                <template #icon>
                    <button
                        type="button"
                        :aria-label="showConfirmPassword ? 'Hide password' : 'Show password'"
                        @click="showConfirmPassword = !showConfirmPassword"
                    >
                        <EyeSlashIcon v-if="showConfirmPassword" class="h-5 w-5" />
                        <EyeIcon v-else class="h-5 w-5" />
                    </button>
                </template>
            </FloatingLabelInput>

            <ul class="space-y-3">
                <li v-for="rule in rules" :key="rule.label" class="flex items-center gap-3">
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                        :class="rule.met ? 'bg-accent text-white' : 'bg-gray-200 text-transparent'"
                    >
                        <CheckIcon class="h-3.5 w-3.5" />
                    </span>
                    <span class="text-ink">{{ rule.label }}</span>
                </li>
            </ul>

            <div class="flex items-center justify-center gap-2 text-sm text-gray-500">
                <ShieldCheckIcon class="h-5 w-5" />
                Your information is confidential and secure
            </div>

            <button
                type="submit"
                :disabled="!canContinue"
                class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                Continue
            </button>
        </form>

        <p class="mt-6 text-center">
            <a href="#" class="text-accent text-sm font-semibold underline">Need help?</a>
        </p>
    </div>

    <div v-else class="flex items-center justify-center py-24">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>
</template>

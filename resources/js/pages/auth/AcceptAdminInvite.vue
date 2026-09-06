<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/auth'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const otpRaw = ref('')
const otp = computed({
    get: () => otpRaw.value,
    set: (value) => {
        otpRaw.value = value.replace(/\D/g, '').slice(0, 6)
    },
})
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const submitting = ref(false)
const errorMessage = ref('')

const rules = computed(() => [
    { label: 'Min 8 characters', met: password.value.length >= 8 },
    { label: 'An uppercase', met: /[A-Z]/.test(password.value) },
    { label: 'A special character', met: /[^A-Za-z0-9]/.test(password.value) },
    { label: 'A number', met: /[0-9]/.test(password.value) },
])

const canContinue = computed(
    () =>
        Boolean(email.value) &&
        otp.value.length === 6 &&
        rules.value.every((rule) => rule.met) &&
        password.value === confirmPassword.value,
)

async function handleSubmit() {
    submitting.value = true
    errorMessage.value = ''

    try {
        await auth.acceptAdminInvite({
            email: email.value.trim(),
            otp: otp.value,
            password: password.value,
            password_confirmation: confirmPassword.value,
        })
        router.push('/login')
    } catch (error) {
        const errors = error.response?.data?.errors
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Set your admin password</h1>
            <p class="text-muted mt-2">Enter the code emailed to you and choose a password to accept your admin invite.</p>
        </div>

        <form class="mt-10 space-y-6" novalidate @submit.prevent="handleSubmit">
            <p v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

            <FloatingLabelInput id="email" v-model="email" label="Email address" type="email" autocomplete="email" />
            <FloatingLabelInput id="otp" v-model="otp" label="6-digit code" autocomplete="one-time-code" />

            <FloatingLabelInput id="password" v-model="password" label="Password" :type="showPassword ? 'text' : 'password'">
                <template #icon>
                    <button type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
                        <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
                        <EyeIcon v-else class="h-5 w-5" />
                    </button>
                </template>
            </FloatingLabelInput>

            <FloatingLabelInput
                id="confirm-password"
                v-model="confirmPassword"
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
                        :class="rule.met ? 'bg-accent text-white' : 'bg-card-alt text-transparent'"
                    >
                        <CheckIcon class="h-3.5 w-3.5" />
                    </span>
                    <span class="text-body">{{ rule.label }}</span>
                </li>
            </ul>

            <button
                type="submit"
                :disabled="submitting || !canContinue"
                class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ submitting ? 'Setting up…' : 'Accept invite' }}
            </button>
        </form>

        <p class="mt-6 text-center">
            <router-link to="/login" class="text-accent text-sm font-semibold underline">Back to log in</router-link>
        </p>
    </div>
</template>

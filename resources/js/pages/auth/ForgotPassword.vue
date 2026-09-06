<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const submitting = ref(false)
const submitted = ref(false)
const errorMessage = ref('')

async function handleSubmit() {
    submitting.value = true
    errorMessage.value = ''

    try {
        // Backend always returns the same generic response whether or not
        // the email belongs to an account, so there's nothing to branch on
        // here beyond a genuine request failure (validation, network, etc).
        await auth.forgotPassword(email.value.trim())
        submitted.value = true
    } catch (error) {
        errorMessage.value = error.response?.data?.errors?.email?.[0] ?? error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        submitting.value = false
    }
}

function continueToReset() {
    router.push({ path: '/reset-password', query: { email: email.value.trim() } })
}
</script>

<template>
    <div class="w-full max-w-md">
        <template v-if="!submitted">
            <div class="text-center">
                <h1 class="text-body text-3xl font-bold">Forgot your password?</h1>
                <p class="text-muted mt-2">Enter your email address and we'll send you a code to reset it.</p>
            </div>

            <form class="mt-10 space-y-6" novalidate @submit.prevent="handleSubmit">
                <p v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

                <FloatingLabelInput id="email" v-model="email" label="Email address" type="email" autocomplete="email" />

                <button
                    type="submit"
                    :disabled="submitting || !email"
                    class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ submitting ? 'Sending…' : 'Send reset code' }}
                </button>
            </form>
        </template>

        <template v-else>
            <div class="text-center">
                <h1 class="text-body text-3xl font-bold">Check your email</h1>
                <p class="text-muted mt-2">
                    If an account exists for <strong>{{ email }}</strong>, we've sent a password reset code to it.
                </p>
            </div>

            <button
                type="button"
                class="bg-amber mt-10 w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95"
                @click="continueToReset"
            >
                I have the code
            </button>
        </template>

        <p class="mt-6 text-center">
            <router-link to="/login" class="text-accent text-sm font-semibold underline">Back to log in</router-link>
        </p>
    </div>
</template>

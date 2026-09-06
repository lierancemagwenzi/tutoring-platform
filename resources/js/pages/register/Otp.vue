<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/auth'
import { DASHBOARD_BY_ROLE } from '../../router/dashboardByRole'

// Shared by two routes: the last step of the registration wizard
// (/register/otp, meta.afterVerify = 'success') and the standalone
// destination a returning-but-unverified login is redirected to
// (/verify-account, meta.afterVerify = 'dashboard'). Both already have an
// authenticated (but unverified) session by the time this page is reached.

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const digits = ref(['', '', '', '', '', ''])
const inputs = ref([])
const secondsLeft = ref(60)
const verifying = ref(false)
const resending = ref(false)
const errorMessage = ref('')
let timer = null

const maskedEmail = computed(() => {
    const email = auth.user?.email ?? ''
    const [local, domain] = email.split('@')
    if (!local || !domain) return email
    const visible = local.slice(0, 2)
    return `${visible}${'*'.repeat(Math.max(local.length - 2, 1))}@${domain}`
})

const canContinue = computed(() => digits.value.every((digit) => digit.length === 1) && !verifying.value)
const canResend = computed(() => secondsLeft.value === 0 && !resending.value)

function startCooldown() {
    clearInterval(timer)
    secondsLeft.value = 60
    timer = setInterval(() => {
        if (secondsLeft.value > 0) secondsLeft.value--
    }, 1000)
}

function handleInput(index, event) {
    const value = event.target.value.replace(/\D/g, '').slice(-1)
    digits.value[index] = value
    if (value && index < inputs.value.length - 1) {
        inputs.value[index + 1]?.focus()
    }
}

function handleKeydown(index, event) {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus()
    }
}

function extractErrorMessage(error) {
    return error.response?.data?.errors?.otp?.[0] ?? error.response?.data?.message ?? 'Something went wrong. Please try again.'
}

async function resend() {
    if (!canResend.value) return

    resending.value = true
    errorMessage.value = ''

    try {
        await auth.resendVerification()
        digits.value = ['', '', '', '', '', '']
        startCooldown()
        inputs.value[0]?.focus()
    } catch (error) {
        errorMessage.value = extractErrorMessage(error)
    } finally {
        resending.value = false
    }
}

async function verify() {
    verifying.value = true
    errorMessage.value = ''

    try {
        const user = await auth.verifyEmail(digits.value.join(''))

        if (route.meta.afterVerify === 'dashboard') {
            router.push(DASHBOARD_BY_ROLE[user.role] ?? '/login')
        } else {
            router.push('/register/success')
        }
    } catch (error) {
        errorMessage.value = extractErrorMessage(error)
        digits.value = ['', '', '', '', '', '']
        inputs.value[0]?.focus()
    } finally {
        verifying.value = false
    }
}

onMounted(startCooldown)
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Verify your account</h1>
            <p class="text-muted mt-2">We sent a 6-digit code to <strong>{{ maskedEmail }}</strong>.</p>
        </div>

        <form class="mt-10" novalidate @submit.prevent="verify">
            <p v-if="errorMessage" class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-center text-sm text-red-600">{{ errorMessage }}</p>

            <div class="flex justify-center gap-3">
                <input
                    v-for="(digit, index) in digits"
                    :key="index"
                    :ref="(el) => (inputs[index] = el)"
                    :value="digit"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    class="focus:border-accent bg-surface text-body h-16 w-12 rounded-xl border border-border text-center text-2xl outline-none"
                    @input="handleInput(index, $event)"
                    @keydown="handleKeydown(index, $event)"
                />
            </div>

            <p class="text-body mt-4 text-center">
                Didn't receive the code?
                <button
                    type="button"
                    class="text-accent font-bold disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canResend"
                    @click="resend"
                >
                    {{ canResend ? 'Resend' : `Resend in ${secondsLeft}s` }}
                </button>
            </p>

            <div class="text-muted mt-8 flex items-center justify-center gap-2 text-sm">
                <ShieldCheckIcon class="h-5 w-5" />
                Your information is confidential and secure
            </div>

            <button
                type="submit"
                :disabled="!canContinue"
                class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ verifying ? 'Verifying…' : 'Continue' }}
            </button>
        </form>

        <p class="mt-6 text-center">
            <a href="#" class="text-accent text-sm font-semibold underline">Need help?</a>
        </p>
    </div>
</template>

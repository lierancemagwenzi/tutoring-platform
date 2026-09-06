<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/auth'
import { TUTOR_APPLICATION_STEP_ROUTES } from '../../router/tutorApplicationSteps'
import { DASHBOARD_BY_ROLE } from '../../router/dashboardByRole'

const router = useRouter()
const auth = useAuthStore()

const username = ref('')
const password = ref('')
const usernameFocused = ref(false)
const passwordFocused = ref(false)
const showPassword = ref(false)
const loginError = ref('')
const submitting = ref(false)

const usernameFloating = computed(() => usernameFocused.value || username.value.length > 0)
const passwordFloating = computed(() => passwordFocused.value || password.value.length > 0)

async function handleSubmit() {
    submitting.value = true
    loginError.value = ''

    try {
        const user = await auth.login(username.value.trim(), password.value)

        if (!user.email_verified_at) {
            router.push('/verify-account')
        } else if (user.role === 'tutor' && !user.tutor_profile?.onboarding_complete) {
            const step = user.tutor_profile?.onboarding_step ?? 1
            router.push(TUTOR_APPLICATION_STEP_ROUTES[step - 1] ?? TUTOR_APPLICATION_STEP_ROUTES[0])
        } else {
            router.push(DASHBOARD_BY_ROLE[user.role] ?? '/login')
        }
    } catch (error) {
        loginError.value =
            error.response?.data?.errors?.email?.[0] ??
            error.response?.data?.message ??
            'Something went wrong. Please try again.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Log in</h1>
            <p class="text-muted mt-2">Connect with expert guides or eager students</p>
        </div>

        <form class="mt-10 space-y-6" novalidate @submit.prevent="handleSubmit">
            <p v-if="loginError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ loginError }}</p>

            <div>
                <div class="relative">
                    <label
                        for="username"
                        class="bg-surface pointer-events-none absolute left-3 px-1 transition-all duration-150"
                        :class="[
                            usernameFloating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                            usernameFocused ? 'text-accent font-medium' : 'text-muted',
                        ]"
                    >
                        Username
                    </label>
                    <input
                        id="username"
                        v-model="username"
                        type="text"
                        autocomplete="username"
                        class="bg-surface text-body w-full rounded-xl border px-4 py-3.5 pr-11 outline-none transition-colors"
                        :class="usernameFocused ? 'border-accent border-2' : 'border-border'"
                        @focus="usernameFocused = true"
                        @blur="usernameFocused = false"
                    />
                    <InformationCircleIcon
                        class="absolute top-1/2 right-3.5 h-5 w-5 -translate-y-1/2"
                        :class="usernameFocused ? 'text-accent' : 'text-muted'"
                    />
                </div>
                <div class="mt-1.5 text-right">
                    <a href="#" class="text-accent text-sm font-medium underline">Forgot username?</a>
                </div>
            </div>

            <div>
                <div class="relative">
                    <label
                        for="password"
                        class="bg-surface pointer-events-none absolute left-3 px-1 transition-all duration-150"
                        :class="[
                            passwordFloating ? '-top-2.5 text-xs' : 'top-1/2 -translate-y-1/2 text-base',
                            passwordFocused ? 'text-accent font-medium' : 'text-muted',
                        ]"
                    >
                        Password
                    </label>
                    <input
                        id="password"
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        class="bg-surface text-body w-full rounded-xl border px-4 py-3.5 pr-11 outline-none transition-colors"
                        :class="passwordFocused ? 'border-accent border-2' : 'border-border'"
                        @focus="passwordFocused = true"
                        @blur="passwordFocused = false"
                    />
                    <button
                        type="button"
                        class="text-muted absolute top-1/2 right-3.5 -translate-y-1/2"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
                        <EyeIcon v-else class="h-5 w-5" />
                    </button>
                </div>
                <div class="mt-1.5 text-right">
                    <router-link to="/forgot-password" class="text-accent text-sm font-medium underline">Forgot password?</router-link>
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="bg-amber w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ submitting ? 'Logging in…' : 'Log in' }}
                </button>
                <router-link
                    to="/register"
                    class="border-amber text-amber block w-full rounded-full border-2 py-3.5 text-center font-semibold transition hover:bg-amber-50"
                >
                    Register
                </router-link>
            </div>
        </form>

        <p class="mt-6 text-center">
            <a href="#" class="text-accent text-sm font-semibold underline">Need help?</a>
        </p>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckIcon } from '@heroicons/vue/24/outline'
import { useRegistrationStore } from '../../stores/registration'
import { useAuthStore } from '../../stores/auth'
import { DASHBOARD_BY_ROLE } from '../../router/dashboardByRole'

const router = useRouter()
const registration = useRegistrationStore()
const auth = useAuthStore()

onMounted(() => {
    setTimeout(() => {
        registration.reset()
        router.push(DASHBOARD_BY_ROLE[auth.user?.role] ?? '/login')
    }, 2000)
})
</script>

<template>
    <div class="bg-surface flex min-h-screen items-center justify-center px-6">
        <div class="bg-card shadow-popover border-border w-full max-w-md rounded-2xl border p-12 text-center">
            <h1 class="text-body text-2xl font-bold">Your profile is created successfully</h1>
            <div class="bg-amber/10 mx-auto mt-6 flex h-16 w-16 items-center justify-center rounded-full">
                <span class="bg-amber flex h-12 w-12 items-center justify-center rounded-full">
                    <CheckIcon class="text-body h-6 w-6" />
                </span>
            </div>
            <p class="text-muted mt-6">Redirecting you to the next step...</p>
        </div>
    </div>
</template>

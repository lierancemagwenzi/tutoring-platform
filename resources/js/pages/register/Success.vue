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
    <div class="flex min-h-screen items-center justify-center bg-white px-6">
        <div class="w-full max-w-md rounded-2xl border border-gray-100 p-12 text-center shadow-lg">
            <h1 class="text-ink text-2xl font-bold">Your profile is created successfully</h1>
            <div class="mx-auto mt-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                <span class="bg-amber flex h-12 w-12 items-center justify-center rounded-full">
                    <CheckIcon class="text-ink h-6 w-6" />
                </span>
            </div>
            <p class="mt-6 text-gray-500">Redirecting you to the next step...</p>
        </div>
    </div>
</template>

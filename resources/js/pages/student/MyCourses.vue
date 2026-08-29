<script setup>
import { onMounted, ref } from 'vue'
import { AcademicCapIcon } from '@heroicons/vue/24/outline'
import { useOrderStore } from '../../stores/orders'
import EnrollmentCard from '../../components/commerce/EnrollmentCard.vue'

const store = useOrderStore()
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    try {
        await store.fetchMyCourses()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">My Courses</h1>

        <div v-if="loading" class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="n in 3" :key="n" class="animate-pulse rounded-2xl bg-white p-5 shadow-sm">
                <div class="h-36 rounded-xl bg-gray-200" />
                <div class="mt-4 h-4 w-2/3 rounded bg-gray-200" />
                <div class="mt-2 h-3 w-1/3 rounded bg-gray-200" />
            </div>
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-else-if="store.myCourses.length === 0" class="mt-6 flex flex-col items-center rounded-2xl bg-white py-24 text-center shadow-sm">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <AcademicCapIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-gray-500">You haven't enrolled in any courses yet.</p>
            <router-link
                to="/student/marketplace"
                class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
            >
                Browse the Marketplace
            </router-link>
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <EnrollmentCard v-for="enrollment in store.myCourses" :key="enrollment.id" :enrollment="enrollment" />
        </div>
    </div>
</template>

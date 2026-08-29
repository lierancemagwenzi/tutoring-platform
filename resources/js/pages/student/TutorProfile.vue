<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UserCircleIcon } from '@heroicons/vue/24/outline'
import { useMarketplaceStore } from '../../stores/marketplace'
import ServiceCard from '../../components/marketplace/ServiceCard.vue'

const route = useRoute()
const router = useRouter()
const store = useMarketplaceStore()

const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    try {
        await store.fetchTutorProfile(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This tutor could not be found.'
    } finally {
        loading.value = false
    }
})

function continueToBooking(service) {
    router.push({
        name: 'student.booking.create',
        params: { tutorId: route.params.id, serviceId: service.id },
    })
}
</script>

<template>
    <div class="p-8">
        <div v-if="loading" class="animate-pulse space-y-6">
            <div class="flex items-center gap-4 rounded-2xl bg-white p-6 shadow-sm">
                <div class="h-24 w-24 rounded-full bg-gray-200" />
                <div class="flex-1 space-y-2">
                    <div class="h-5 w-1/3 rounded bg-gray-200" />
                    <div class="h-4 w-1/2 rounded bg-gray-200" />
                </div>
            </div>
        </div>

        <p v-else-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="store.tutorProfile">
            <div class="flex flex-col gap-6 rounded-2xl bg-white p-6 shadow-sm sm:flex-row sm:items-center">
                <img
                    v-if="store.tutorProfile.profile_photo"
                    :src="store.tutorProfile.profile_photo"
                    :alt="store.tutorProfile.display_name"
                    class="h-24 w-24 shrink-0 rounded-full object-cover"
                />
                <span v-else class="bg-amber flex h-24 w-24 shrink-0 items-center justify-center rounded-full text-white">
                    <UserCircleIcon class="h-14 w-14" />
                </span>

                <div>
                    <h1 class="text-ink text-2xl font-bold">{{ store.tutorProfile.display_name }}</h1>
                    <p class="mt-1 text-gray-500">{{ store.tutorProfile.years_experience ?? 0 }} years of experience</p>
                    <p v-if="store.tutorProfile.languages.length" class="mt-1 text-gray-500">
                        Speaks {{ store.tutorProfile.languages.join(', ') }}
                    </p>
                    <div v-if="store.tutorProfile.subjects.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="subject in store.tutorProfile.subjects"
                            :key="subject.id"
                            class="bg-accent/10 text-accent rounded-full px-3 py-1 text-xs font-medium"
                        >
                            {{ subject.name }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="store.tutorProfile.bio" class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-ink font-bold">About</h2>
                <p class="mt-2 text-gray-600">{{ store.tutorProfile.bio }}</p>
            </div>

            <div v-if="store.tutorProfile.qualifications.length" class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-ink font-bold">Qualifications</h2>
                <ul class="mt-4 divide-y divide-gray-100">
                    <li v-for="qualification in store.tutorProfile.qualifications" :key="qualification.id" class="py-3">
                        <p class="text-ink font-semibold">{{ qualification.title }}</p>
                        <p class="text-sm text-gray-500">
                            {{ qualification.institution }}
                            <span v-if="qualification.field_of_study"> &middot; {{ qualification.field_of_study }}</span>
                        </p>
                    </li>
                </ul>
            </div>

            <div class="mt-6">
                <h2 class="text-ink font-bold">Services</h2>

                <p v-if="store.tutorProfile.services.length === 0" class="mt-4 text-gray-500">
                    This guide hasn't published any services yet.
                </p>

                <div v-else class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <ServiceCard
                        v-for="service in store.tutorProfile.services"
                        :key="service.id"
                        :service="service"
                        @continue="continueToBooking(service)"
                    />
                </div>
            </div>
        </template>
    </div>
</template>

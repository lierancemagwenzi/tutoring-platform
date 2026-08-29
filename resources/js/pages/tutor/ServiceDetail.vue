<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTutorServicesStore } from '../../stores/tutorServices'

const VISIBILITY_LABELS = { draft: 'Draft', published: 'Published', paused: 'Paused' }
const VISIBILITY_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    paused: 'bg-amber-100 text-amber-700',
}

const route = useRoute()
const router = useRouter()
const store = useTutorServicesStore()

const loading = ref(true)
const errorMessage = ref('')
const service = ref(null)

onMounted(async () => {
    try {
        service.value = await store.fetchService(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This service could not be found.'
    } finally {
        loading.value = false
    }
})

function visibilityLabel(visibility) {
    return VISIBILITY_LABELS[visibility] ?? visibility
}

function visibilityClasses(visibility) {
    return VISIBILITY_CLASSES[visibility] ?? 'bg-gray-100 text-gray-600'
}

function resourceNames(list) {
    return list.map((item) => item.name).join(', ')
}

function backToServices() {
    router.push('/tutor/services')
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="backToServices">&larr; Back to Services</button>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="service">
            <div class="mt-4 flex items-start justify-between gap-2">
                <div>
                    <p class="text-sm text-gray-500">{{ service.subject.name }} &middot; {{ service.category.name }}</p>
                    <h1 class="text-ink mt-1 text-2xl font-bold">{{ service.title }}</h1>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="visibilityClasses(service.visibility)">
                    {{ visibilityLabel(service.visibility) }}
                </span>
            </div>

            <router-link
                :to="`/tutor/services/${service.id}/edit`"
                class="text-accent mt-2 inline-block text-sm font-semibold"
            >
                Edit Service
            </router-link>

            <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
                <p class="text-sm text-gray-600">{{ service.description }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">Subject</dt>
                    <dd class="text-ink font-medium">{{ service.subject.name }}</dd>
                    <dt class="text-gray-500">Category</dt>
                    <dd class="text-ink font-medium">{{ service.category.name }}</dd>
                    <dt class="text-gray-500">Format</dt>
                    <dd class="text-ink font-medium">{{ service.session_format.name }}</dd>
                    <dt class="text-gray-500">Price</dt>
                    <dd class="text-ink font-medium">{{ service.currency }} {{ service.price }}</dd>
                    <dt class="text-gray-500">Duration</dt>
                    <dd class="text-ink font-medium">{{ service.session_duration_minutes }} minutes</dd>
                    <dt class="text-gray-500">Sessions included</dt>
                    <dd class="text-ink font-medium">{{ service.sessions_included }}</dd>
                    <dt class="text-gray-500">Validity period</dt>
                    <dd class="text-ink font-medium">{{ service.validity_period_days }} days</dd>
                    <dt class="text-gray-500">Max students</dt>
                    <dd class="text-ink font-medium">{{ service.max_students_per_session }}</dd>
                </dl>

                <div v-if="service.learning_resources.length" class="mt-4 border-t border-gray-100 pt-4">
                    <p class="font-semibold text-gray-700">Learning resources</p>
                    <p class="mt-1 text-gray-500">{{ resourceNames(service.learning_resources) }}</p>
                </div>
                <div v-if="service.assessment_types.length" class="mt-4 border-t border-gray-100 pt-4">
                    <p class="font-semibold text-gray-700">Assessments</p>
                    <p class="mt-1 text-gray-500">{{ resourceNames(service.assessment_types) }}</p>
                </div>
                <div v-if="service.curricula.length" class="mt-4 border-t border-gray-100 pt-4">
                    <p class="font-semibold text-gray-700">Curriculum</p>
                    <p class="mt-1 text-gray-500">{{ resourceNames(service.curricula) }}</p>
                </div>
            </div>
        </template>
    </div>
</template>

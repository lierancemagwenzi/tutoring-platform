<script setup>
import { onMounted, ref } from 'vue'
import { BriefcaseIcon, EyeIcon, PauseIcon, PencilSquareIcon, PlayIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useTutorServicesStore } from '../../stores/tutorServices'

const store = useTutorServicesStore()

const loading = ref(true)
const actioningId = ref(null)
const actionError = ref('')

const VISIBILITY_LABELS = { draft: 'Draft', published: 'Published', paused: 'Paused' }
const VISIBILITY_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    paused: 'bg-amber-100 text-amber-700',
}

onMounted(async () => {
    await store.fetchServices()
    loading.value = false
})

function visibilityLabel(visibility) {
    return VISIBILITY_LABELS[visibility] ?? visibility
}

function visibilityClasses(visibility) {
    return VISIBILITY_CLASSES[visibility] ?? 'bg-gray-100 text-gray-600'
}

async function publish(service) {
    actioningId.value = service.id
    actionError.value = ''
    try {
        await store.publishService(service.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}

async function pause(service) {
    actioningId.value = service.id
    actionError.value = ''
    try {
        await store.pauseService(service.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}

</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <h1 class="text-ink text-2xl font-bold">Teaching Services</h1>
            <router-link
                to="/tutor/services/create"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
            >
                <PlusIcon class="h-4 w-4" />
                Add Service
            </router-link>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.services.length === 0" class="mt-16 flex flex-col items-center text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <BriefcaseIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-gray-500">You haven't created any services yet.</p>
            <router-link
                to="/tutor/services/create"
                class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
            >
                Add Service
            </router-link>
        </div>

        <div v-else class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="service in store.services" :key="service.id" class="flex flex-col rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm text-gray-500">{{ service.subject.name }} &middot; {{ service.category.name }}</p>
                        <p class="text-ink mt-1 font-bold">{{ service.title }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="visibilityClasses(service.visibility)">
                        {{ visibilityLabel(service.visibility) }}
                    </span>
                </div>

                <p class="mt-3 text-lg font-bold text-gray-900">{{ service.currency }} {{ service.price }}</p>

                <dl class="mt-3 space-y-1 text-sm text-gray-500">
                    <div class="flex justify-between">
                        <dt>Duration</dt>
                        <dd>{{ service.session_duration_minutes }} min</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Sessions included</dt>
                        <dd>{{ service.sessions_included }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Max students</dt>
                        <dd>{{ service.max_students_per_session }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
                    <router-link :to="`/tutor/services/${service.id}`" class="text-accent flex items-center gap-1 text-sm font-semibold">
                        <EyeIcon class="h-4 w-4" />
                        View
                    </router-link>
                    <router-link :to="`/tutor/services/${service.id}/edit`" class="text-accent flex items-center gap-1 text-sm font-semibold">
                        <PencilSquareIcon class="h-4 w-4" />
                        Edit
                    </router-link>
                    <button
                        v-if="service.visibility !== 'published'"
                        type="button"
                        :disabled="actioningId === service.id"
                        class="flex items-center gap-1 text-sm font-semibold text-green-600 disabled:opacity-40"
                        @click="publish(service)"
                    >
                        <PlayIcon class="h-4 w-4" />
                        Publish
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="actioningId === service.id"
                        class="flex items-center gap-1 text-sm font-semibold text-amber-600 disabled:opacity-40"
                        @click="pause(service)"
                    >
                        <PauseIcon class="h-4 w-4" />
                        Pause
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

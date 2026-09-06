<script setup>
import { onMounted, ref } from 'vue'
import { CalendarIcon } from '@heroicons/vue/24/outline'
import { useLearningHubStore } from '../../stores/learningHub'

const store = useLearningHubStore()

const loading = ref(true)
const sessions = ref([])
const meta = ref(null)

async function load(page = 1) {
    loading.value = true
    try {
        const data = await store.fetchUpcomingSessions(page)
        sessions.value = data.sessions
        meta.value = data.meta
    } finally {
        loading.value = false
    }
}

onMounted(() => load())
</script>

<template>
    <div class="bg-card rounded-2xl p-6 shadow-elevated">
        <div class="flex items-center justify-between">
            <h2 class="text-body text-lg font-bold">Upcoming Sessions</h2>
            <router-link to="/student/bookings" class="text-accent text-sm font-semibold">View all</router-link>
        </div>

        <div v-if="loading" class="flex justify-center py-10">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="sessions.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="bg-card-alt text-muted flex h-14 w-14 items-center justify-center rounded-full">
                <CalendarIcon class="h-7 w-7" />
            </span>
            <p class="text-muted mt-3 text-sm">No upcoming sessions booked yet.</p>
            <router-link to="/student/marketplace" class="text-accent mt-2 text-sm font-semibold">Browse tutors</router-link>
        </div>

        <template v-else>
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <li v-for="entry in sessions" :key="entry.booking.id">
                    <router-link
                        :to="`/student/bookings/${entry.booking.id}`"
                        class="bg-card-alt block rounded-xl px-4 py-3.5 transition hover:brightness-95"
                    >
                        <p class="text-body font-semibold">{{ entry.booking.tutor.display_name }}</p>
                        <p class="text-muted text-sm">{{ entry.booking.service.title }}</p>
                        <p class="text-muted mt-1 text-xs">{{ entry.booking.date }} &middot; {{ entry.booking.start_time }}</p>
                    </router-link>
                </li>
            </ul>

            <div v-if="meta && meta.last_page > 1" class="border-border mt-4 flex items-center justify-between border-t pt-4">
                <button
                    type="button"
                    class="text-muted text-sm font-semibold disabled:opacity-30"
                    :disabled="meta.current_page <= 1"
                    @click="load(meta.current_page - 1)"
                >
                    Previous
                </button>
                <span class="text-muted text-xs">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
                <button
                    type="button"
                    class="text-accent text-sm font-semibold disabled:opacity-30"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="load(meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </template>
    </div>
</template>

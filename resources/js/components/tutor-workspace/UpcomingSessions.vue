<script setup>
import { onMounted, ref } from 'vue'
import { CalendarIcon } from '@heroicons/vue/24/outline'
import { useTutorWorkspaceStore } from '../../stores/tutorWorkspace'

const store = useTutorWorkspaceStore()

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
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <div class="flex items-center justify-between">
            <h2 class="text-body text-lg font-bold">Upcoming Sessions</h2>
            <router-link to="/tutor/sessions" class="text-accent text-sm font-semibold">View all</router-link>
        </div>

        <div v-if="loading" class="flex justify-center py-10">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="sessions.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <CalendarIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">No upcoming sessions scheduled.</p>
        </div>

        <template v-else>
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <li v-for="entry in sessions" :key="entry.session.id" class="rounded-xl bg-card-alt px-4 py-3.5">
                    <p class="text-body font-semibold">{{ entry.session.service.subject?.name ?? entry.session.service.title }}</p>
                    <p class="mt-1 text-xs text-muted">{{ entry.session.date }} &middot; {{ entry.session.start_time }}</p>
                    <div class="mt-2 flex flex-wrap gap-3 text-sm font-semibold">
                        <router-link :to="`/tutor/sessions/${entry.session.id}`" class="text-accent">View</router-link>
                        <router-link :to="`/tutor/sessions/${entry.session.id}/content`" class="text-accent">Assign Lessons</router-link>
                    </div>
                </li>
            </ul>

            <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between border-t border-border pt-4">
                <button
                    type="button"
                    class="text-sm font-semibold text-muted disabled:opacity-30"
                    :disabled="meta.current_page <= 1"
                    @click="load(meta.current_page - 1)"
                >
                    Previous
                </button>
                <span class="text-xs text-muted">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
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

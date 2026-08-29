<script setup>
import { onMounted, ref } from 'vue'
import { useLearningHubStore } from '../../stores/learningHub'
import WelcomeCard from '../../components/learning-hub/WelcomeCard.vue'
import QuickActions from '../../components/learning-hub/QuickActions.vue'
import TodaysSessions from '../../components/learning-hub/TodaysSessions.vue'
import UpcomingSessions from '../../components/learning-hub/UpcomingSessions.vue'
import ContinueLearning from '../../components/learning-hub/ContinueLearning.vue'
import PendingActivities from '../../components/learning-hub/PendingActivities.vue'
import RecentResults from '../../components/learning-hub/RecentResults.vue'
import PerformanceSnapshot from '../../components/learning-hub/PerformanceSnapshot.vue'
import TutorFeedback from '../../components/learning-hub/TutorFeedback.vue'
import CalendarPreview from '../../components/learning-hub/CalendarPreview.vue'
import Notifications from '../../components/learning-hub/Notifications.vue'

const store = useLearningHubStore()

const loading = ref(true)
const errorMessage = ref('')
const hub = ref(null)

onMounted(async () => {
    try {
        hub.value = await store.fetchHub()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'We could not load your dashboard. Please try again.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="m-8 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-else class="space-y-6 p-8">
            <WelcomeCard :stats="hub.stats" />

            <QuickActions :continue-learning="hub.continue_learning" />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <TodaysSessions :sessions="hub.todays_sessions" />
                <Notifications :notifications="hub.notifications" />

                <ContinueLearning :items="hub.continue_learning" />
                <PendingActivities :items="hub.pending_activities" />

                <RecentResults :items="hub.recent_results" />
                <PerformanceSnapshot :snapshot="hub.performance_snapshot" />

                <TutorFeedback :feedback="hub.latest_feedback" />
                <CalendarPreview :calendar-preview="hub.calendar_preview" />
            </div>

            <UpcomingSessions />
        </div>
    </div>
</template>

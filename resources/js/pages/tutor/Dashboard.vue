<script setup>
import { onMounted, ref } from 'vue'
import { useTutorWorkspaceStore } from '../../stores/tutorWorkspace'
import WelcomeCard from '../../components/tutor-workspace/WelcomeCard.vue'
import SummaryCards from '../../components/tutor-workspace/SummaryCards.vue'
import ActionCenter from '../../components/tutor-workspace/ActionCenter.vue'
import TodaysSessions from '../../components/tutor-workspace/TodaysSessions.vue'
import UpcomingSessions from '../../components/tutor-workspace/UpcomingSessions.vue'
import BookingRequests from '../../components/tutor-workspace/BookingRequests.vue'
import StudentsRequiringAttention from '../../components/tutor-workspace/StudentsRequiringAttention.vue'
import PendingReviews from '../../components/tutor-workspace/PendingReviews.vue'
import RecentStudentActivity from '../../components/tutor-workspace/RecentStudentActivity.vue'
import PerformanceSnapshot from '../../components/tutor-workspace/PerformanceSnapshot.vue'
import LessonManagement from '../../components/tutor-workspace/LessonManagement.vue'
import ContentRelease from '../../components/tutor-workspace/ContentRelease.vue'
import AvailabilitySummary from '../../components/tutor-workspace/AvailabilitySummary.vue'
import CalendarPreview from '../../components/tutor-workspace/CalendarPreview.vue'
import QuickActions from '../../components/tutor-workspace/QuickActions.vue'

const store = useTutorWorkspaceStore()

const loading = ref(true)
const errorMessage = ref('')
const workspace = ref(null)

onMounted(async () => {
    try {
        workspace.value = await store.fetchWorkspace()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'We could not load your workspace. Please try again.'
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
            <WelcomeCard />
            <SummaryCards :stats="workspace.stats" />

            <ActionCenter :items="workspace.action_center" />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <TodaysSessions :sessions="workspace.todays_sessions" />
                <BookingRequests :requests="workspace.booking_requests" />

                <PendingReviews :reviews="workspace.pending_reviews" />
                <StudentsRequiringAttention :students="workspace.students_requiring_attention" />

                <RecentStudentActivity :activity="workspace.recent_activity" />
                <PerformanceSnapshot :snapshot="workspace.performance_snapshot" />

                <ContentRelease :items="workspace.content_release" />
                <AvailabilitySummary :availability="workspace.availability" />

                <LessonManagement />
                <QuickActions />
            </div>

            <CalendarPreview :calendar-preview="workspace.calendar_preview" />

            <UpcomingSessions />
        </div>
    </div>
</template>

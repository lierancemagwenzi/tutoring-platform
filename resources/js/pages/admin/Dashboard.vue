<script setup>
import { onMounted, ref } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useAdminDashboardStore } from '../../stores/adminDashboard'
import { useAdminQuickSetupStore } from '../../stores/adminQuickSetup'
import { useAdminActivityLogStore } from '../../stores/adminActivityLog'

const dashboard = useAdminDashboardStore()
const quickSetup = useAdminQuickSetupStore()
const activityLog = useAdminActivityLogStore()
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    loading.value = true
    try {
        await Promise.all([dashboard.fetchOverview(), quickSetup.fetchChecklist(), activityLog.fetchList({ per_page: 8 })])
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading the dashboard.'
    } finally {
        loading.value = false
    }
})

const STAT_GROUPS = [
    {
        key: 'users',
        title: 'Users',
        stats: [
            { key: 'total_students', label: 'Total Students' },
            { key: 'total_tutors', label: 'Total Tutors' },
            { key: 'verified_students', label: 'Verified Students' },
            { key: 'verified_tutors', label: 'Verified Tutors' },
            { key: 'pending_tutor_accounts', label: 'Pending Tutor Accounts' },
        ],
    },
    {
        key: 'subjects',
        title: 'Subjects',
        stats: [
            { key: 'total_subjects', label: 'Total Subjects' },
            { key: 'active_subjects', label: 'Active Subjects' },
            { key: 'inactive_subjects', label: 'Inactive Subjects' },
            { key: 'pending_tutor_subject_requests', label: 'Pending Requests' },
        ],
    },
    {
        key: 'tutoring',
        title: 'Tutoring',
        stats: [
            { key: 'active_tutoring_services', label: 'Active Services' },
            { key: 'total_bookings', label: 'Total Bookings' },
            { key: 'upcoming_sessions', label: 'Upcoming Sessions' },
            { key: 'completed_sessions', label: 'Completed Sessions' },
            { key: 'cancelled_sessions', label: 'Cancelled Sessions' },
        ],
    },
    {
        key: 'self_paced',
        title: 'Self-Paced Courses',
        stats: [
            { key: 'published_courses', label: 'Published Courses' },
            { key: 'draft_courses', label: 'Draft Courses' },
            { key: 'total_enrollments', label: 'Total Enrollments' },
            { key: 'active_learners', label: 'Active Learners' },
            { key: 'completed_courses', label: 'Completed Courses' },
            { key: 'certificates_issued', label: 'Certificates Issued' },
        ],
    },
    {
        key: 'payments',
        title: 'Payments',
        stats: [
            { key: 'total_payments', label: 'Total Payments' },
            { key: 'successful_payments', label: 'Successful' },
            { key: 'failed_payments', label: 'Failed' },
            { key: 'pending_payments', label: 'Pending' },
        ],
    },
]
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Admin Dashboard</h1>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="dashboard.overview">
            <section v-if="dashboard.actionRequired.length > 0" class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
                <h2 class="text-ink font-bold">Action Required</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="(alert, index) in dashboard.actionRequired" :key="index">
                        <router-link
                            :to="alert.link"
                            class="flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-800 hover:bg-amber-100"
                        >
                            <ExclamationTriangleIcon class="h-5 w-5 shrink-0" />
                            {{ alert.message }}
                        </router-link>
                    </li>
                </ul>
            </section>
            <section v-else class="mt-6 rounded-2xl bg-green-50 p-5 text-sm font-medium text-green-700 shadow-sm">
                Nothing needs your attention right now.
            </section>

            <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Quick Setup</h2>
                    <router-link to="/admin/quick-setup" class="text-accent text-sm font-semibold">View Checklist</router-link>
                </div>
                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="bg-amber h-full rounded-full transition-all"
                        :style="{ width: `${(quickSetup.progress.completed / Math.max(quickSetup.progress.total, 1)) * 100}%` }"
                    />
                </div>
                <p class="mt-2 text-sm text-gray-600">
                    {{ quickSetup.progress.completed }} / {{ quickSetup.progress.total }} completed &middot;
                    {{ quickSetup.progress.required_completed }} / {{ quickSetup.progress.required_total }} required
                </p>
                <p class="mt-2 text-sm font-semibold" :class="quickSetup.progress.ready_for_production ? 'text-green-700' : 'text-amber-700'">
                    {{
                        quickSetup.progress.ready_for_production
                            ? 'Ready for production.'
                            : `${quickSetup.progress.required_total - quickSetup.progress.required_completed} required configuration items need attention.`
                    }}
                </p>
            </section>

            <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <section v-for="group in STAT_GROUPS" :key="group.key" class="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 class="text-ink font-bold">{{ group.title }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-4">
                        <div v-for="stat in group.stats" :key="stat.key">
                            <dt class="text-xs text-gray-500">{{ stat.label }}</dt>
                            <dd class="text-ink text-xl font-bold">{{ dashboard.overview[group.key]?.[stat.key] ?? 0 }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-ink font-bold">Recent Activity</h2>
                    <router-link to="/admin/activity-log" class="text-accent text-sm font-semibold">View All</router-link>
                </div>
                <p v-if="activityLog.logs.length === 0" class="mt-3 text-sm text-gray-500">No activity recorded yet.</p>
                <ul v-else class="mt-3 space-y-3">
                    <li v-for="log in activityLog.logs" :key="log.id" class="border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                        <p class="text-ink text-sm">{{ log.description }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-3 text-xs text-gray-500">
                            <span v-if="log.actor" class="font-medium text-gray-600">{{ log.actor.name }}</span>
                            <span>{{ log.created_at.slice(0, 19).replace('T', ' ') }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

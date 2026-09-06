<script setup>
import { onMounted, ref } from 'vue'
import { useAdminTutorApprovalsStore } from '../../stores/adminTutorApprovals'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'
import Pagination from '../../components/common/Pagination.vue'
import ReasonPromptModal from '../../components/common/ReasonPromptModal.vue'

const store = useAdminTutorApprovalsStore()
const loading = ref(true)
const errorMessage = ref('')
const actioningId = ref(null)

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchPending(page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading pending tutors.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

async function approve(tutorId) {
    if (!confirm('Approve this tutor?')) {
        return
    }
    actioningId.value = tutorId
    errorMessage.value = ''
    try {
        await store.approve(tutorId)
        await load(store.meta.current_page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioningId.value = null
    }
}

// Reject / Request Changes reason modal
const reasonModal = ref({ open: false, mode: null, tutorId: null })
const reasonSubmitting = ref(false)

function openReasonModal(mode, tutorId) {
    reasonModal.value = { open: true, mode, tutorId }
}

async function submitReason(text) {
    reasonSubmitting.value = true
    errorMessage.value = ''
    try {
        if (reasonModal.value.mode === 'reject') {
            await store.reject(reasonModal.value.tutorId, text)
        } else {
            await store.requestChanges(reasonModal.value.tutorId, text)
        }
        reasonModal.value = { open: false, mode: null, tutorId: null }
        await load(store.meta.current_page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        reasonSubmitting.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Tutor Approvals</h1>
        <p class="mt-1 text-sm text-muted">Tutors awaiting a decision on their application.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.tutors.length === 0" class="mt-16 text-center text-muted">No pending tutor approvals.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="tutor in store.tutors" :key="tutor.id" class="rounded-2xl bg-card p-5 shadow-elevated">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-body font-bold">{{ tutor.name }}</p>
                        <p class="text-sm text-muted">{{ tutor.email }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span>Registered {{ tutor.registered_at?.slice(0, 10) }}</span>
                            <span>{{ tutor.email_verified ? 'Email verified' : 'Email not verified' }}</span>
                            <span>{{ tutor.profile_complete ? 'Profile complete' : 'Profile incomplete' }}</span>
                            <span>{{ tutor.requested_subjects_count }} subjects requested</span>
                            <span :class="tutor.has_banking_details ? 'text-green-600' : 'text-red-600'">
                                {{ tutor.has_banking_details ? 'Banking details on file' : 'No banking details' }}
                            </span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(tutor.status)">
                        {{ adminStatusLabel(tutor.status) }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-4">
                    <router-link
                        :to="{ name: 'admin.tutor-approvals.show', params: { id: tutor.id } }"
                        class="text-accent text-sm font-semibold"
                    >
                        Review
                    </router-link>
                    <button
                        type="button"
                        :disabled="actioningId === tutor.id || !tutor.has_banking_details"
                        :title="tutor.has_banking_details ? '' : 'This tutor has no banking details on file.'"
                        class="text-sm font-semibold text-green-600 disabled:opacity-40"
                        @click="approve(tutor.id)"
                    >
                        Approve
                    </button>
                    <button type="button" class="text-sm font-semibold text-red-600" @click="openReasonModal('reject', tutor.id)">Reject</button>
                    <button type="button" class="text-sm font-semibold text-muted" @click="openReasonModal('request-changes', tutor.id)">
                        Request Changes
                    </button>
                </div>
            </div>
        </div>

        <Pagination :meta="store.meta" @change="load" />

        <ReasonPromptModal
            v-model="reasonModal.open"
            :title="reasonModal.mode === 'reject' ? 'Reject Tutor' : 'Request Changes'"
            :label="reasonModal.mode === 'reject' ? 'Reason for rejection' : 'What needs to change'"
            confirm-label="Submit"
            :loading="reasonSubmitting"
            @confirm="submitReason"
        />
    </div>
</template>

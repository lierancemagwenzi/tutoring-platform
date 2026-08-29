<script setup>
import { onMounted, ref } from 'vue'
import { useAdminTutorApprovalsStore } from '../../stores/adminTutorApprovals'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'
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

// Detail review modal
const reviewing = ref(null)
const reviewLoading = ref(false)

async function openReview(tutor) {
    reviewing.value = { id: tutor.id }
    reviewLoading.value = true
    try {
        reviewing.value = await store.fetchDetail(tutor.id)
        reviewing.value.id = tutor.id
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this tutor.'
        reviewing.value = null
    } finally {
        reviewLoading.value = false
    }
}

async function approve(tutorId) {
    if (!confirm('Approve this tutor?')) {
        return
    }
    actioningId.value = tutorId
    errorMessage.value = ''
    try {
        await store.approve(tutorId)
        reviewing.value = null
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
        reviewing.value = null
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
        <h1 class="text-ink text-2xl font-bold">Tutor Approvals</h1>
        <p class="mt-1 text-sm text-gray-500">Tutors awaiting a decision on their application.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.tutors.length === 0" class="mt-16 text-center text-gray-500">No pending tutor approvals.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="tutor in store.tutors" :key="tutor.id" class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-ink font-bold">{{ tutor.name }}</p>
                        <p class="text-sm text-gray-500">{{ tutor.email }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
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

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
                    <button type="button" class="text-accent text-sm font-semibold" @click="openReview(tutor)">Review</button>
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
                    <button type="button" class="text-sm font-semibold text-gray-600" @click="openReasonModal('request-changes', tutor.id)">
                        Request Changes
                    </button>
                </div>
            </div>
        </div>

        <Pagination :meta="store.meta" @change="load" />

        <Modal :model-value="reviewing !== null" :title="reviewing?.user?.name ?? 'Tutor Application'" @update:model-value="reviewing = null">
            <div v-if="reviewLoading" class="flex justify-center py-8">
                <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
            </div>
            <div v-else-if="reviewing?.user" class="space-y-4 text-sm">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-ink font-medium">{{ reviewing.user.email }}</dd>
                    <dt class="text-gray-500">Registered</dt>
                    <dd class="text-ink font-medium">{{ reviewing.user.registered_at?.slice(0, 10) }}</dd>
                    <dt class="text-gray-500">Years Experience</dt>
                    <dd class="text-ink font-medium">{{ reviewing.profile.years_experience ?? '—' }}</dd>
                    <dt class="text-gray-500">Occupation</dt>
                    <dd class="text-ink font-medium">{{ reviewing.profile.occupation ?? '—' }}</dd>
                </dl>

                <div v-if="reviewing.profile.missing_requirements.length > 0">
                    <p class="font-semibold text-gray-700">Incomplete</p>
                    <ul class="mt-1 list-inside list-disc text-amber-700">
                        <li v-for="req in reviewing.profile.missing_requirements" :key="req">{{ req }}</li>
                    </ul>
                </div>

                <div>
                    <p class="font-semibold text-gray-700">Requested Subjects</p>
                    <ul v-if="reviewing.requested_subjects.length > 0" class="mt-1 space-y-1">
                        <li v-for="subject in reviewing.requested_subjects" :key="subject.id" class="flex items-center justify-between">
                            <span>{{ subject.subject }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="adminStatusBadge(subject.status)">
                                {{ adminStatusLabel(subject.status) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-gray-500">No subjects requested yet.</p>
                </div>

                <div>
                    <p class="font-semibold text-gray-700">Banking Details</p>
                    <div v-if="reviewing.bank_account" class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-gray-600">
                        <span>{{ reviewing.bank_account.bank_name }}</span>
                        <span>{{ reviewing.bank_account.account_holder_name }}</span>
                        <span>{{ reviewing.bank_account.account_number }}</span>
                        <span>Branch {{ reviewing.bank_account.branch_code }} · {{ reviewing.bank_account.account_type }}</span>
                    </div>
                    <p v-else class="mt-1 font-semibold text-red-600">
                        No banking details on file — this tutor cannot be approved until they add them.
                    </p>
                </div>

                <p v-if="reviewing.profile.admin_note" class="rounded-lg bg-gray-50 px-3 py-2 text-gray-600">
                    <span class="font-semibold">Admin note:</span> {{ reviewing.profile.admin_note }}
                </p>
            </div>

            <template v-if="reviewing?.user" #footer>
                <button type="button" class="text-sm font-semibold text-gray-600" @click="openReasonModal('request-changes', reviewing.id)">
                    Request Changes
                </button>
                <button type="button" class="text-sm font-semibold text-red-600" @click="openReasonModal('reject', reviewing.id)">Reject</button>
                <button
                    type="button"
                    :disabled="!reviewing.bank_account"
                    :title="reviewing.bank_account ? '' : 'This tutor has no banking details on file.'"
                    class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400"
                    @click="approve(reviewing.id)"
                >
                    Approve
                </button>
            </template>
        </Modal>

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

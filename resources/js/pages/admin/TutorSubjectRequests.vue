<script setup>
import { onMounted, ref } from 'vue'
import { useAdminTutorSubjectRequestsStore } from '../../stores/adminTutorSubjectRequests'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'
import Pagination from '../../components/common/Pagination.vue'
import ReasonPromptModal from '../../components/common/ReasonPromptModal.vue'

const store = useAdminTutorSubjectRequestsStore()
const loading = ref(true)
const errorMessage = ref('')
const actioningId = ref(null)

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchPending(page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading subject requests.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

async function approve(request) {
    if (!confirm(`Approve "${request.tutor.display_name}" to teach "${request.subject.name}"?`)) {
        return
    }
    actioningId.value = request.id
    errorMessage.value = ''
    try {
        await store.approve(request.id)
        await load(store.meta.current_page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioningId.value = null
    }
}

const reasonModal = ref({ open: false, mode: null, requestId: null })
const reasonSubmitting = ref(false)

function openReasonModal(mode, requestId) {
    reasonModal.value = { open: true, mode, requestId }
}

async function submitReason(text) {
    reasonSubmitting.value = true
    errorMessage.value = ''
    try {
        if (reasonModal.value.mode === 'reject') {
            await store.reject(reasonModal.value.requestId, text)
        } else {
            await store.suspend(reasonModal.value.requestId, text)
        }
        reasonModal.value = { open: false, mode: null, requestId: null }
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
        <h1 class="text-ink text-2xl font-bold">Tutor Subject Requests</h1>
        <p class="mt-1 text-sm text-gray-500">
            Permission for a tutor to teach a specific subject — separate from tutor account approval.
        </p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.requests.length === 0" class="mt-16 text-center text-gray-500">No pending subject requests.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="request in store.requests" :key="request.id" class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-ink font-bold">{{ request.tutor.display_name }}</p>
                        <p class="text-sm text-gray-500">{{ request.tutor.email }}</p>
                        <p class="mt-2 text-sm text-gray-700">
                            Requesting <span class="font-semibold">{{ request.subject.name }}</span>
                        </p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>Requested {{ request.requested_at?.slice(0, 10) }}</span>
                            <span>Tutor account: {{ adminStatusLabel(request.tutor.profile_status) }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(request.status)">
                        {{ adminStatusLabel(request.status) }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        :disabled="actioningId === request.id"
                        class="text-sm font-semibold text-green-600 disabled:opacity-40"
                        @click="approve(request)"
                    >
                        Approve
                    </button>
                    <button type="button" class="text-sm font-semibold text-red-600" @click="openReasonModal('reject', request.id)">Reject</button>
                    <button type="button" class="text-sm font-semibold text-gray-600" @click="openReasonModal('suspend', request.id)">
                        Suspend
                    </button>
                </div>
            </div>
        </div>

        <Pagination :meta="store.meta" @change="load" />

        <ReasonPromptModal
            v-model="reasonModal.open"
            :title="reasonModal.mode === 'reject' ? 'Reject Subject Request' : 'Suspend Subject Permission'"
            label="Reason"
            confirm-label="Submit"
            :loading="reasonSubmitting"
            @confirm="submitReason"
        />
    </div>
</template>

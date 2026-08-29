<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useSupportTicketsStore } from '../../stores/supportTickets'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const route = useRoute()
const store = useSupportTicketsStore()
const loading = ref(true)
const errorMessage = ref('')

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList({ page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading your tickets.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

// Raise a ticket modal
const raiseModal = reactive({ open: false, subject: '', message: '' })
const raising = ref(false)
const raiseError = ref('')

function openRaiseModal() {
    raiseModal.open = true
    raiseModal.subject = ''
    raiseModal.message = ''
    raiseError.value = ''
}

async function submitTicket() {
    raising.value = true
    raiseError.value = ''
    try {
        await store.raise(raiseModal.subject, raiseModal.message)
        raiseModal.open = false
        await load(1)
    } catch (error) {
        const errors = error.response?.data?.errors
        raiseError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        raising.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-ink text-2xl font-bold">Help</h1>
                <p class="mt-1 text-sm text-gray-500">Raise a ticket and the admin team will get back to you.</p>
            </div>
            <button
                type="button"
                class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="openRaiseModal"
            >
                Raise a Ticket
            </button>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.tickets.length === 0" class="mt-16 text-center text-gray-500">You haven't raised any tickets yet.</div>

        <div v-else class="mt-6 space-y-3">
            <router-link
                v-for="ticket in store.tickets"
                :key="ticket.id"
                :to="`${route.path}/${ticket.id}`"
                class="block rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-ink font-bold">{{ ticket.subject }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ ticket.message }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>{{ ticket.comments_count }} comment{{ ticket.comments_count === 1 ? '' : 's' }}</span>
                            <span>{{ ticket.created_at.slice(0, 10) }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(ticket.status)">
                        {{ adminStatusLabel(ticket.status) }}
                    </span>
                </div>
            </router-link>
        </div>

        <Pagination :meta="store.meta" @change="load" />

        <Modal v-model="raiseModal.open" title="Raise a Ticket">
            <div class="space-y-4">
                <p v-if="raiseError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ raiseError }}</p>

                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="ticket-subject">Subject</label>
                    <input
                        id="ticket-subject"
                        v-model="raiseModal.subject"
                        type="text"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                        placeholder="What's this about?"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="ticket-message">Message</label>
                    <textarea
                        id="ticket-message"
                        v-model="raiseModal.message"
                        rows="4"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                        placeholder="Describe your issue..."
                    />
                </div>
            </div>

            <template #footer>
                <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700" @click="raiseModal.open = false">
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="raising || !raiseModal.subject.trim() || !raiseModal.message.trim()"
                    class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:opacity-40"
                    @click="submitTicket"
                >
                    {{ raising ? 'Submitting…' : 'Submit Ticket' }}
                </button>
            </template>
        </Modal>
    </div>
</template>

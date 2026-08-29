<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useTutorEarningsStore } from '../../stores/tutorEarnings'
import { useTutorPaymentTicketsStore } from '../../stores/tutorPaymentTickets'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const store = useTutorEarningsStore()
const ticketsStore = useTutorPaymentTicketsStore()
const loading = ref(true)
const errorMessage = ref('')

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await Promise.all([store.fetchSummary(), store.fetchTransactions({ page })])
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading your earnings.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

// Raise a ticket modal
const raiseModal = reactive({ open: false, transactionId: null, message: '' })
const raising = ref(false)
const raiseError = ref('')
const raiseSuccess = ref(false)

function openRaiseModal(transaction) {
    raiseModal.open = true
    raiseModal.transactionId = transaction.id
    raiseModal.message = ''
    raiseError.value = ''
    raiseSuccess.value = false
}

async function submitTicket() {
    raising.value = true
    raiseError.value = ''
    try {
        await ticketsStore.raise(raiseModal.transactionId, raiseModal.message)
        raiseSuccess.value = true
    } catch (error) {
        raiseError.value = error.response?.data?.errors?.message?.[0] ?? error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        raising.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Earnings</h1>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="store.summary">
            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-xs text-gray-500">All-Time Earnings</p>
                    <p class="text-ink text-2xl font-bold">R{{ store.summary.all_time_earnings }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-xs text-gray-500">This Month</p>
                    <p class="text-ink text-2xl font-bold">R{{ store.summary.this_month_earnings }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-xs text-gray-500">Transactions</p>
                    <p class="text-ink text-2xl font-bold">{{ store.summary.transactions_count }}</p>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-xs text-gray-500">Pending Payout</p>
                    <p class="text-ink text-2xl font-bold">R{{ store.summary.pending_payout_total }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-xs text-gray-500">Paid Out</p>
                    <p class="text-ink text-2xl font-bold">R{{ store.summary.paid_total }}</p>
                </div>
            </div>

            <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
                <h2 class="text-ink font-bold">Your Commission Rate</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ store.summary.applicable_rate.scope === 'global' ? 'Platform default rate.' : 'Your custom rate, set by the platform.' }}
                    Set by the platform — contact support if you believe this is incorrect.
                </p>
                <p class="text-ink mt-3 text-lg font-semibold">
                    {{ store.summary.applicable_rate.percentage }}% + R{{ store.summary.applicable_rate.fixed_fee }} fixed
                </p>
            </section>

            <section class="mt-8">
                <h2 class="text-ink font-bold">Transaction History</h2>

                <div v-if="store.transactions.length === 0" class="mt-8 text-center text-gray-500">No earnings yet.</div>

                <div v-else class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Product</th>
                                <th class="px-5 py-3 font-semibold">Gross</th>
                                <th class="px-5 py-3 font-semibold">You Earned</th>
                                <th class="px-5 py-3 font-semibold">Payout Status</th>
                                <th class="px-5 py-3 font-semibold">Date</th>
                                <th class="px-5 py-3 font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="transaction in store.transactions" :key="transaction.id">
                                <td class="px-5 py-3 text-gray-600 capitalize">{{ transaction.product_type.replace('_', ' ') }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ transaction.currency }} {{ transaction.gross_amount }}</td>
                                <td class="text-ink px-5 py-3 font-semibold">{{ transaction.currency }} {{ transaction.tutor_amount }}</td>
                                <td class="px-5 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="adminStatusBadge(transaction.payout_status)"
                                    >
                                        {{ adminStatusLabel(transaction.payout_status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">{{ transaction.created_at.slice(0, 10) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <button
                                        type="button"
                                        class="text-accent text-xs font-semibold"
                                        @click="openRaiseModal(transaction)"
                                    >
                                        Raise a Ticket
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :meta="store.meta" @change="load" />
            </section>
        </template>

        <Modal v-model="raiseModal.open" title="Raise a Payment Ticket">
            <div v-if="raiseSuccess" class="text-sm text-green-700">
                Your ticket has been submitted. The admin team will review it and respond via the ticket thread.
            </div>
            <div v-else class="space-y-3">
                <p class="text-sm text-gray-500">
                    Describe the issue with this transaction (e.g. payment wasn't received). An admin will review and respond.
                </p>
                <p v-if="raiseError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ raiseError }}</p>
                <textarea
                    v-model="raiseModal.message"
                    rows="4"
                    class="focus:border-accent w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    placeholder="I completed this booking but haven't received payment..."
                />
            </div>

            <template v-if="!raiseSuccess" #footer>
                <button
                    type="button"
                    :disabled="raising || !raiseModal.message.trim()"
                    class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="submitTicket"
                >
                    {{ raising ? 'Submitting…' : 'Submit Ticket' }}
                </button>
            </template>
            <template v-else #footer>
                <button
                    type="button"
                    class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-95"
                    @click="raiseModal.open = false"
                >
                    Close
                </button>
            </template>
        </Modal>
    </div>
</template>

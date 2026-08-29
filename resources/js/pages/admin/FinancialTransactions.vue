<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminFinancialTransactionsStore } from '../../stores/adminFinancialTransactions'
import Pagination from '../../components/common/Pagination.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const PRODUCT_TYPE_TABS = [
    { value: '', label: 'All' },
    { value: 'course_offering', label: 'Courses' },
    { value: 'tutoring_service_booking', label: 'Bookings' },
]

const store = useAdminFinancialTransactionsStore()
const loading = ref(true)
const errorMessage = ref('')

const filters = reactive({ product_type: '', from: '', to: '' })

async function applyFilters(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchTransactions({ ...filters, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading transactions.'
    } finally {
        loading.value = false
    }
}

onMounted(() => applyFilters())

function selectProductType(type) {
    filters.product_type = type
    applyFilters(1)
}

const markingPaidId = ref(null)
const updatingStatusId = ref(null)

const STATUS_OPTIONS = [
    { value: 'eligible', label: 'Eligible' },
    { value: 'processing', label: 'Processing' },
    { value: 'on_hold', label: 'On Hold' },
]

async function markPaid(transaction) {
    markingPaidId.value = transaction.id
    errorMessage.value = ''
    try {
        await store.markPaid(transaction.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not mark this transaction as paid.'
    } finally {
        markingPaidId.value = null
    }
}

async function updateStatus(transaction, status) {
    if (!status) return
    updatingStatusId.value = transaction.id
    errorMessage.value = ''
    try {
        await store.updatePayoutStatus(transaction.id, status)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not update this transaction\'s payout status.'
    } finally {
        updatingStatusId.value = null
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Financial Transactions</h1>
        <p class="mt-1 text-sm text-gray-500">The commission snapshot recorded for every confirmed payment.</p>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in PRODUCT_TYPE_TABS"
                    :key="tab.value"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="filters.product_type === tab.value ? 'bg-amber text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                    @click="selectProductType(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
            <input
                v-model="filters.from"
                type="date"
                class="focus:border-accent rounded-xl border border-gray-300 px-3.5 py-2 text-sm text-gray-900 outline-none"
                @change="applyFilters(1)"
            />
            <span class="text-sm text-gray-400">to</span>
            <input
                v-model="filters.to"
                type="date"
                class="focus:border-accent rounded-xl border border-gray-300 px-3.5 py-2 text-sm text-gray-900 outline-none"
                @change="applyFilters(1)"
            />
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.transactions.length === 0" class="mt-16 text-center text-gray-500">No transactions found.</div>

        <div v-else class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Tutor</th>
                        <th class="px-5 py-3 font-semibold">Student</th>
                        <th class="px-5 py-3 font-semibold">Product</th>
                        <th class="px-5 py-3 font-semibold">Gross</th>
                        <th class="px-5 py-3 font-semibold">Platform Fee</th>
                        <th class="px-5 py-3 font-semibold">Tutor Amount</th>
                        <th class="px-5 py-3 font-semibold">Payout Status</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Paid</th>
                        <th class="px-5 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="transaction in store.transactions" :key="transaction.id">
                        <td class="text-ink px-5 py-3 font-medium">{{ transaction.tutor.name }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ transaction.student.name }}</td>
                        <td class="px-5 py-3 text-gray-600 capitalize">{{ transaction.product_type.replace('_', ' ') }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ transaction.currency }} {{ transaction.gross_amount }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ transaction.currency }} {{ transaction.platform_fee_total }}</td>
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
                        <td class="px-5 py-3 text-gray-500">{{ transaction.paid_at ? transaction.paid_at.slice(0, 10) : '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            <div v-if="['pending', 'eligible', 'processing', 'on_hold'].includes(transaction.payout_status)" class="flex items-center justify-end gap-2">
                                <select
                                    :disabled="updatingStatusId === transaction.id"
                                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 disabled:opacity-40"
                                    @change="updateStatus(transaction, $event.target.value); $event.target.value = ''"
                                >
                                    <option value="">Move to…</option>
                                    <option v-for="option in STATUS_OPTIONS" :key="option.value" :value="option.value" :disabled="option.value === transaction.payout_status">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    :disabled="!transaction.eligible_for_payout || markingPaidId === transaction.id"
                                    :title="transaction.eligible_for_payout ? '' : 'This booking must be completed before it can be paid out.'"
                                    class="bg-amber rounded-full px-4 py-1.5 text-xs font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400"
                                    @click="markPaid(transaction)"
                                >
                                    {{ markingPaidId === transaction.id ? 'Marking…' : 'Mark Paid' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="store.meta" @change="applyFilters" />
    </div>
</template>

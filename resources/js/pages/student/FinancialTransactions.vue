<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useStudentFinancialTransactionsStore } from '../../stores/studentFinancialTransactions'
import Pagination from '../../components/common/Pagination.vue'

const store = useStudentFinancialTransactionsStore()
const loading = ref(true)
const errorMessage = ref('')
const filters = reactive({ from: '', to: '' })

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList({ ...filters, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading your payments.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Payments</h1>
        <p class="text-muted mt-1 text-sm">Everything you've paid for, one line per item.</p>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <input
                v-model="filters.from"
                type="date"
                class="focus:border-accent bg-card text-body border-border rounded-xl border px-3.5 py-2 text-sm outline-none"
                @change="load(1)"
            />
            <span class="text-muted text-sm">to</span>
            <input
                v-model="filters.to"
                type="date"
                class="focus:border-accent bg-card text-body border-border rounded-xl border px-3.5 py-2 text-sm outline-none"
                @change="load(1)"
            />
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.transactions.length === 0" class="text-muted mt-16 text-center">No payments yet.</div>

        <div v-else class="bg-card shadow-elevated mt-6 overflow-x-auto rounded-2xl">
            <table class="w-full text-left text-sm">
                <thead class="border-border text-muted border-b text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Item</th>
                        <th class="px-5 py-3 font-semibold">Type</th>
                        <th class="px-5 py-3 font-semibold">Amount</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-border divide-y">
                    <tr v-for="transaction in store.transactions" :key="transaction.id">
                        <td class="text-body px-5 py-3 font-medium">{{ transaction.product_title ?? '—' }}</td>
                        <td class="text-muted px-5 py-3 capitalize">{{ transaction.product_type.replace('_', ' ') }}</td>
                        <td class="text-body px-5 py-3 font-semibold">{{ transaction.currency }} {{ transaction.gross_amount }}</td>
                        <td class="text-muted px-5 py-3">{{ transaction.created_at.slice(0, 10) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="store.meta" @change="load" />
    </div>
</template>

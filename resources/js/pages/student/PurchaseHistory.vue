<script setup>
import { onMounted, ref } from 'vue'
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline'
import { useOrderStore } from '../../stores/orders'

const store = useOrderStore()
const loading = ref(true)
const errorMessage = ref('')

const STATUS_STYLES = {
    paid: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    cancelled: 'bg-gray-100 text-gray-600',
    expired: 'bg-gray-100 text-gray-600',
    refunded: 'bg-blue-100 text-blue-700',
}

onMounted(async () => {
    try {
        await store.fetchMyOrders()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Purchase History</h1>

        <div v-if="loading" class="mt-6 animate-pulse space-y-3">
            <div v-for="n in 4" :key="n" class="h-16 rounded-2xl bg-white shadow-sm" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div
            v-else-if="store.myOrders.length === 0"
            class="mt-6 flex flex-col items-center rounded-2xl bg-white py-24 text-center shadow-sm"
        >
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <ClipboardDocumentListIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-gray-500">You haven't made any purchases yet.</p>
        </div>

        <div v-else class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Order Number</th>
                        <th class="px-5 py-3 font-medium">Course</th>
                        <th class="px-5 py-3 font-medium">Amount</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Purchase Date</th>
                        <th class="px-5 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="order in store.myOrders" :key="order.id">
                        <td class="text-ink px-5 py-4 font-medium">{{ order.order_number }}</td>
                        <td class="px-5 py-4 text-gray-600">{{ order.items[0]?.product?.title ?? '—' }}</td>
                        <td class="text-ink px-5 py-4 font-semibold">{{ order.currency }} {{ order.final_amount }}</td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="STATUS_STYLES[order.status]">
                                {{ order.status }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-gray-500">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                        <td class="px-5 py-4 text-right">
                            <router-link :to="`/student/orders/${order.id}`" class="text-accent font-semibold">
                                View
                            </router-link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

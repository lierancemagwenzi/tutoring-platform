<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useOrderStore } from '../../stores/orders'
import OrderSummary from '../../components/commerce/OrderSummary.vue'

const route = useRoute()
const store = useOrderStore()
const order = ref(null)
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    try {
        order.value = await store.fetchOrder(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This order could not be found.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="mx-auto max-w-2xl p-8">
        <h1 class="text-body text-2xl font-bold">Order Details</h1>

        <div v-if="loading" class="bg-card shadow-elevated mt-6 h-48 animate-pulse rounded-2xl" />

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="order">
            <div class="mt-6">
                <OrderSummary :order="order" />
            </div>

            <div v-if="order.payment" class="bg-card shadow-elevated mt-6 rounded-2xl p-6">
                <h2 class="text-body font-bold">Payment Details</h2>
                <dl class="mt-3 grid grid-cols-2 gap-y-3 text-sm">
                    <dt class="text-muted">Provider</dt>
                    <dd class="text-body font-medium capitalize">{{ order.payment.provider }}</dd>
                    <dt class="text-muted">Reference</dt>
                    <dd class="text-body font-medium">{{ order.payment.payment_reference }}</dd>
                    <dt class="text-muted">Status</dt>
                    <dd class="text-body font-medium capitalize">{{ order.payment.status }}</dd>
                    <dt v-if="order.payment.payment_method" class="text-muted">Method</dt>
                    <dd v-if="order.payment.payment_method" class="text-body font-medium">{{ order.payment.payment_method }}</dd>
                    <dt v-if="order.payment.paid_at" class="text-muted">Paid At</dt>
                    <dd v-if="order.payment.paid_at" class="text-body font-medium">{{ new Date(order.payment.paid_at).toLocaleString() }}</dd>
                </dl>
            </div>

            <router-link
                v-if="order.status === 'pending'"
                :to="`/student/orders/${order.id}/pay`"
                class="bg-amber shadow-elevated mt-6 inline-block rounded-full px-6 py-3 font-semibold text-white transition hover:brightness-95"
            >
                Complete Payment
            </router-link>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { CheckCircleIcon, ClockIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import { useOrderStore } from '../../stores/orders'

const props = defineProps({
    orderId: { type: [String, Number], required: true },
})

const MAX_ATTEMPTS = 10
const POLL_INTERVAL_MS = 2000

const store = useOrderStore()
const order = ref(null)
const attempts = ref(0)
const errorMessage = ref('')
let timer = null

const settled = computed(() => order.value && order.value.status !== 'pending')

const primaryItem = computed(() => order.value?.items?.[0])
const isBooking = computed(() => primaryItem.value?.product_type === 'tutoring_service_booking')
const successRoute = computed(() =>
    isBooking.value ? `/student/bookings/${primaryItem.value.product.id}` : '/student/my-courses',
)
const successLabel = computed(() => (isBooking.value ? 'View Booking' : 'Go to My Courses'))
const successMessage = computed(() =>
    isBooking.value ? 'Your booking is confirmed and your session has been scheduled.' : 'You now have access to this course.',
)

async function poll() {
    try {
        order.value = await store.fetchOrder(props.orderId)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This order could not be found.'
        return
    }

    attempts.value += 1

    if (!settled.value && attempts.value < MAX_ATTEMPTS) {
        timer = setTimeout(poll, POLL_INTERVAL_MS)
    }
}

onMounted(poll)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
    <div class="mx-auto max-w-lg p-8 text-center">
        <p v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="order?.status === 'paid'">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                <CheckCircleIcon class="h-8 w-8" />
            </span>
            <h2 class="text-ink mt-4 text-xl font-bold">Payment successful!</h2>
            <p class="mt-2 text-gray-500">{{ successMessage }}</p>
            <router-link
                :to="successRoute"
                class="bg-amber mt-6 inline-block rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
            >
                {{ successLabel }}
            </router-link>
        </template>

        <template v-else-if="!settled && attempts < MAX_ATTEMPTS">
            <span class="border-amber mx-auto flex h-16 w-16 animate-spin items-center justify-center rounded-full border-4 border-t-transparent" />
            <h2 class="text-ink mt-4 text-xl font-bold">Confirming your payment…</h2>
            <p class="mt-2 text-gray-500">This can take a few seconds after you return from PayFast.</p>
        </template>

        <template v-else>
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                <ClockIcon v-if="!order" class="h-8 w-8" />
                <XCircleIcon v-else class="h-8 w-8" />
            </span>
            <h2 class="text-ink mt-4 text-xl font-bold">Payment not confirmed yet</h2>
            <p class="mt-2 text-gray-500">
                We haven't received confirmation of this payment. If you completed checkout, it may still be processing.
            </p>
            <router-link
                v-if="order"
                :to="`/student/orders/${order.id}`"
                class="bg-amber mt-6 inline-block rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
            >
                View Order
            </router-link>
        </template>
    </div>
</template>

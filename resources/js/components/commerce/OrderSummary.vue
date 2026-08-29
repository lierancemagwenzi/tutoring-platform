<script setup>
defineProps({
    order: { type: Object, required: true },
})
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-ink font-bold">Order {{ order.order_number }}</h2>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 capitalize">{{ order.status }}</span>
        </div>

        <ul class="mt-4 divide-y divide-gray-100">
            <li v-for="item in order.items" :key="item.id" class="flex items-center justify-between py-3">
                <div>
                    <p class="text-ink font-medium">{{ item.product?.title ?? 'Course' }}</p>
                    <p class="text-xs text-gray-500">Qty {{ item.quantity }}</p>
                </div>
                <p class="text-ink font-semibold">{{ order.currency }} {{ item.total }}</p>
            </li>
        </ul>

        <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Subtotal</dt>
                <dd class="text-ink font-medium">{{ order.currency }} {{ order.total_amount }}</dd>
            </div>
            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between">
                <dt class="text-gray-500">Discount</dt>
                <dd class="font-medium text-green-600">-{{ order.currency }} {{ order.discount_amount }}</dd>
            </div>
            <div v-if="Number(order.booking_fee_amount) > 0" class="flex justify-between">
                <dt class="text-gray-500">Platform &amp; Booking Fee ({{ order.booking_fee_percentage }}%)</dt>
                <dd class="text-ink font-medium">{{ order.currency }} {{ order.booking_fee_amount }}</dd>
            </div>
            <div class="flex justify-between border-t border-gray-100 pt-2 text-base">
                <dt class="text-ink font-bold">Total</dt>
                <dd class="text-ink font-bold">{{ order.currency }} {{ order.final_amount }}</dd>
            </div>
        </dl>
    </div>
</template>

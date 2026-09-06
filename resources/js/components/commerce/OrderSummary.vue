<script setup>
defineProps({
    order: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-card shadow-elevated rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-body font-bold">Order {{ order.order_number }}</h2>
            <span class="bg-card-alt text-body rounded-full px-3 py-1 text-xs font-semibold capitalize">{{ order.status }}</span>
        </div>

        <ul class="divide-border mt-4 divide-y">
            <li v-for="item in order.items" :key="item.id" class="flex items-center justify-between py-3">
                <div>
                    <p class="text-body font-medium">{{ item.product?.title ?? 'Course' }}</p>
                    <p class="text-muted text-xs">Qty {{ item.quantity }}</p>
                </div>
                <p class="text-body font-semibold">{{ order.currency }} {{ item.total }}</p>
            </li>
        </ul>

        <dl class="border-border mt-4 space-y-2 border-t pt-4 text-sm">
            <div class="flex justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd class="text-body font-medium">{{ order.currency }} {{ order.total_amount }}</dd>
            </div>
            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between">
                <dt class="text-muted">Discount</dt>
                <dd class="font-medium text-green-600">-{{ order.currency }} {{ order.discount_amount }}</dd>
            </div>
            <div v-if="Number(order.booking_fee_amount) > 0" class="flex justify-between">
                <dt class="text-muted">Platform &amp; Booking Fee ({{ order.booking_fee_percentage }}%)</dt>
                <dd class="text-body font-medium">{{ order.currency }} {{ order.booking_fee_amount }}</dd>
            </div>
            <div class="border-border flex justify-between border-t pt-2 text-base">
                <dt class="text-body font-bold">Total</dt>
                <dd class="text-body font-bold">{{ order.currency }} {{ order.final_amount }}</dd>
            </div>
        </dl>
    </div>
</template>

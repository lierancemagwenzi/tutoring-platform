<script setup>
defineProps({
    pricing: { type: Object, required: true },
})
</script>

<template>
    <div>
        <p v-if="pricing.is_free" class="text-body text-3xl font-bold">Free</p>

        <template v-else-if="pricing.discounted_price !== null">
            <div class="flex items-baseline gap-3">
                <p class="text-body text-3xl font-bold">{{ pricing.currency }} {{ pricing.discounted_price }}</p>
                <p class="text-muted text-lg line-through">{{ pricing.currency }} {{ pricing.original_price }}</p>
            </div>
            <p class="mt-1 text-sm font-semibold text-green-600">
                {{ pricing.discount_percentage }}% off &middot; Save {{ pricing.currency }} {{ pricing.savings }}
            </p>
        </template>

        <p v-else class="text-body text-3xl font-bold">{{ pricing.currency }} {{ pricing.original_price }}</p>
    </div>
</template>

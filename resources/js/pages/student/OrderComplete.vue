<script setup>
import { useRoute } from 'vue-router'
import PaymentStatus from '../../components/commerce/PaymentStatus.vue'

const route = useRoute()
</script>

<template>
    <PaymentStatus :order-id="route.params.id" />
</template>

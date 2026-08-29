<script setup>
import { useRoute } from 'vue-router'
import PaymentRedirect from '../../components/commerce/PaymentRedirect.vue'

const route = useRoute()
</script>

<template>
    <PaymentRedirect :order-id="route.params.id" />
</template>

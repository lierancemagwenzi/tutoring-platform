<script setup>
import { onMounted, ref } from 'vue'
import { useOrderStore } from '../../stores/orders'

const props = defineProps({
    orderId: { type: [String, Number], required: true },
})

const store = useOrderStore()
const formRef = ref(null)
const processUrl = ref('')
const fields = ref({})
const errorMessage = ref('')

onMounted(async () => {
    try {
        const { process_url: url, fields: payload } = await store.fetchPaymentFields(props.orderId)
        processUrl.value = url
        fields.value = payload

        // PayFast requires a real browser POST to its hosted page — never a
        // button that calls our own API. Submitting synchronously in the
        // same tick as mount keeps the "Redirecting…" state visible only
        // for the brief instant before navigation actually happens.
        await new Promise((resolve) => setTimeout(resolve, 50))
        formRef.value?.submit()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This order could not be prepared for payment.'
    }
})
</script>

<template>
    <div class="mx-auto max-w-md p-8 text-center">
        <p v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else>
            <div class="border-amber mx-auto h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
            <p class="text-body mt-4 font-semibold">Redirecting to PayFast…</p>
            <p class="text-muted mt-1 text-sm">Please wait, you're being taken to a secure payment page.</p>

            <form ref="formRef" :action="processUrl" method="POST" class="hidden">
                <input v-for="(value, key) in fields" :key="key" type="hidden" :name="key" :value="value" />
            </form>
        </template>
    </div>
</template>

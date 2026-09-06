<script setup>
import { onMounted, ref } from 'vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'
import { useFaqsStore } from '../../stores/faqs'

const store = useFaqsStore()
const loading = ref(true)
const errorMessage = ref('')
const openId = ref(null)

onMounted(async () => {
    try {
        await store.fetchList()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading FAQs.'
    } finally {
        loading.value = false
    }
})

function toggle(faq) {
    openId.value = openId.value === faq.id ? null : faq.id
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">FAQs</h1>
        <p class="text-muted mt-1 text-sm">Answers to common questions.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.faqs.length === 0" class="text-muted mt-16 text-center">No FAQs available yet.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="faq in store.faqs" :key="faq.id" class="bg-card shadow-elevated rounded-2xl">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                    @click="toggle(faq)"
                >
                    <span class="text-body font-semibold">{{ faq.question }}</span>
                    <ChevronDownIcon
                        class="text-muted h-5 w-5 shrink-0 transition-transform"
                        :class="{ 'rotate-180': openId === faq.id }"
                    />
                </button>
                <p v-if="openId === faq.id" class="border-border text-muted border-t px-5 py-4 text-sm">
                    {{ faq.answer }}
                </p>
            </div>
        </div>
    </div>
</template>

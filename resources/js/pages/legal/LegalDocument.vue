<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { useLegalDocumentsStore } from '../../stores/legalDocuments'
import logo from '../../assets/logo.png'

const props = defineProps({
    type: {
        type: String,
        required: true,
        validator: (value) => ['terms', 'privacy'].includes(value),
    },
})

const router = useRouter()
const legalDocuments = useLegalDocumentsStore()
const loading = ref(true)

const title = computed(() => (props.type === 'terms' ? 'Terms & Conditions' : 'Privacy Policy'))
const content = computed(() => (props.type === 'terms' ? legalDocuments.termsAndConditions : legalDocuments.privacyPolicy))

onMounted(async () => {
    try {
        await legalDocuments.fetchDocuments()
    } finally {
        loading.value = false
    }
})

function goBack() {
    if (window.history.length > 1) {
        router.back()
        return
    }
    router.push('/login')
}
</script>

<template>
    <div class="bg-surface min-h-screen w-full">
        <header class="border-b border-border">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-5">
                <img :src="logo" alt="ItsLearnable" class="h-8 w-auto" />
                <button type="button" class="text-muted hover:text-body flex items-center gap-1.5 text-sm font-semibold" @click="goBack">
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <h1 class="text-body text-3xl font-bold">{{ title }}</h1>

            <div v-if="loading" class="flex justify-center py-24">
                <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
            </div>

            <div v-else-if="content" class="prose prose-sm text-body mt-8 max-w-none" v-html="content" />

            <p v-else class="text-muted mt-8">This document hasn't been published yet.</p>
        </main>
    </div>
</template>

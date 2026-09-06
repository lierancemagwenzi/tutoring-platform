<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useTutorPaymentTicketsStore } from '../../stores/tutorPaymentTickets'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const route = useRoute()
const store = useTutorPaymentTicketsStore()
const loading = ref(true)
const errorMessage = ref('')
const replyBody = ref('')
const replying = ref(false)
const replyError = ref('')

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchDetail(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this ticket.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

async function submitReply() {
    replying.value = true
    replyError.value = ''
    try {
        await store.addComment(route.params.id, replyBody.value)
        replyBody.value = ''
    } catch (error) {
        replyError.value = error.response?.data?.message ?? 'Something went wrong sending your reply.'
    } finally {
        replying.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <router-link :to="{ name: 'tutor.payment-tickets' }" class="text-accent text-sm font-semibold">&larr; Back to Payment Tickets</router-link>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="store.ticket">
            <div class="bg-card shadow-elevated mt-4 rounded-2xl p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-body text-lg font-bold capitalize">{{ store.ticket.transaction.product_type.replace('_', ' ') }}</p>
                        <p class="text-muted mt-1 text-sm">
                            {{ store.ticket.transaction.currency }} {{ store.ticket.transaction.tutor_amount }} &middot;
                            {{ store.ticket.created_at.slice(0, 10) }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(store.ticket.status)">
                        {{ adminStatusLabel(store.ticket.status) }}
                    </span>
                </div>
                <p class="text-body mt-4 text-sm">{{ store.ticket.message }}</p>
            </div>

            <section class="mt-6">
                <h2 class="text-body font-bold">Conversation</h2>

                <div v-if="store.comments.length === 0" class="text-muted mt-4 text-sm">No replies yet.</div>

                <div v-else class="mt-4 space-y-3">
                    <div
                        v-for="comment in store.comments"
                        :key="comment.id"
                        class="shadow-elevated rounded-2xl p-4"
                        :class="comment.author.is_admin ? 'bg-accent/10' : 'bg-card'"
                    >
                        <div class="text-muted flex items-center justify-between text-xs">
                            <span class="text-body font-semibold">{{ comment.author.name }}{{ comment.author.is_admin ? ' (Admin)' : '' }}</span>
                            <span>{{ comment.created_at.slice(0, 10) }}</span>
                        </div>
                        <p class="text-body mt-2 text-sm">{{ comment.body }}</p>
                    </div>
                </div>

                <div class="bg-card shadow-elevated mt-5 rounded-2xl p-5">
                    <p v-if="replyError" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ replyError }}</p>
                    <textarea
                        v-model="replyBody"
                        rows="3"
                        class="focus:border-accent bg-card text-body border-border w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                        placeholder="Write a reply..."
                    />
                    <button
                        type="button"
                        :disabled="replying || !replyBody.trim()"
                        class="bg-amber shadow-elevated mt-3 rounded-full px-6 py-2 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="submitReply"
                    >
                        {{ replying ? 'Sending…' : 'Send Reply' }}
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>

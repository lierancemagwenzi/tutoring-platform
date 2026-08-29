<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSupportTicketsStore } from '../../stores/supportTickets'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const route = useRoute()
const router = useRouter()
const store = useSupportTicketsStore()
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
        <button type="button" class="text-accent text-sm font-semibold" @click="router.back()">&larr; Back to Help</button>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="store.ticket">
            <div class="mt-4 rounded-2xl bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-ink text-lg font-bold">{{ store.ticket.subject }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ store.ticket.created_at.slice(0, 10) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(store.ticket.status)">
                        {{ adminStatusLabel(store.ticket.status) }}
                    </span>
                </div>
                <p class="mt-4 text-sm text-gray-700">{{ store.ticket.message }}</p>
            </div>

            <section class="mt-6">
                <h2 class="text-ink font-bold">Conversation</h2>

                <div v-if="store.comments.length === 0" class="mt-4 text-sm text-gray-500">No replies yet.</div>

                <div v-else class="mt-4 space-y-3">
                    <div
                        v-for="comment in store.comments"
                        :key="comment.id"
                        class="rounded-2xl p-4 shadow-sm"
                        :class="comment.author.is_admin ? 'bg-indigo-50' : 'bg-white'"
                    >
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span class="font-semibold text-gray-700">{{ comment.author.name }}{{ comment.author.is_admin ? ' (Admin)' : '' }}</span>
                            <span>{{ comment.created_at.slice(0, 10) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-700">{{ comment.body }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl bg-white p-5 shadow-sm">
                    <p v-if="replyError" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ replyError }}</p>
                    <textarea
                        v-model="replyBody"
                        rows="3"
                        class="focus:border-accent w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                        placeholder="Write a reply..."
                    />
                    <button
                        type="button"
                        :disabled="replying || !replyBody.trim()"
                        class="bg-amber mt-3 rounded-full px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="submitReply"
                    >
                        {{ replying ? 'Sending…' : 'Send Reply' }}
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>

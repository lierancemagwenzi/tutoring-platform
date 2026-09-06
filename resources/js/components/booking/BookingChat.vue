<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useBookingChatStore } from '../../stores/bookingChat'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({
    apiBasePath: { type: String, required: true },
    active: { type: Boolean, default: false },
})

const POLL_INTERVAL_MS = 4000

const store = useBookingChatStore()
const auth = useAuthStore()
const loading = ref(true)
const errorMessage = ref('')
const body = ref('')
const sending = ref(false)
const sendError = ref('')
const listEl = ref(null)

let pollTimer = null

function scrollToBottom() {
    nextTick(() => {
        if (listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight
        }
    })
}

async function load(initial = false) {
    try {
        await store.fetchMessages(props.apiBasePath)
        if (initial) {
            scrollToBottom()
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this chat.'
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    store.reset()
    await load(true)
    pollTimer = setInterval(() => load(false), POLL_INTERVAL_MS)
})

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer)
    }
})

async function send() {
    if (!body.value.trim()) {
        return
    }

    sending.value = true
    sendError.value = ''
    try {
        await store.sendMessage(props.apiBasePath, body.value)
        body.value = ''
        scrollToBottom()
    } catch (error) {
        sendError.value = error.response?.data?.errors?.body?.[0] ?? error.response?.data?.message ?? 'Could not send that message.'
    } finally {
        sending.value = false
    }
}
</script>

<template>
    <section class="bg-card shadow-elevated mt-6 rounded-2xl p-5">
        <h2 class="text-body font-bold">Chat</h2>

        <p v-if="errorMessage" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-10">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else>
            <div ref="listEl" class="bg-card-alt mt-3 max-h-96 space-y-3 overflow-y-auto rounded-xl p-4">
                <p v-if="store.messages.length === 0" class="text-muted text-center text-sm">No messages yet.</p>

                <div
                    v-for="message in store.messages"
                    :key="message.id"
                    class="flex"
                    :class="message.sender.id === auth.user?.id ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-xs rounded-2xl px-4 py-2 text-sm sm:max-w-sm"
                        :class="message.sender.id === auth.user?.id ? 'bg-amber text-white' : 'bg-card shadow-elevated text-body'"
                    >
                        <p v-if="message.sender.id !== auth.user?.id" class="text-muted mb-0.5 text-xs font-semibold">
                            {{ message.sender.name }}
                        </p>
                        <p>{{ message.body }}</p>
                    </div>
                </div>
            </div>

            <div v-if="active" class="mt-4">
                <p v-if="sendError" class="mb-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ sendError }}</p>
                <div class="flex gap-2">
                    <input
                        v-model="body"
                        type="text"
                        placeholder="Write a message..."
                        class="focus:border-accent bg-card text-body border-border flex-1 rounded-xl border px-3.5 py-2.5 text-sm outline-none"
                        @keyup.enter="send"
                    />
                    <button
                        type="button"
                        :disabled="sending || !body.trim()"
                        class="bg-amber shadow-elevated rounded-full px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="send"
                    >
                        Send
                    </button>
                </div>
            </div>
            <p v-else class="bg-card-alt text-muted mt-4 rounded-lg px-4 py-3 text-sm">
                This chat is closed because the booking is no longer active.
            </p>
        </template>
    </section>
</template>

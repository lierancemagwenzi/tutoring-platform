<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminFaqsStore } from '../../stores/adminFaqs'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'

const AUDIENCE_TABS = [
    { value: '', label: 'All' },
    { value: 'student', label: 'Students' },
    { value: 'tutor', label: 'Tutors' },
    { value: 'both', label: 'Both' },
]

const AUDIENCE_OPTIONS = [
    { value: 'both', label: 'Both' },
    { value: 'student', label: 'Students only' },
    { value: 'tutor', label: 'Tutors only' },
]

const store = useAdminFaqsStore()
const loading = ref(true)
const errorMessage = ref('')
const activeAudience = ref('')

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList({ audience: activeAudience.value, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading FAQs.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

function selectAudience(audience) {
    activeAudience.value = audience
    load(1)
}

// Create / edit modal
const modalOpen = ref(false)
const editingId = ref(null)
const form = reactive({ question: '', answer: '', audience: 'both', is_published: true })
const saving = ref(false)
const formError = ref('')

function openCreate() {
    editingId.value = null
    form.question = ''
    form.answer = ''
    form.audience = 'both'
    form.is_published = true
    formError.value = ''
    modalOpen.value = true
}

function openEdit(faq) {
    editingId.value = faq.id
    form.question = faq.question
    form.answer = faq.answer
    form.audience = faq.audience
    form.is_published = faq.is_published
    formError.value = ''
    modalOpen.value = true
}

async function submitForm() {
    saving.value = true
    formError.value = ''
    try {
        if (editingId.value) {
            await store.update(editingId.value, { ...form })
        } else {
            await store.create({ ...form })
            await load(store.meta.current_page)
        }
        modalOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}

const deletingId = ref(null)

async function remove(faq) {
    if (!confirm(`Delete the FAQ "${faq.question}"?`)) {
        return
    }
    deletingId.value = faq.id
    try {
        await store.remove(faq.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not delete this FAQ.'
    } finally {
        deletingId.value = null
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-body text-2xl font-bold">FAQs</h1>
                <p class="mt-1 text-sm text-muted">Question &amp; answer entries shown to students and/or tutors.</p>
            </div>
            <button
                type="button"
                class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                @click="openCreate"
            >
                New FAQ
            </button>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="tab in AUDIENCE_TABS"
                :key="tab.value"
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="activeAudience === tab.value ? 'bg-amber text-white' : 'border border-border text-body hover:brightness-95'"
                @click="selectAudience(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.faqs.length === 0" class="mt-16 text-center text-muted">No FAQs yet.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="faq in store.faqs" :key="faq.id" class="rounded-2xl bg-card p-5 shadow-elevated">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-body font-bold">{{ faq.question }}</p>
                        <p class="mt-1 text-sm text-muted">{{ faq.answer }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span class="capitalize">Audience: {{ faq.audience }}</span>
                            <span v-if="!faq.is_published" class="font-semibold text-amber-600">Unpublished</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-4">
                        <button type="button" class="text-accent text-sm font-semibold" @click="openEdit(faq)">Edit</button>
                        <button
                            type="button"
                            :disabled="deletingId === faq.id"
                            class="text-sm font-semibold text-red-600 disabled:opacity-40"
                            @click="remove(faq)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Pagination :meta="store.meta" @change="load" />

        <Modal v-model="modalOpen" :title="editingId ? 'Edit FAQ' : 'New FAQ'">
            <div class="space-y-4">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

                <div>
                    <label class="block text-sm font-semibold text-body" for="faq-question">Question</label>
                    <textarea
                        id="faq-question"
                        v-model="form.question"
                        rows="2"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-body" for="faq-answer">Answer</label>
                    <textarea
                        id="faq-answer"
                        v-model="form.answer"
                        rows="4"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-body" for="faq-audience">Audience</label>
                    <select
                        id="faq-audience"
                        v-model="form.audience"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none"
                    >
                        <option v-for="option in AUDIENCE_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <label class="flex items-center gap-2 text-sm text-body">
                    <input v-model="form.is_published" type="checkbox" class="rounded border-border" />
                    Published
                </label>
            </div>

            <template #footer>
                <button type="button" class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-body" @click="modalOpen = false">
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="saving"
                    class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95 disabled:opacity-40"
                    @click="submitForm"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
